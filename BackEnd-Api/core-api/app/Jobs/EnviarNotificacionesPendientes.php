<?php

namespace App\Jobs;

use App\Mail\NotificacionMail;
use App\Models\Notificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

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

    public function handle(): void
    {
        Notificacion::query()
            ->where('estatus', 'pendiente')
            ->whereNotNull('destinatario')
            ->where('destinatario', '!=', '')
            ->orderBy('id')
            ->limit(self::LOTE)
            ->get()
            ->each(function (Notificacion $notificacion): void {
                try {
                    Mail::to((string) $notificacion->destinatario)->send(new NotificacionMail($notificacion));

                    $notificacion->update([
                        'estatus' => 'enviado',
                        'enviado_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // Se marca fallido para que el panel permita reenviarla;
                    // no se deja pendiente para no reintentar en cada corrida
                    // un correo que el servidor rechaza.
                    report($e);

                    $notificacion->update(['estatus' => 'fallido']);
                }
            });
    }
}
