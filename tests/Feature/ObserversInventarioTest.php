<?php

namespace Tests\Feature;

use App\Jobs\EnviarNotificacionesPendientes;
use App\Mail\NotificacionMail;
use App\Models\Dependencia;
use App\Models\MaterialCatalogo;
use App\Models\Notificacion;
use App\Models\Reporte;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ObserversInventarioTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(): Dependencia
    {
        return Dependencia::create([
            'nombre' => 'Dep Observers',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(10),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'correo_reportes' => 'reportes@observers.com',
            'activa' => true,
        ]);
    }

    private function admin(Dependencia $dep): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => 1,
            'nombre' => 'Admin',
            'apellidos' => 'Observers',
            'email' => 'admin@observers.com',
            'password' => 'password123',
            'estado' => true,
        ]);
    }

    private function material(Dependencia $dep, float $stock): MaterialCatalogo
    {
        return MaterialCatalogo::create([
            'dependencia_id' => $dep->id,
            'nombre' => 'Cable UTP',
            'unidad_base' => 'metro',
            'costo_unitario' => 12.5,
            'stock_actual' => $stock,
            'stock_minimo' => 1,
            'activo' => true,
        ]);
    }

    public function test_finalizar_reporte_actualiza_ticket_y_deja_notificacion_pendiente(): void
    {
        $dep = $this->dependencia();
        $admin = $this->admin($dep);
        $this->actingAs($admin, 'sanctum');

        $ticket = Ticket::create([
            'dependencia_id' => $dep->id,
            'folio' => 'TKT-20260101-0001',
            'asunto' => 'Servidor caído',
            'prioridad' => 'alta',
            'estatus' => 'abierto',
        ]);

        $reporte = Reporte::create([
            'dependencia_id' => $dep->id,
            'creado_por' => $admin->id,
            'ticket_id' => $ticket->id,
            'folio' => 'REP-202601-00001',
            'tipo' => 'ticket',
            'categoria' => 'correctivo',
            'fecha_inicio' => now()->subHour(),
            'fecha_fin' => now(),
            'estatus' => 'finalizado',
        ]);

        $this->assertSame('resuelto', $ticket->fresh()->estatus);

        $this->assertDatabaseHas('ticket_historial', [
            'ticket_id' => $ticket->id,
            'estatus_anterior' => 'abierto',
            'estatus_nuevo' => 'resuelto',
        ]);

        $this->assertDatabaseHas('notificaciones', [
            'referencia_tipo' => 'reporte',
            'referencia_id' => $reporte->id,
            'estatus' => 'pendiente',
        ]);

        // El job entrega las pendientes y las marca enviadas.
        Mail::fake();

        app()->call([new EnviarNotificacionesPendientes, 'handle']);

        Mail::assertSent(NotificacionMail::class);
        $this->assertSame(0, Notificacion::where('estatus', 'pendiente')->count());
    }

    public function test_eliminar_reporte_devuelve_los_materiales_al_stock(): void
    {
        $dep = $this->dependencia();
        $admin = $this->admin($dep);
        $material = $this->material($dep, 10);

        $creado = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/reportes', [
                'tipo' => 'libre',
                'fecha_inicio' => now()->subHour()->toDateTimeString(),
                'fecha_fin' => now()->toDateTimeString(),
                'materiales' => [
                    ['material_id' => $material->id, 'cantidad' => 4],
                ],
            ])
            ->assertStatus(201)
            ->json();

        $this->assertEquals(6, (float) $material->fresh()->stock_actual);

        $this->deleteJson('/api/v1/reportes/'.$creado['id'])->assertOk();

        $this->assertEquals(10, (float) $material->fresh()->stock_actual);

        $this->assertDatabaseHas('movimientos_inventario', [
            'material_id' => $material->id,
            'referencia_tipo' => 'reporte',
            'referencia_id' => $creado['id'],
            'tipo' => 'entrada',
        ]);
    }

    public function test_stock_insuficiente_responde_422(): void
    {
        $dep = $this->dependencia();
        $admin = $this->admin($dep);
        $material = $this->material($dep, 1);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/reportes', [
                'tipo' => 'libre',
                'fecha_inicio' => now()->subHour()->toDateTimeString(),
                'fecha_fin' => now()->toDateTimeString(),
                'materiales' => [
                    ['material_id' => $material->id, 'cantidad' => 5],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['materiales']);

        $this->assertEquals(1, (float) $material->fresh()->stock_actual);
        $this->assertSame(0, Reporte::count());
    }
}
