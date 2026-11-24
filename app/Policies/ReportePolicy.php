<?php

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Reporte;
use App\Models\Usuario;

class ReportePolicy
{
    public function viewAny(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, Reporte $reporte): bool
    {
        return $reporte->dependencia_id === $user->dependencia_id;
    }

    public function create(Usuario $user): bool
    {
        return in_array($user->rol, [RolUsuario::AdminTec->value, RolUsuario::Tecnico->value], true);
    }

    public function update(Usuario $user, Reporte $reporte): bool
    {
        if ($reporte->dependencia_id !== $user->dependencia_id) {
            return false;
        }

        // Solo el usuario que creó el reporte puede editarlo
        return (int) $reporte->creado_por === (int) $user->id;
    }

    public function delete(Usuario $user, Reporte $reporte): bool
    {
        return $this->update($user, $reporte);
    }

    public function conformidad(Usuario $user, Reporte $reporte): bool
    {
        if ($reporte->dependencia_id !== $user->dependencia_id) {
            return false;
        }

        return in_array($user->rol, [RolUsuario::AdminCom->value, RolUsuario::AdminTec->value], true);
    }
}
