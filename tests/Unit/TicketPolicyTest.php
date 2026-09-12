<?php

namespace Tests\Unit;

use App\Enums\RolUsuario;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Policies\TicketPolicy;
use PHPUnit\Framework\TestCase;

class TicketPolicyTest extends TestCase
{
    public function test_view_any_always_true(): void
    {
        $policy = new TicketPolicy;
        $user = $this->makeUser(1, 1);
        $this->assertTrue($policy->viewAny($user));
    }

    public function test_view_same_dependencia(): void
    {
        $policy = new TicketPolicy;
        $user = $this->makeUser(1, 1);
        $ticket = $this->makeTicket(1, 99);
        $this->assertFalse($policy->view($user, $ticket));
        $ticket2 = $this->makeTicket(1, 1);
        $this->assertTrue($policy->view($user, $ticket2));
    }

    public function test_assign_only_admins(): void
    {
        $policy = new TicketPolicy;
        $this->assertTrue($policy->assign($this->makeUser(RolUsuario::AdminTec->value, 1)));
        $this->assertTrue($policy->assign($this->makeUser(RolUsuario::AdminCom->value, 1)));
        $this->assertFalse($policy->assign($this->makeUser(RolUsuario::Tecnico->value, 1)));
        $this->assertFalse($policy->assign($this->makeUser(RolUsuario::Cliente->value, 1)));
    }

    public function test_update_cliente_cannot(): void
    {
        $policy = new TicketPolicy;
        $cliente = $this->makeUser(RolUsuario::Cliente->value, 1);
        $ticket = $this->makeTicket(1, 1);
        $this->assertFalse($policy->update($cliente, $ticket));
    }

    private function makeUser(int $rol, int $dep): Usuario
    {
        $u = new Usuario;
        $u->rol = $rol;
        $u->dependencia_id = $dep;

        return $u;
    }

    private function makeTicket(int $id, int $dep): Ticket
    {
        $t = new Ticket;
        $t->id = $id;
        $t->dependencia_id = $dep;

        return $t;
    }
}
