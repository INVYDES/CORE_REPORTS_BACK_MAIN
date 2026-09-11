<?php

namespace App\Providers;

use App\Models\Reporte;
use App\Models\Ticket;
use App\Models\Servicio;
use App\Observers\ReporteObserver;
use App\Observers\TicketObserver;
use App\Observers\ServicioObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && config('telescope.enabled', env('TELESCOPE_ENABLED', true))) {
            // Telescope service provider is auto-registered when installed
        }
        if (env('SENTRY_LARAVEL_DSN')) {
            // Sentry service provider is auto-registered when installed
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Ticket::observe(TicketObserver::class);
        Servicio::observe(ServicioObserver::class);
        Reporte::observe(ReporteObserver::class);
    }
}
