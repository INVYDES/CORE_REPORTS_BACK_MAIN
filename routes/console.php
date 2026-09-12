<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new \App\Jobs\MarcarServiciosVencidos)->dailyAt('00:30');
Schedule::job(new \App\Jobs\CheckLicenciasVencidas)->dailyAt('01:00');
