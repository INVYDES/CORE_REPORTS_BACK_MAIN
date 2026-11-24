<?php

namespace App\Jobs;

use App\Models\Dependencia;
use App\Services\CorreoAutomatizadoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckLicenciasVencidas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CorreoAutomatizadoService $correos): void
    {
        Dependencia::query()
            ->where('activa', true)
            ->where('es_plataforma', false)
            ->whereNotNull('fecha_expiracion')
            ->whereDate('fecha_expiracion', '<', now()->toDateString())
            ->get()
            ->each(function (Dependencia $dependencia) use ($correos): void {
                $correos->enviarLicenciaVencida($dependencia);
                $dependencia->update(['activa' => false]);
            });
    }
}
