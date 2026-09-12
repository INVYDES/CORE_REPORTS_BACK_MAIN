<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveDependencia
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $request->attributes->set('dependencia_id', $request->user()->dependencia_id);
            // Compartir en config para jobs/listeners si se necesita
            config(['app.dependencia_id' => $request->user()->dependencia_id]);
        }

        return $next($request);
    }
}
