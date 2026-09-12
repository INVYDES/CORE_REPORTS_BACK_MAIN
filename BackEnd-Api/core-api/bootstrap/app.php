<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'dependencia' => \App\Http\Middleware\ResolveDependencia::class,
                'licencia' => \App\Http\Middleware\CheckLicencia::class,
                'singleDevice' => \App\Http\Middleware\EnsureSingleDevice::class,

        ]);
        $middleware->throttleApi('60,1');
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Sesión expirada. Recarga la página e intenta de nuevo. No se requiere token CSRF para la API (usa Bearer Token).'], 419);
            }
        });
    })->create();
