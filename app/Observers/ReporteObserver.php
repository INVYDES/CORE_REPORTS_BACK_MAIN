<?php

namespace App\Observers;

use App\Models\Notificacion;
use App\Models\Reporte;
use Illuminate\Support\Facades\Auth;

class ReporteObserver
{
    /**
     * Sincroniza el estatus del ticket/servicio origen y encola la
     * notificación de reporte finalizado. El envío de correo lo hace el job
     * EnviarNotificacionesPendientes: aquí solo se persiste la notificación.
     */
    public function saved(Reporte $reporte): void
    {
        if ($reporte->ticket_id) {
            $ticket = $reporte->ticket;
            $nuevo = match ($reporte->estatus) {
                'finalizado' => 'resuelto',
                'parcial' => 'en_proceso',
                default => null,
            };

            if ($ticket !== null && $nuevo !== null && $ticket->estatus !== $nuevo) {
                // TicketObserver registra el historial y la notificación.
                $ticket->update([
                    'estatus' => $nuevo,
                    'fecha_atencion' => $ticket->fecha_atencion ?? now(),
                ]);
            }
        }

        if ($reporte->servicio_id) {
            $servicio = $reporte->servicio;
            $nuevo = match ($reporte->estatus) {
                'finalizado' => 'realizado',
                'parcial' => 'en_proceso',
                default => null,
            };

            if ($servicio !== null && $nuevo !== null && $servicio->estatus !== $nuevo) {
                // ServicioObserver registra el historial y la notificación.
                $servicio->update(['estatus' => $nuevo]);
            }
        }

        if (($reporte->wasRecentlyCreated || $reporte->wasChanged('estatus')) && $reporte->estatus === 'finalizado') {
            $this->notificarFinalizado($reporte);
        }
    }

    private function notificarFinalizado(Reporte $reporte): void
    {
        $dep = $reporte->dependencia;
        $dest = $dep?->correo_reportes ?: $dep?->correo_contacto;

        if (! $dest) {
            return;
        }

        $usuarioId = Auth::id() ?? $reporte->creado_por;

        Notificacion::create([
            'dependencia_id' => $reporte->dependencia_id,
            'usuario_id' => $usuarioId,
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
    }
}
