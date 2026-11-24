<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Usuario autenticado con el tipo concreto del proyecto.
     *
     * Laravel devuelve Authenticatable|Model, así que se comprueba el tipo
     * real en lugar de asumirlo. Un usuario de otro guard es 401.
     */
    protected function usuario(Request $request): Usuario
    {
        $user = $request->user();

        if (! $user && auth()->check()) {
            $user = auth()->user();
        }

        if ($user instanceof Usuario) {
            return $user;
        }

        if ($user && isset($user->id)) {
            $u = Usuario::find($user->id);
            if ($u) {
                return $u;
            }
        }

        abort(401, 'No autenticado.');
    }
}
