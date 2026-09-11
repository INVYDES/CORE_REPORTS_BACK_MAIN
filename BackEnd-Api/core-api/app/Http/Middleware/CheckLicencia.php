<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicencia
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->dependencia) {
            $dep = $user->dependencia;

            if (!$dep->activa) {
                return response()->json(['message' => 'Dependencia desactivada. Licencia no activa.'], 403);
            }

            if ($dep->fecha_expiracion && now()->toDateString() > $dep->fecha_expiracion) {
                return response()->json(['message' => 'Licencia vencida. Contacte a soporte.'], 403);
            }
        }

        return $next($request);
    }
}
