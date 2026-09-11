<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\Usuario;
use App\Enums\RolUsuario;

class TicketPolicy
{
    public function viewAny(Usuario $user): bool { return true; }
    public function view(Usuario $user, Ticket $ticket): bool {
        return $ticket->dependencia_id === $user->dependencia_id;
    }
    public function create(Usuario $user): bool {
        // Cliente puede crear, tecnico/admin también
        return true;
    }
    public function update(Usuario $user, Ticket $ticket): bool {
        if ($ticket->dependencia_id !== $user->dependencia_id) return false;
        // Cliente no edita tickets ajenos
        if ($user->rol === RolUsuario::Cliente->value) return false;
        return true;
    }
    public function assign(Usuario $user): bool {
        return in_array($user->rol, [RolUsuario::AdminTec->value, RolUsuario::AdminCom->value], true);
    }
}
