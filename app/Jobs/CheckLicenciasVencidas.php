<?php

namespace App\Jobs;

use App\Models\Dependencia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckLicenciasVencidas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Dependencia::where('activa', true)->whereNotNull('fecha_expiracion')->whereDate('fecha_expiracion', '<', now()->toDateString())->update(['activa' => false]);
    }
}
