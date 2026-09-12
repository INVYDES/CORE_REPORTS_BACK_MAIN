<?php

namespace App\Observers;

use App\Mail\NotificacionMail;
use App\Models\Notificacion;
use App\Models\Servicio;
use App\Models\ServicioHistorial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ServicioObserver
{
    public function updating(Servicio $servicio): void
    {
        if ($servicio->isDirty('estatus')) {
            ServicioHistorial::create([
                'dependencia_id' => $servicio->dependencia_id,
                'servicio_id' => $servicio->id,
                'estatus_anterior' => $servicio->getOriginal('estatus'),
                'estatus_nuevo' => $servicio->estatus,
                'cambiado_por' => Auth::id() ?? $servicio->usuario_asignado_id ?? 1,
                'fecha_cambio' => now(),
            ]);
        }
    }

    public function updated(Servicio $servicio): void
    {
        if ($servicio->wasChanged('estatus') && in_array($servicio->estatus, ['vencido', 'realizado'])) {
            $dep = $servicio->dependencia;
            $dest = $dep->correo_reportes ?? $dep->correo_contacto;
            $tipo = $servicio->estatus === 'vencido' ? 'servicio_vencido' : 'servicio_realizado';
            $notif = Notificacion::create([
                'dependencia_id' => $servicio->dependencia_id,
                'usuario_id' => $servicio->usuario_asignado_id,
                'tipo' => $tipo,
                'titulo' => 'Servicio '.$servicio->folio.' '.$servicio->estatus,
                'referencia_tipo' => 'servicio',
                'referencia_id' => $servicio->id,
                'destinatario' => $dest,
                'asunto' => 'Servicio '.$servicio->folio.' '.$servicio->estatus,
                'cuerpo' => "El servicio {$servicio->folio} ({$servicio->asunto}) ha cambiado a {$servicio->estatus}. Vence: {$servicio->fecha_vencimiento}",
                'estatus' => 'pendiente',
                'created_at' => now(),
            ]);
            if ($dest) {
                try {
                    Mail::to($dest)->send(new NotificacionMail($notif));
                    $notif->update(['estatus' => 'enviado', 'enviado_at' => now()]);
                } catch (\Throwable $e) {
                    $notif->update(['estatus' => 'fallido']);
                }
            }
        }
    }
}
