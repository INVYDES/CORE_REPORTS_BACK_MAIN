<?php

namespace App\Jobs;

use App\Models\Notificacion;
use App\Services\CorreoAutomatizadoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envía las notificaciones que quedaron en estatus "pendiente".
 *
 * Los observers y los controladores solo persisten la notificación; el envío
 * real de correo ocurre aquí para no bloquear la petición del usuario.
 */
class EnviarNotificacionesPendientes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Lote máximo por ejecución para no saturar SMTP. */
    private const LOTE = 50;

    public function handle(CorreoAutomatizadoService $correos): void
    {
        Notificacion::query()
            ->where('estatus', 'pendiente')
            ->whereNotNull('destinatario')
            ->where('destinatario', '!=', '')
            ->orderBy('id')
            ->limit(self::LOTE)
            ->get()
            ->each(function (Notificacion $notificacion) use ($correos): void {
                $log = $correos->enviarNotificacion($notificacion);

                if ($log?->estado === 'enviado') {
                    $notificacion->update([
                        'estatus' => 'enviado',
                        'enviado_at' => now(),
                    ]);
                } else {
                    // Se marca fallido para que el panel permita reenviarla;
                    // no se deja pendiente para no reintentar en cada corrida
                    // un correo que el servidor rechaza.
                    $notificacion->update(['estatus' => 'fallido']);
                }
            });
    }
}
