<?php

namespace App\Observers;

use App\Models\Dependencia;
use App\Models\Notificacion;
use App\Models\Servicio;
use App\Models\ServicioHistorial;
use Illuminate\Support\Facades\Auth;

class ServicioObserver
{
    /** Historial de cambios de estatus (única fuente: no duplicar en controladores). */
    public function updating(Servicio $servicio): void
    {
        if (! $servicio->isDirty('estatus')) {
            return;
        }

        ServicioHistorial::create([
            'dependencia_id' => $servicio->dependencia_id,
            'servicio_id' => $servicio->id,
            'estatus_anterior' => $servicio->getOriginal('estatus'),
            'estatus_nuevo' => $servicio->estatus,
            'cambiado_por' => Auth::id() ?? $servicio->usuario_asignado_id ?? 1,
            'fecha_cambio' => now(),
        ]);
    }

    public function updated(Servicio $servicio): void
    {
        if (! $servicio->wasChanged('estatus') || ! in_array($servicio->estatus, ['vencido', 'realizado'], true)) {
            return;
        }

        $dep = $servicio->dependencia;

        if (! $dep instanceof Dependencia) {
            return;
        }

        $dest = $dep->correo_reportes ?: $dep->correo_contacto;

        if (! $dest) {
            return;
        }

        Notificacion::create([
            'dependencia_id' => $servicio->dependencia_id,
            'usuario_id' => $servicio->usuario_asignado_id,
            'tipo' => $servicio->estatus === 'vencido' ? 'servicio_vencido' : 'servicio_realizado',
            'titulo' => 'Servicio '.$servicio->folio.' '.$servicio->estatus,
            'referencia_tipo' => 'servicio',
            'referencia_id' => $servicio->id,
            'destinatario' => $dest,
            'asunto' => 'Servicio '.$servicio->folio.' '.$servicio->estatus,
            'cuerpo' => "El servicio {$servicio->folio} ({$servicio->asunto}) ha cambiado a {$servicio->estatus}. Vence: {$servicio->fecha_vencimiento}",
            'estatus' => 'pendiente',
            'created_at' => now(),
        ]);
    }
}
