<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\Usuario;

class EquipoPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, Equipo $equipo): bool
    {
        return $equipo->dependencia_id === $user->dependencia_id;
    }

    public function create(Usuario $user): bool
    {
        return $user->isAdminTec() || $user->isAdminCom();
    }

    public function update(Usuario $user, Equipo $equipo): bool
    {
        return $equipo->dependencia_id === $user->dependencia_id
            && ($user->isAdminTec() || $user->isAdminCom());
    }

    public function delete(Usuario $user, Equipo $equipo): bool
    {
        return $this->update($user, $equipo);
    }
}
