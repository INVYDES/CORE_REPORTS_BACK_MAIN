<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReporteEvidenciaTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

    private function dependencia(): Dependencia
    {
        return Dependencia::create([
            'nombre' => 'Dep Evidencias',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(30),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);
    }

    private function admin(Dependencia $dep): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => 1,
            'nombre' => 'Admin',
            'apellidos' => 'Evidencias',
            'email' => 'admin@evidencias.com',
            'password' => 'password123',
            'estado' => true,
        ]);
    }

    private function payloadBase(): array
    {
        return [
            'tipo' => 'libre',
            'fecha_inicio' => now()->subHour()->toDateTimeString(),
            'fecha_fin' => now()->toDateTimeString(),
            'desarrollo' => 'Trabajo de prueba con evidencia',
        ];
    }

    public function test_evidencia_base64_se_persiste_en_el_disco_configurado(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $this->actingAs($this->admin($dep), 'sanctum');

        $response = $this->postJson('/api/v1/reportes', $this->payloadBase() + [
            'evidenciaFotografica' => ['data:image/png;base64,'.self::PNG_1X1],
        ])->assertStatus(201);

        $this->assertNotEmpty(Storage::disk('public')->files('evidencias/'.$dep->id.'/imagenes'));

        $evidencias = $response->json('evidencias');
        $this->assertCount(1, $evidencias);
        $this->assertNotNull($evidencias[0]['url']);
        $this->assertSame('image/png', $evidencias[0]['tipo_archivo']);
        $this->assertGreaterThan(0, $evidencias[0]['peso_kb']);

        $this->assertDatabaseHas('reporte_evidencias', [
            'dependencia_id' => $dep->id,
            'tipo_archivo' => 'image/png',
        ]);
    }

    public function test_evidencia_multipart_se_persiste_en_el_disco_configurado(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $this->actingAs($this->admin($dep), 'sanctum');

        $response = $this->post('/api/v1/reportes', $this->payloadBase() + [
            'evidencias' => [UploadedFile::fake()->image('evidencia.jpg', 30, 30)],
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->assertNotEmpty(Storage::disk('public')->files('evidencias/'.$dep->id.'/imagenes'));
        $this->assertCount(1, $response->json('evidencias'));
    }

    public function test_documentos_se_guardan_separados_de_las_imagenes(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $this->actingAs($this->admin($dep), 'sanctum');

        $this->post('/api/v1/reportes', $this->payloadBase() + [
            'evidencias' => [
                UploadedFile::fake()->image('foto.jpg', 30, 30),
                UploadedFile::fake()->create('reporte.csv', 5, 'text/csv'),
            ],
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->assertCount(1, Storage::disk('public')->files('evidencias/'.$dep->id.'/imagenes'));
        $this->assertCount(1, Storage::disk('public')->files('evidencias/'.$dep->id.'/documentos'));
    }

    public function test_guarda_ruta_y_disco_de_la_evidencia(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $this->actingAs($this->admin($dep), 'sanctum');

        $this->post('/api/v1/reportes', $this->payloadBase() + [
            'evidencias' => [UploadedFile::fake()->image('foto.jpg', 20, 20)],
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $evidencia = \App\Models\ReporteEvidencia::withoutGlobalScopes()->firstOrFail();

        $this->assertStringStartsWith('evidencias/'.$dep->id.'/imagenes/', (string) $evidencia->ruta);
        $this->assertSame('public', $evidencia->disco);
    }

    public function test_documento_se_descarga_solo_con_url_firmada(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $this->actingAs($this->admin($dep), 'sanctum');

        $response = $this->post('/api/v1/reportes', $this->payloadBase() + [
            'evidencias' => [UploadedFile::fake()->create('reporte.csv', 5, 'text/csv')],
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $url = (string) $response->json('evidencias.0.url');

        $this->assertStringContainsString('/documento?', $url);
        $this->assertStringContainsString('signature=', $url);

        $descarga = $this->get($url)->assertOk();
        $this->assertStringContainsString('text/csv', (string) $descarga->headers->get('Content-Type'));
        $this->get(preg_replace('/signature=[^&]+/', 'signature=invalid', $url))
            ->assertForbidden();
    }

    public function test_rechaza_archivos_no_permitidos(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $this->actingAs($this->admin($dep), 'sanctum');

        $this->post('/api/v1/reportes', $this->payloadBase() + [
            'evidencias' => [UploadedFile::fake()->create('malware.exe', 4)],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_rechaza_ejecutable_renombrado_como_imagen(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $this->actingAs($this->admin($dep), 'sanctum');

        $this->post('/api/v1/reportes', $this->payloadBase() + [
            'evidencias' => [UploadedFile::fake()->create('malware.jpg', 4, 'application/x-dosexec')],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_rechaza_data_uri_que_no_es_imagen(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $this->actingAs($this->admin($dep), 'sanctum');

        $this->postJson('/api/v1/reportes', $this->payloadBase() + [
            'evidenciaFotografica' => ['data:text/csv;base64,'.base64_encode('a,b')],
        ])->assertStatus(422);
    }

    public function test_evidencia_url_externa_se_guarda_sin_tocar_el_disco(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $this->actingAs($this->admin($dep), 'sanctum');

        $response = $this->postJson('/api/v1/reportes', $this->payloadBase() + [
            'evidenciaFotografica' => ['https://cdn.example.com/foto.jpg'],
        ])->assertStatus(201);

        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertSame('https://cdn.example.com/foto.jpg', $response->json('evidencias.0.url'));
    }
}
