<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeguridadLicenciasTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(array $extra = []): Dependencia
    {
        return Dependencia::create(array_merge([
            'nombre' => 'Dep Licencias',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(5),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'correo_reportes' => 'reportes@licencias.com',
            'activa' => true,
        ], $extra));
    }

    private function usuario(Dependencia $dep, int $rol = 1, string $email = 'admin@licencias.com'): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => $rol,
            'nombre' => 'Admin',
            'apellidos' => 'Licencias',
            'email' => $email,
            'password' => 'password123',
            'estado' => true,
        ]);
    }

    public function test_registro_publico_siempre_crea_un_trial(): void
    {
        $this->postJson('/api/v1/public/register-compania', [
            'companyName' => 'Acme Industrial',
            'email' => 'nuevo@acme.com',
            'password' => 'password123',
            'firstName' => 'Ana',
            'lastNamePaternal' => 'Pérez',
            // Un cliente intenta registrarse directo con plan anual.
            'planType' => 'annual',
        ])->assertStatus(201);

        $dep = Dependencia::where('nombre', 'Acme Industrial')->firstOrFail();

        $this->assertSame('trial', $dep->tipo_licencia);
        $this->assertNotNull($dep->trial_usado_at, 'El trial del registro debe quedar marcado como usado');
        $this->assertTrue(
            $dep->fecha_expiracion->lessThanOrEqualTo(now()->addDays(7)->endOfDay()),
            'El registro no puede otorgar más de 7 días'
        );

        $this->assertDatabaseHas('suscripciones', [
            'dependencia_id' => $dep->id,
            'plan' => 'trial',
            'estado' => 'trial',
        ]);
    }

    public function test_trial_no_se_puede_reactivar(): void
    {
        $dep = $this->dependencia(['trial_usado_at' => now()->subDays(2)]);
        $admin = $this->usuario($dep);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/licencias/1/comprar-mercadopago')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame('trial', $dep->fresh()->tipo_licencia);
    }

    public function test_trial_disponible_se_activa_sin_pasarela(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/licencias/1/comprar-mercadopago')
            ->assertOk()
            ->assertJsonPath('pasarela', 'none');

        $this->assertNotNull($dep->fresh()->trial_usado_at);
    }

    public function test_dependencia_update_requiere_rol_administrativo(): void
    {
        $dep = $this->dependencia();
        $tecnico = $this->usuario($dep, 3, 'tecnico@licencias.com');

        $this->actingAs($tecnico, 'sanctum')
            ->putJson('/api/v1/dependencias/'.$dep->id, ['nombre' => 'Nombre cambiado'])
            ->assertStatus(403);

        $this->assertSame('Dep Licencias', $dep->fresh()->nombre);

        $admin = $this->usuario($dep, 1, 'admin2@licencias.com');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/dependencias/'.$dep->id, ['nombre' => 'Nombre cambiado'])
            ->assertOk();

        $this->assertSame('Nombre cambiado', $dep->fresh()->nombre);
    }

    public function test_no_se_pueden_usar_ejecutores_de_otra_dependencia(): void
    {
        $propia = $this->dependencia();
        $ajena = $this->dependencia(['nombre' => 'Dep Ajena']);

        $admin = $this->usuario($propia);
        $ajeno = $this->usuario($ajena, 3, 'tecnico@ajena.com');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/reportes', [
                'tipo' => 'libre',
                'fecha_inicio' => now()->subHour()->toDateTimeString(),
                'fecha_fin' => now()->toDateTimeString(),
                'ejecutores' => [$ajeno->id],
            ])
            ->assertStatus(422);

        $this->assertSame(0, \App\Models\Reporte::count());
    }

    public function test_evidencia_base64_svg_es_rechazada(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/reportes', [
                'tipo' => 'libre',
                'fecha_inicio' => now()->subHour()->toDateTimeString(),
                'fecha_fin' => now()->toDateTimeString(),
                'evidenciaFotografica' => ['data:image/svg+xml;base64,'.base64_encode('<svg></svg>')],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['evidenciaFotografica']);
    }
}
