<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Dependencia;
use App\Models\Notificacion;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionApiTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(): Dependencia
    {
        return Dependencia::create([
            'nombre' => 'Dep Notificaciones',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(15),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'correo_reportes' => 'reportes@notificaciones.com',
            'activa' => true,
        ]);
    }

    private function admin(Dependencia $dep): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => 1,
            'nombre' => 'Admin',
            'apellidos' => 'Notificaciones',
            'email' => 'admin@notificaciones.com',
            'password' => 'password123',
            'estado' => true,
        ]);
    }

    private function area(Dependencia $dep, string $codigo, string $nombre): Area
    {
        return Area::create([
            'dependencia_id' => $dep->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activa' => true,
        ]);
    }

    private function ticket(Dependencia $dep, Area $area, string $folio): Ticket
    {
        return Ticket::create([
            'dependencia_id' => $dep->id,
            'area_id' => $area->id,
            'folio' => $folio,
            'asunto' => 'Ticket de prueba',
            'estatus' => 'abierto',
        ]);
    }

    private function notificacion(Dependencia $dep, Usuario $usuario, Ticket $ticket): Notificacion
    {
        return Notificacion::create([
            'dependencia_id' => $dep->id,
            'usuario_id' => $usuario->id,
            'tipo' => 'ticket_resuelto',
            'titulo' => 'Ticket resuelto '.$ticket->folio,
            'referencia_tipo' => 'ticket',
            'referencia_id' => $ticket->id,
            'destinatario' => $usuario->email,
            'asunto' => 'Ticket resuelto',
            'cuerpo' => 'El ticket fue resuelto.',
            'estatus' => 'pendiente',
            'created_at' => now(),
        ]);
    }

    public function test_index_expone_area_y_referencia_real_y_filtra_por_area(): void
    {
        $dep = $this->dependencia();
        $admin = $this->admin($dep);
        $areaA = $this->area($dep, 'ARE-A', 'Área A');
        $areaB = $this->area($dep, 'ARE-B', 'Área B');
        $ticketA = $this->ticket($dep, $areaA, 'TCK-A');
        $ticketB = $this->ticket($dep, $areaB, 'TCK-B');
        $this->notificacion($dep, $admin, $ticketA);
        $this->notificacion($dep, $admin, $ticketB);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/notificaciones?area_id='.$areaA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.area.id', $areaA->id)
            ->assertJsonPath('data.0.area.nombre', 'Área A')
            ->assertJsonPath('data.0.referencia.folio', 'TCK-A')
            ->assertJsonPath('meta.no_leidas', 1);
    }

    public function test_index_cuenta_las_no_leidas_sobre_el_filtro_aplicado(): void
    {
        $dep = $this->dependencia();
        $admin = $this->admin($dep);
        $area = $this->area($dep, 'ARE-C', 'Área C');
        $ticket = $this->ticket($dep, $area, 'TCK-C');
        $primera = $this->notificacion($dep, $admin, $ticket);
        $segunda = $this->notificacion($dep, $admin, $ticket);
        $primera->update(['leida_at' => now()]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/notificaciones?area_id='.$area->id)
            ->assertOk()
            ->assertJsonPath('meta.no_leidas', 1)
            ->assertJsonPath('no_leidas', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/notificaciones?no_leidas=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $segunda->id);
    }
}
