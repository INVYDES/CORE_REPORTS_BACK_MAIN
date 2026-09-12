<?php

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Encuesta;
use App\Models\Usuario;

class EncuestaPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, Encuesta $encuesta): bool
    {
        return $encuesta->dependencia_id === $user->dependencia_id;
    }

    /** Responder encuestas: cualquier miembro de la dependencia excepto técnicos. */
    public function create(Usuario $user): bool
    {
        return $user->rol !== RolUsuario::Tecnico->value;
    }

    public function delete(Usuario $user, Encuesta $encuesta): bool
    {
        return $encuesta->dependencia_id === $user->dependencia_id
            && ($user->isAdminTec() || $user->isAdminCom());
    }
}
