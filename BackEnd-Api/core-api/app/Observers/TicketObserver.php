<?php

namespace App\Observers;

use App\Models\Dependencia;
use App\Models\Notificacion;
use App\Models\Ticket;
use App\Models\TicketHistorial;
use Illuminate\Support\Facades\Auth;

class TicketObserver
{
    /** Historial de cambios de estatus (única fuente: no duplicar en controladores). */
    public function updating(Ticket $ticket): void
    {
        if (! $ticket->isDirty('estatus')) {
            return;
        }

        TicketHistorial::create([
            'dependencia_id' => $ticket->dependencia_id,
            'ticket_id' => $ticket->id,
            'estatus_anterior' => $ticket->getOriginal('estatus'),
            'estatus_nuevo' => $ticket->estatus,
            'cambiado_por' => Auth::id() ?? $ticket->usuario_asignado_id ?? 1,
            'fecha_cambio' => now(),
        ]);
    }

    public function updated(Ticket $ticket): void
    {
        if (! $ticket->wasChanged('estatus') || ! in_array($ticket->estatus, ['resuelto', 'cerrado'], true)) {
            return;
        }

        $dep = $ticket->dependencia;

        if (! $dep instanceof Dependencia) {
            return;
        }

        $dest = $dep->correo_reportes ?: $dep->correo_contacto;

        if (! $dest) {
            return;
        }

        Notificacion::create([
            'dependencia_id' => $ticket->dependencia_id,
            'usuario_id' => $ticket->usuario_asignado_id,
            'tipo' => 'ticket_'.$ticket->estatus,
            'titulo' => 'Ticket '.$ticket->folio.' '.$ticket->estatus,
            'referencia_tipo' => 'ticket',
            'referencia_id' => $ticket->id,
            'destinatario' => $dest,
            'asunto' => 'Ticket '.$ticket->folio.' '.$ticket->estatus,
            'cuerpo' => "El ticket {$ticket->folio} ({$ticket->asunto}) ha cambiado a {$ticket->estatus}.",
            'estatus' => 'pendiente',
            'created_at' => now(),
        ]);
    }
}
