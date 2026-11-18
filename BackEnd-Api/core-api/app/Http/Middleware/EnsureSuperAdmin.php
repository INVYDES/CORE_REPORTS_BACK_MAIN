<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Usuario || ! $user->isSuperAdmin()) {
            return response()->json(['message' => 'No tienes permisos de plataforma.'], 403);
        }

        return $next($request);
    }
}
