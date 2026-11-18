<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Dependencia;
use App\Models\Servicio;
use App\Models\ServicioHistorial;
use App\Models\Ticket;
use App\Models\TicketHistorial;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketsServiciosTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(string $nombre = 'Dep Operaciones'): Dependencia
    {
        return Dependencia::create([
            'nombre' => $nombre,
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(15),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'correo_reportes' => 'reportes@operaciones.com',
            'activa' => true,
        ]);
    }

    private function usuario(Dependencia $dep, int $rol, string $email): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => $rol,
            'nombre' => 'Usuario',
            'apellidos' => 'Prueba',
            'email' => $email,
            'password' => 'password123',
            'estado' => true,
        ]);
    }

    private function crearTicket(Usuario $admin, array $extra = []): array
    {
        return $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/tickets', array_merge([
                'asunto' => 'Servidor caído',
                'descripcion' => 'No responde ping',
            ], $extra))
            ->assertStatus(201)
            ->json();
    }

    private function crearServicio(Usuario $admin, array $extra = []): array
    {
        return $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/servicios', array_merge([
                'asunto' => 'Mantenimiento preventivo',
            ], $extra))
            ->assertStatus(201)
            ->json();
    }

    // ------------------------------------------------------------------
    // Tickets
    // ------------------------------------------------------------------

    public function test_crear_ticket_genera_folio_y_valores_por_defecto(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');

        $primero = $this->crearTicket($admin);
        $segundo = $this->crearTicket($admin, ['asunto' => 'Impresora sin tóner']);

        $this->assertStringStartsWith('TKT-', $primero['folio']);
        $this->assertStringEndsWith('-0001', $primero['folio']);
        $this->assertStringEndsWith('-0002', $segundo['folio']);

        $this->assertSame('abierto', $primero['estatus']);
        $this->assertSame('media', $primero['prioridad']);
        $this->assertSame($dep->id, $primero['dependencia_id']);
    }

    public function test_crear_ticket_calcula_sla_y_fecha_limite_por_prioridad(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');

        $esperado = ['alta' => 4, 'media' => 24, 'baja' => 72];

        foreach ($esperado as $prioridad => $horas) {
            $ticket = $this->crearTicket($admin, [
                'asunto' => 'Ticket '.$prioridad,
                'prioridad' => $prioridad,
            ]);

            $this->assertSame($horas, $ticket['sla_horas'], "SLA incorrecto para prioridad {$prioridad}");
            $this->assertTrue(
                Carbon::parse($ticket['fecha_limite'])->equalTo(
                    Carbon::parse($ticket['fecha_solicitud'])->addHours($horas)
                ),
                "fecha_limite incorrecta para prioridad {$prioridad}"
            );
        }
    }

    public function test_sla_capturado_se_respeta_y_recalcula_al_cambiar_prioridad(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');

        // Un SLA explícito no se sobrescribe con el de la prioridad.
        $custom = $this->crearTicket($admin, ['prioridad' => 'alta', 'sla_horas' => 10]);
        $this->assertSame(10, $custom['sla_horas']);
        $this->assertTrue(
            Carbon::parse($custom['fecha_limite'])->equalTo(
                Carbon::parse($custom['fecha_solicitud'])->addHours(10)
            )
        );

        // Al cambiar prioridad sin capturar SLA se recalcula el compromiso.
        $ticket = $this->crearTicket($admin, ['asunto' => 'Cambio de prioridad', 'prioridad' => 'baja']);
        $this->assertSame(72, $ticket['sla_horas']);

        $actualizado = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/tickets/'.$ticket['id'], ['prioridad' => 'alta'])
            ->assertOk()
            ->json();

        $this->assertSame(4, $actualizado['sla_horas']);
        $this->assertTrue(
            Carbon::parse($actualizado['fecha_limite'])->equalTo(
                Carbon::parse($actualizado['fecha_solicitud'])->addHours(4)
            )
        );
    }

    public function test_folios_se_generan_dentro_de_una_transaccion(): void
    {
        $dep = $this->dependencia();

        $primero = Ticket::createConFolio([
            'dependencia_id' => $dep->id,
            'asunto' => 'Concurrente 1',
            'prioridad' => 'media',
            'estatus' => 'abierto',
            'fecha_solicitud' => now(),
        ], $dep->id);

        $segundo = Ticket::createConFolio([
            'dependencia_id' => $dep->id,
            'asunto' => 'Concurrente 2',
            'prioridad' => 'media',
            'estatus' => 'abierto',
            'fecha_solicitud' => now(),
        ], $dep->id);

        $this->assertStringEndsWith('-0001', $primero->folio);
        $this->assertStringEndsWith('-0002', $segundo->folio);
        $this->assertNotSame($primero->folio, $segundo->folio);
    }

    public function test_asignar_ticket_cambia_estatus_registra_historial_y_encola_notificacion(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');
        $tecnico = $this->usuario($dep, 3, 'tecnico@operaciones.com');

        $ticket = $this->crearTicket($admin);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/tickets/'.$ticket['id'].'/asignar', [
                'usuario_asignado_id' => $tecnico->id,
            ])
            ->assertOk()
            ->assertJsonPath('estatus', 'en_proceso')
            ->assertJsonPath('usuario_asignado_id', $tecnico->id);

        $this->assertSame('en_proceso', Ticket::find($ticket['id'])->estatus);

        $historial = TicketHistorial::where('ticket_id', $ticket['id'])->get();
        $this->assertCount(1, $historial, 'La asignación debe generar un solo registro de historial');
        $this->assertSame('abierto', $historial->first()->estatus_anterior);
        $this->assertSame('en_proceso', $historial->first()->estatus_nuevo);
        $this->assertSame($admin->id, $historial->first()->cambiado_por);

        $this->assertDatabaseHas('notificaciones', [
            'referencia_tipo' => 'ticket',
            'referencia_id' => $ticket['id'],
            'tipo' => 'ticket_asignado',
            'usuario_id' => $tecnico->id,
            'estatus' => 'pendiente',
        ]);
    }

    public function test_tecnico_no_puede_asignar_tickets(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');
        $tecnico = $this->usuario($dep, 3, 'tecnico@operaciones.com');

        $ticket = $this->crearTicket($admin);

        $this->actingAs($tecnico, 'sanctum')
            ->patchJson('/api/v1/tickets/'.$ticket['id'].'/asignar', [
                'usuario_asignado_id' => $tecnico->id,
            ])
            ->assertStatus(403);

        $this->assertNull(Ticket::find($ticket['id'])->usuario_asignado_id);
    }

    public function test_no_se_puede_asignar_un_usuario_de_otra_dependencia(): void
    {
        $propia = $this->dependencia('Propia');
        $ajena = $this->dependencia('Ajena');

        $admin = $this->usuario($propia, 1, 'admin@propia.com');
        $ajeno = $this->usuario($ajena, 3, 'tecnico@ajena.com');

        $ticket = $this->crearTicket($admin);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/tickets/'.$ticket['id'].'/asignar', [
                'usuario_asignado_id' => $ajeno->id,
            ])
            ->assertStatus(422);

        $this->assertNull(Ticket::find($ticket['id'])->usuario_asignado_id);
    }

    public function test_actualizar_estatus_de_ticket_registra_un_solo_historial_y_notifica(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');

        $ticket = $this->crearTicket($admin);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/tickets/'.$ticket['id'], ['estatus' => 'resuelto'])
            ->assertOk()
            ->assertJsonPath('estatus', 'resuelto');

        $historial = TicketHistorial::where('ticket_id', $ticket['id'])->get();
        $this->assertCount(1, $historial);
        $this->assertSame('abierto', $historial->first()->estatus_anterior);
        $this->assertSame('resuelto', $historial->first()->estatus_nuevo);

        $this->assertDatabaseHas('notificaciones', [
            'referencia_tipo' => 'ticket',
            'referencia_id' => $ticket['id'],
            'tipo' => 'ticket_resuelto',
            'estatus' => 'pendiente',
        ]);
    }

    // ------------------------------------------------------------------
    // Servicios
    // ------------------------------------------------------------------

    public function test_crear_servicio_genera_folio_y_estatus_programado(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');

        $primero = $this->crearServicio($admin);
        $segundo = $this->crearServicio($admin, ['asunto' => 'Revisión de UPS']);

        $this->assertStringStartsWith('SRV-', $primero['folio']);
        $this->assertStringEndsWith('-0001', $primero['folio']);
        $this->assertStringEndsWith('-0002', $segundo['folio']);
        $this->assertSame('programado', $primero['estatus']);
        $this->assertSame($dep->id, $primero['dependencia_id']);
    }

    public function test_actualizar_servicio_a_realizado_registra_historial_y_notifica(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');

        $servicio = $this->crearServicio($admin);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/servicios/'.$servicio['id'], ['estatus' => 'realizado'])
            ->assertOk()
            ->assertJsonPath('estatus', 'realizado');

        $historial = ServicioHistorial::where('servicio_id', $servicio['id'])->get();
        $this->assertCount(1, $historial);
        $this->assertSame('programado', $historial->first()->estatus_anterior);
        $this->assertSame('realizado', $historial->first()->estatus_nuevo);

        $this->assertDatabaseHas('notificaciones', [
            'referencia_tipo' => 'servicio',
            'referencia_id' => $servicio['id'],
            'tipo' => 'servicio_realizado',
            'estatus' => 'pendiente',
        ]);
    }

    public function test_finalizar_reporte_de_servicio_sincroniza_el_servicio(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');

        $servicio = $this->crearServicio($admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/reportes', [
                'tipo' => 'servicio',
                'servicio_id' => $servicio['id'],
                'categoria' => 'preventivo',
                'fecha_inicio' => now()->subHours(2)->toDateTimeString(),
                'fecha_fin' => now()->toDateTimeString(),
                'desarrollo' => 'Mantenimiento concluido',
                'estatus' => 'finalizado',
            ])
            ->assertStatus(201);

        $this->assertSame('realizado', Servicio::find($servicio['id'])->estatus);

        $historial = ServicioHistorial::where('servicio_id', $servicio['id'])->get();
        $this->assertCount(1, $historial);
        $this->assertSame('programado', $historial->first()->estatus_anterior);
        $this->assertSame('realizado', $historial->first()->estatus_nuevo);

        $this->assertDatabaseHas('notificaciones', [
            'referencia_tipo' => 'servicio',
            'referencia_id' => $servicio['id'],
            'tipo' => 'servicio_realizado',
            'estatus' => 'pendiente',
        ]);
    }

    public function test_listar_servicios_filtra_por_area(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep, 1, 'admin@operaciones.com');

        $areaUno = Area::create([
            'dependencia_id' => $dep->id,
            'codigo' => 'A1',
            'nombre' => 'Área uno',
            'activa' => true,
        ]);
        $areaDos = Area::create([
            'dependencia_id' => $dep->id,
            'codigo' => 'A2',
            'nombre' => 'Área dos',
            'activa' => true,
        ]);

        $servicioUno = $this->crearServicio($admin, ['area_id' => $areaUno->id]);
        $this->crearServicio($admin, ['asunto' => 'Otro servicio', 'area_id' => $areaDos->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/servicios?area_id='.$areaUno->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $servicioUno['id']);
    }
}
