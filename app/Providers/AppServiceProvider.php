<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Políticas registradas manualmente (los nombres de modelo no coinciden
     * con la convención de descubrimiento automático en todos los casos).
     *
     * @var array<class-string, class-string>
     */
    private array $policies = [
        \App\Models\Ticket::class => \App\Policies\TicketPolicy::class,
        \App\Models\Reporte::class => \App\Policies\ReportePolicy::class,
        \App\Models\Area::class => \App\Policies\AreaPolicy::class,
        \App\Models\Equipo::class => \App\Policies\EquipoPolicy::class,
        \App\Models\Servicio::class => \App\Policies\ServicioPolicy::class,
        \App\Models\MaterialCatalogo::class => \App\Policies\MaterialPolicy::class,
        \App\Models\Usuario::class => \App\Policies\UsuarioPolicy::class,
        \App\Models\Encuesta::class => \App\Policies\EncuestaPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Observers: sincronización de estatus, historial y notificaciones.
        // Se registran explícitamente porque los nombres de modelo no siguen
        // la convención de descubrimiento automático de Laravel.
        \App\Models\Reporte::observe(\App\Observers\ReporteObserver::class);
        \App\Models\Ticket::observe(\App\Observers\TicketObserver::class);
        \App\Models\Servicio::observe(\App\Observers\ServicioObserver::class);

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        // Login: 5 intentos por minuto por IP+email (brute force)
        RateLimiter::for('auth', function (Request $request) {
            $key = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by('auth:'.$request->ip().':'.$key),
                Limit::perMinute(30)->by('auth-ip:'.$request->ip()),
            ];
        });

        // Verificación de correo: 10 por minuto por IP+correo
        RateLimiter::for('verificacion', function (Request $request) {
            $key = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(10)->by('ver:'.$request->ip().':'.$key),
                Limit::perMinute(40)->by('ver-ip:'.$request->ip()),
            ];
        });

        // Recuperación de contraseña: 5 por minuto por IP+correo
        RateLimiter::for('recuperacion', function (Request $request) {
            $key = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by('rec:'.$request->ip().':'.$key),
                Limit::perMinute(20)->by('rec-ip:'.$request->ip()),
            ];
        });

        // Registro público: 3 por minuto por IP
        RateLimiter::for('registro', fn (Request $request) => Limit::perMinute(3)->by('reg:'.$request->ip()));

        // Webhooks de pago: 30 por minuto (verificación clave: valor, no IP)
        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(30)->by('wh:'.$request->ip()));

        // Escrituras genéricas API: 120 por minuto por usuario/IP
        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
