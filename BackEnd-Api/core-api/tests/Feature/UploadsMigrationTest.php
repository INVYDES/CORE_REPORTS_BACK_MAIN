<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Reporte;
use App\Models\ReporteEvidencia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migra_evidencias_y_logos_entre_discos(): void
    {
        Storage::fake('public');
        Storage::fake('ionos');

        $dep = Dependencia::create([
            'nombre' => 'Dep Migracion',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(30),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);
        $user = Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => 1,
            'nombre' => 'Admin',
            'apellidos' => 'Migracion',
            'email' => 'admin@migracion.com',
            'password' => 'password123',
            'estado' => true,
        ]);
        $reporte = Reporte::create([
            'dependencia_id' => $dep->id,
            'creado_por' => $user->id,
            'folio' => 'REP-MIG-0001',
            'tipo' => 'libre',
            'fecha_inicio' => now()->subHour(),
            'fecha_fin' => now(),
            'estatus' => 'finalizado',
        ]);

        Storage::disk('public')->put('evidencias/1/imagenes/foto.jpg', 'image-bytes');
        Storage::disk('public')->put('evidencias/1/documentos/doc.pdf', 'pdf-bytes');
        Storage::disk('public')->put('logos/logo.png', 'logo-bytes');

        $imagen = ReporteEvidencia::create([
            'dependencia_id' => $dep->id,
            'reporte_id' => $reporte->id,
            'url' => config('app.url').'/storage/evidencias/1/imagenes/foto.jpg',
            'tipo_archivo' => 'image/jpeg',
        ]);
        $documento = ReporteEvidencia::create([
            'dependencia_id' => $dep->id,
            'reporte_id' => $reporte->id,
            'url' => config('app.url').'/storage/evidencias/1/documentos/doc.pdf',
            'tipo_archivo' => 'application/pdf',
        ]);

        $dep->update([
            'logo' => config('app.url').'/storage/logos/logo.png',
        ]);

        $this->artisan('uploads:migrate', [
            '--from' => 'public',
            '--to' => 'ionos',
            '--delete' => true,
        ])->assertSuccessful();

        $imagen->refresh();
        $documento->refresh();
        $dep->refresh();

        $this->assertSame('evidencias/1/imagenes/foto.jpg', $imagen->ruta);
        $this->assertSame('ionos', $imagen->disco);
        $this->assertSame('evidencias/1/documentos/doc.pdf', $documento->ruta);
        $this->assertSame('logos/logo.png', $dep->logo_ruta);
        $this->assertSame('ionos', $dep->logo_disco);

        Storage::disk('ionos')->assertExists('evidencias/1/imagenes/foto.jpg');
        Storage::disk('ionos')->assertExists('evidencias/1/documentos/doc.pdf');
        Storage::disk('ionos')->assertExists('logos/logo.png');
        Storage::disk('public')->assertMissing('evidencias/1/imagenes/foto.jpg');
    }
}
