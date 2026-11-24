<?php

namespace App\Jobs;

use App\Models\Dependencia;
use App\Services\CorreoAutomatizadoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnviarRecordatoriosLicencia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CorreoAutomatizadoService $correos): void
    {
        $diasAviso = array_map('intval', (array) config('correo.recordatorios_dias', [7, 3, 1]));
        $hoy = now()->startOfDay();

        Dependencia::query()
            ->where('activa', true)
            ->where('es_plataforma', false)
            ->whereNotNull('fecha_expiracion')
            ->whereDate('fecha_expiracion', '>=', $hoy->toDateString())
            ->whereDate('fecha_expiracion', '<=', $hoy->copy()->addDays(max($diasAviso ?: [0]))->toDateString())
            ->get()
            ->each(function (Dependencia $dependencia) use ($correos, $hoy, $diasAviso): void {
                $dias = (int) floor(
                    ($dependencia->fecha_expiracion->copy()->startOfDay()->timestamp - $hoy->timestamp) / 86400
                );

                if (in_array($dias, $diasAviso, true)) {
                    $correos->enviarLicenciaPorVencer($dependencia, $dias);
                }
            });
    }
}
