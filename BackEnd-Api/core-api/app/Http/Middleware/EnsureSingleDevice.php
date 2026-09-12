<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleDevice
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $request->bearerToken()) {
            $token = $user->currentAccessToken();
            // $token may be null if token not found (already revoked)
            if ($token && $token->name !== $user->current_device) {
                // Token belongs to a previous device, reject
                return response()->json([
                    'message' => 'Token invalidado por sesión en otro dispositivo.',
                ], 401);
            }
        }
        return $next($request);
    }
}
