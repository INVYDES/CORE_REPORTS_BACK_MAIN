<?php

namespace App\Jobs;

use App\Models\Servicio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarcarServiciosVencidos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Servicio::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->whereIn('estatus', ['programado', 'en_proceso'])
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->each(function (Servicio $s) {
                $s->update(['estatus' => 'vencido']);
            });
    }
}
