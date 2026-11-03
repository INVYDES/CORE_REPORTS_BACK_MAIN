<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Reporte;
use App\Models\ReporteEvidencia;
use App\Models\Usuario;
use App\Services\UploadStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReporteExportTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(): Dependencia
    {
        return Dependencia::create([
            'nombre' => 'Dep Export',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(30),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);
    }

    private function admin(Dependencia $dep, string $email = 'admin@export.com'): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => 1,
            'nombre' => 'Admin',
            'apellidos' => 'Export',
            'email' => $email,
            'password' => 'password123',
            'estado' => true,
        ]);
    }

    private function reporte(
        Dependencia $dep,
        Usuario $user,
        string $folio = 'REP-202601-00001',
        mixed $inicio = null,
        mixed $fin = null
    ): Reporte {
        return Reporte::create([
            'dependencia_id' => $dep->id,
            'creado_por' => $user->id,
            'folio' => $folio,
            'tipo' => 'libre',
            'categoria' => 'correctivo',
            'fecha_inicio' => $inicio ?? now()->subHour(),
            'fecha_fin' => $fin ?? now(),
            'desarrollo' => '=SUMA(A1:A2) trabajo de exportacion',
            'estatus' => 'finalizado',
            'costo_mano_obra' => 100,
            'costo_materiales' => 50,
        ]);
    }

    public function test_exporta_csv_con_bom_y_sanea_formulas(): void
    {
        $dep = $this->dependencia();
        $user = $this->admin($dep);
        $this->reporte($dep, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reportes/export?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('REP-202601-00001', $content);
        $this->assertStringContainsString("'=SUMA", $content);
    }

    public function test_exporta_xlsx_valido(): void
    {
        $dep = $this->dependencia();
        $user = $this->admin($dep);
        $this->reporte($dep, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reportes/export?formato=xlsx')
            ->assertOk();

        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type')
        );
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_exportacion_incluye_columnas_de_evidencias(): void
    {
        $dep = $this->dependencia();
        $user = $this->admin($dep);
        $this->reporte($dep, $user);

        $content = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reportes/export?formato=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Evidencias', $content);
        $this->assertStringContainsString('URL evidencias', $content);
    }

    public function test_exportacion_respeta_filtros_de_fecha_y_dependencia(): void
    {
        $dep = $this->dependencia();
        $user = $this->admin($dep);
        $otra = Dependencia::create([
            'nombre' => 'Otra Dep',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(30),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);
        $otroUser = $this->admin($otra, 'otro@export.com');

        $this->reporte($dep, $user, 'REP-ACTUAL', now()->subHour(), now());
        $this->reporte($dep, $user, 'REP-VIEJO', now()->subMonths(6), now()->subMonths(6)->addHour());
        $this->reporte($otra, $otroUser, 'REP-OTRA');

        $content = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reportes/export?formato=csv&desde='.now()->subDay()->toDateString())
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('REP-ACTUAL', $content);
        $this->assertStringNotContainsString('REP-VIEJO', $content);
        $this->assertStringNotContainsString('REP-OTRA', $content);
    }

    public function test_pdf_incrusta_evidencias_del_disco_y_descarta_urls_remotas(): void
    {
        Storage::fake('public');

        $dep = $this->dependencia();
        $user = $this->admin($dep);
        $reporte = $this->reporte($dep, $user);
        $uploads = app(UploadStorageService::class);

        // Imagen real de 1x1 px guardada en el disco propio.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $ruta = $uploads->storeContents((string) $png, 'evidencias/'.$dep->id.'/imagenes', 'png');

        $local = ReporteEvidencia::create([
            'dependencia_id' => $dep->id,
            'reporte_id' => $reporte->id,
            'url' => $uploads->url($ruta),
            'ruta' => $ruta,
            'disco' => $uploads->diskName(),
            'tipo_archivo' => 'image/png',
            'subido_por' => $user->id,
            'fecha_subida' => now(),
        ]);

        $remota = ReporteEvidencia::create([
            'dependencia_id' => $dep->id,
            'reporte_id' => $reporte->id,
            'url' => 'http://169.254.169.254/latest/meta-data/iam/security-credentials/',
            'ruta' => null,
            'tipo_archivo' => 'image/jpeg',
            'subido_por' => $user->id,
            'fecha_subida' => now(),
        ]);

        $this->assertStringStartsWith('data:image/png;base64,', (string) $local->pdfImage());
        $this->assertNull($remota->pdfImage(), 'Una URL remota no debe descargarse ni incrustarse');

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reportes/'.$reporte->id.'/pdf')
            ->assertOk();

        $pdf = $response->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringNotContainsString('169.254.169.254', $pdf);
    }

    public function test_descarga_pdf_del_reporte(): void
    {
        $dep = $this->dependencia();
        $user = $this->admin($dep);
        $reporte = $this->reporte($dep, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/v1/reportes/'.$reporte->id.'/pdf')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
