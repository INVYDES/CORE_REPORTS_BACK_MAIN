<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveDependencia
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Usuario) {
            $request->attributes->set('dependencia_id', $user->dependencia_id);
            // Compartir en config para jobs/listeners si se necesita
            config(['app.dependencia_id' => $user->dependencia_id]);
        }

        return $next($request);
    }
}
