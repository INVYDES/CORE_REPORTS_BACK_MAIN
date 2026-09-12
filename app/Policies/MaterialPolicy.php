<?php

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\MaterialCatalogo;
use App\Models\Usuario;

class MaterialPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, MaterialCatalogo $material): bool
    {
        return $material->dependencia_id === $user->dependencia_id;
    }

    public function create(Usuario $user): bool
    {
        return $user->isAdminTec() || $user->isAdminCom();
    }

    public function update(Usuario $user, MaterialCatalogo $material): bool
    {
        return $material->dependencia_id === $user->dependencia_id
            && ($user->isAdminTec() || $user->isAdminCom());
    }

    public function delete(Usuario $user, MaterialCatalogo $material): bool
    {
        return $this->update($user, $material);
    }

    /** Registrar entrada de stock: administradores y técnicos. */
    public function registrarEntrada(Usuario $user, MaterialCatalogo $material): bool
    {
        return $material->dependencia_id === $user->dependencia_id
            && in_array($user->rol, [RolUsuario::AdminTec->value, RolUsuario::AdminCom->value, RolUsuario::Tecnico->value], true);
    }

    /** Consultar movimientos: cualquier miembro de la dependencia. */
    public function viewMovimientos(Usuario $user, MaterialCatalogo $material): bool
    {
        return $material->dependencia_id === $user->dependencia_id;
    }
}
