<?php

namespace App\Policies;

use App\Models\Servicio;
use App\Models\Usuario;

class ServicioPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, Servicio $servicio): bool
    {
        return $servicio->dependencia_id === $user->dependencia_id;
    }

    public function create(Usuario $user): bool
    {
        return $user->isAdminTec() || $user->isAdminCom();
    }

    public function update(Usuario $user, Servicio $servicio): bool
    {
        return $servicio->dependencia_id === $user->dependencia_id
            && ($user->isAdminTec() || $user->isAdminCom());
    }

    public function delete(Usuario $user, Servicio $servicio): bool
    {
        return $this->update($user, $servicio);
    }
}
