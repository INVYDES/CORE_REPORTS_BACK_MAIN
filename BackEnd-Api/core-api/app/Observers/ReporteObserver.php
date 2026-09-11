<?php

namespace App\Observers;

use App\Models\Reporte;
use App\Models\TicketHistorial;
use App\Models\ServicioHistorial;
use App\Models\Notificacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificacionMail;

class ReporteObserver
{
    public function saved(Reporte $reporte): void
    {
        // Sincroniza estatus padre según MD 7
        $userId = Auth::id() ?? $reporte->creado_por;

        if ($reporte->ticket_id && $reporte->ticket) {
            $ticket = $reporte->ticket;
            $nuevo = null;
            if ($reporte->estatus === 'finalizado') $nuevo = 'resuelto';
            elseif ($reporte->estatus === 'parcial') $nuevo = 'en_proceso';

            if ($nuevo && $ticket->estatus !== $nuevo) {
                $anterior = $ticket->estatus;
                $ticket->update(['estatus' => $nuevo]);
                TicketHistorial::create([
                    'dependencia_id' => $ticket->dependencia_id,
                    'ticket_id' => $ticket->id,
                    'estatus_anterior' => $anterior,
                    'estatus_nuevo' => $nuevo,
                    'cambiado_por' => $userId,
                    'fecha_cambio' => now(),
                ]);
                if ($nuevo === 'resuelto' || $nuevo === 'en_proceso') {
                    $ticket->update(['fecha_atencion' => $ticket->fecha_atencion ?? now()]);
                }
            }
        }

        if ($reporte->servicio_id && $reporte->servicio) {
            $servicio = $reporte->servicio;
            $nuevo = null;
            if ($reporte->estatus === 'finalizado') $nuevo = 'realizado';
            elseif ($reporte->estatus === 'parcial') $nuevo = 'en_proceso';

            if ($nuevo && $servicio->estatus !== $nuevo) {
                $anterior = $servicio->estatus;
                $servicio->update(['estatus' => $nuevo]);
                ServicioHistorial::create([
                    'dependencia_id' => $servicio->dependencia_id,
                    'servicio_id' => $servicio->id,
                    'estatus_anterior' => $anterior,
                    'estatus_nuevo' => $nuevo,
                    'cambiado_por' => $userId,
                    'fecha_cambio' => now(),
                ]);
            }
        }

        if ($reporte->wasRecentlyCreated || $reporte->wasChanged('estatus')) {
            if ($reporte->estatus === 'finalizado') {
                $dep = $reporte->dependencia;
                $dest = $dep->correo_reportes ?? $dep->correo_contacto;
                $notif = Notificacion::create([
                    'dependencia_id' => $reporte->dependencia_id,
                    'usuario_id' => $reporte->creado_por,
                    'tipo' => 'reporte_finalizado',
                    'titulo' => 'Reporte finalizado '.$reporte->folio,
                    'referencia_tipo' => 'reporte',
                    'referencia_id' => $reporte->id,
                    'destinatario' => $dest,
                    'asunto' => 'Reporte '.$reporte->folio.' finalizado',
                    'cuerpo' => "El reporte {$reporte->folio} ha sido finalizado por el técnico. Tipo: {$reporte->tipo}, Categoría: {$reporte->categoria}",
                    'estatus' => 'pendiente',
                    'created_at' => now(),
                ]);
                if ($dest) {
                    try { Mail::to($dest)->send(new NotificacionMail($notif)); $notif->update(['estatus'=>'enviado','enviado_at'=>now()]); } catch (\Throwable $e) { $notif->update(['estatus'=>'fallido']); }
                }
            }
        }
    }
}
