<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\TicketHistorial;
use App\Models\Notificacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificacionMail;

class TicketObserver
{
    public function updating(Ticket $ticket): void
    {
        if ($ticket->isDirty('estatus')) {
            TicketHistorial::create([
                'dependencia_id' => $ticket->dependencia_id,
                'ticket_id' => $ticket->id,
                'estatus_anterior' => $ticket->getOriginal('estatus'),
                'estatus_nuevo' => $ticket->estatus,
                'cambiado_por' => Auth::id() ?? $ticket->usuario_asignado_id ?? 1,
                'fecha_cambio' => now(),
            ]);
        }
    }

    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('estatus') && in_array($ticket->estatus, ['resuelto','cerrado'])) {
            $dep = $ticket->dependencia;
            $dest = $dep->correo_reportes ?? $dep->correo_contacto;
            $notif = Notificacion::create([
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
            if ($dest) {
                try { Mail::to($dest)->send(new NotificacionMail($notif)); $notif->update(['estatus'=>'enviado','enviado_at'=>now()]); } catch (\Throwable $e) { $notif->update(['estatus'=>'fallido']); }
            }
        }
    }
}
