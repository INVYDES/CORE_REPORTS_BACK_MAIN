<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmpresaApiTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 válido para subidas en base64 */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

    private function crearDependencia(string $nombre = 'Dep Test'): Dependencia
    {
        return Dependencia::create([
            'nombre' => $nombre,
            'rfc' => 'XAXX010101000',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(30),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);
    }

    private function crearUsuario(Dependencia $dep, int $rol = 1, string $email = 'admin@test.com'): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dep->id,
            'numero_empleado' => 'EMP-001',
            'rol' => $rol,
            'nombre' => 'Admin',
            'apellidos' => 'Test',
            'email' => $email,
            'password' => 'password123',
            'estado' => true,
        ]);
    }

    // ---------------------------------------------------------------------
    // Empresa
    // ---------------------------------------------------------------------

    public function test_empresa_show_devuelve_datos_en_camel_case(): void
    {
        $dep = $this->crearDependencia();
        $this->actingAs($this->crearUsuario($dep), 'sanctum');

        $this->getJson('/api/v1/empresa')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombre', 'Dep Test')
            ->assertJsonPath('data.correoContacto', null)
            ->assertJsonPath('data.logo', null);
    }

    public function test_empresa_update_acepta_camel_case_y_snake_case(): void
    {
        $dep = $this->crearDependencia();
        $this->actingAs($this->crearUsuario($dep), 'sanctum');

        $this->putJson('/api/v1/empresa', [
            'nombre' => 'Empresa Renombrada',
            'rfc' => 'XAXX010101010',
            'correoContacto' => 'contacto@test.com',
            'correo_reportes' => 'reportes@test.com',
            'telefono' => '5555555555',
        ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Empresa Renombrada')
            ->assertJsonPath('data.correoContacto', 'contacto@test.com')
            ->assertJsonPath('data.correoReportes', 'reportes@test.com');
    }

    public function test_empresa_update_requiere_rol_administrativo(): void
    {
        $dep = $this->crearDependencia();
        $tecnico = $this->crearUsuario($dep, 3, 'tecnico@test.com');

        $this->actingAs($tecnico, 'sanctum')
            ->putJson('/api/v1/empresa', ['nombre' => 'Nope'])
            ->assertStatus(403);

        $this->actingAs($tecnico, 'sanctum')
            ->postJson('/api/v1/empresa/logo', [])
            ->assertStatus(403);
    }

    public function test_empresa_logo_sin_payload_no_escribe_nada(): void
    {
        Storage::fake('public');

        $dep = $this->crearDependencia();
        $this->actingAs($this->crearUsuario($dep), 'sanctum');

        $this->postJson('/api/v1/empresa/logo', [])->assertStatus(422);

        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertNull($dep->fresh()->logo);
    }

    public function test_empresa_logo_base64_se_persiste_en_el_disco_configurado(): void
    {
        Storage::fake('public');

        $dep = $this->crearDependencia();
        $this->actingAs($this->crearUsuario($dep), 'sanctum');

        $response = $this->postJson('/api/v1/empresa/logo', [
            'logo' => 'data:image/png;base64,'.self::PNG_1X1,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNotEmpty(Storage::disk('public')->files('logos'));
        $this->assertNotNull($response->json('data.logo'));

        $logoGuardado = $dep->fresh()->logo;
        $this->assertStringStartsWith('logos/', $logoGuardado);
        $this->assertTrue(Storage::disk('public')->exists($logoGuardado));
    }

    public function test_empresa_logo_multipart_se_persiste_en_el_disco_configurado(): void
    {
        Storage::fake('public');

        $dep = $this->crearDependencia();
        $this->actingAs($this->crearUsuario($dep), 'sanctum');

        $this->post('/api/v1/empresa/logo', [
            'logo' => UploadedFile::fake()->image('empresa.png', 20, 20),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty(Storage::disk('public')->files('logos'));
        $this->assertTrue(Storage::disk('public')->exists($dep->fresh()->logo));
    }

    // ---------------------------------------------------------------------
    // Subáreas operativas: recurso `areas` (única fuente, sin subdependencias)
    // ---------------------------------------------------------------------

    public function test_crud_de_areas(): void
    {
        $dep = $this->crearDependencia();
        $this->actingAs($this->crearUsuario($dep), 'sanctum');

        $this->getJson('/api/v1/areas')->assertOk()->assertJsonCount(0, 'data');

        $creada = $this->postJson('/api/v1/areas', [
            'codigo' => 'PLANTA-N',
            'nombre' => 'Planta Norte',
        ])->assertStatus(201)->assertJsonPath('nombre', 'Planta Norte');

        $id = $creada->json('id');

        $this->getJson('/api/v1/areas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', 'PLANTA-N');

        $this->getJson("/api/v1/areas/{$id}")->assertOk()->assertJsonPath('nombre', 'Planta Norte');

        $this->putJson("/api/v1/areas/{$id}", ['nombre' => 'Planta Sur'])
            ->assertOk()
            ->assertJsonPath('nombre', 'Planta Sur');

        $this->deleteJson("/api/v1/areas/{$id}")->assertOk();

        $this->getJson("/api/v1/areas/{$id}")->assertStatus(404);
    }

    public function test_areas_estan_aisladas_por_dependencia(): void
    {
        $propia = $this->crearDependencia('Propia');
        $ajena = $this->crearDependencia('Ajena');

        $idAjena = $this->actingAs($this->crearUsuario($ajena, 1, 'ajena@test.com'), 'sanctum')
            ->postJson('/api/v1/areas', ['codigo' => 'AJENA', 'nombre' => 'Área Ajena'])
            ->assertStatus(201)
            ->json('id');

        $this->actingAs($this->crearUsuario($propia, 1, 'propia@test.com'), 'sanctum')
            ->getJson('/api/v1/areas')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/areas/{$idAjena}")->assertStatus(404);
    }

    public function test_crear_area_requiere_rol_administrativo(): void
    {
        $dep = $this->crearDependencia();
        $tecnico = $this->crearUsuario($dep, 3, 'tecnico2@test.com');

        $this->actingAs($tecnico, 'sanctum')
            ->postJson('/api/v1/areas', ['codigo' => 'X', 'nombre' => 'No permitido'])
            ->assertStatus(403);
    }
}
