<?php

namespace App\Http\Middleware;

use App\Models\Dependencia;
use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicencia
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Usuario && $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user instanceof Usuario && $user->dependencia instanceof Dependencia) {
            $dep = $user->dependencia;

            if (! $dep->activa) {
                return response()->json(['message' => 'Dependencia desactivada. Licencia no activa.'], 403);
            }

            if ($dep->fecha_expiracion && now()->startOfDay()->gt($dep->fecha_expiracion->endOfDay())) {
                return response()->json(['message' => 'Licencia vencida. Contacte a soporte.'], 403);
            }
        }

        return $next($request);
    }
}
