<?php

namespace App\Policies;

use App\Models\Usuario;

class UsuarioPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, Usuario $target): bool
    {
        return $target->dependencia_id === $user->dependencia_id;
    }

    /** Alta de usuarios: solo administradores. */
    public function create(Usuario $user): bool
    {
        return $user->isAdminTec() || $user->isAdminCom();
    }

    public function update(Usuario $user, Usuario $target): bool
    {
        return $target->dependencia_id === $user->dependencia_id
            && ($user->isAdminTec() || $user->isAdminCom());
    }

    public function delete(Usuario $user, Usuario $target): bool
    {
        if ($target->dependencia_id !== $user->dependencia_id) {
            return false;
        }
        if (! $user->isAdminTec() && ! $user->isAdminCom()) {
            return false;
        }

        // Nadie elimina su propia cuenta desde el panel
        return $target->id !== $user->id;
    }

    /** Cambiar rol: solo administradores, y sin tocar al último admin de la dependencia. */
    public function changeRol(Usuario $user, Usuario $target): bool
    {
        return $this->update($user, $target);
    }
}
