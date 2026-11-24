<?php

namespace Tests\Unit;

use App\Enums\RolUsuario;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Policies\ReportePolicy;
use PHPUnit\Framework\TestCase;

class ReportePolicyTest extends TestCase
{
    public function test_create_only_tecnico_like(): void
    {
        $p = new ReportePolicy;
        $this->assertTrue($p->create($this->makeUser(RolUsuario::AdminTec->value)));
        $this->assertTrue($p->create($this->makeUser(RolUsuario::Tecnico->value)));
        $this->assertFalse($p->create($this->makeUser(RolUsuario::AdminCom->value)));
        $this->assertFalse($p->create($this->makeUser(RolUsuario::Cliente->value)));
    }

    public function test_view_same_dependencia(): void
    {
        $p = new ReportePolicy;
        $user = $this->makeUser(RolUsuario::Tecnico->value, 1);
        $rep = $this->makeReporte(1);
        $this->assertFalse($p->view($user, $rep));
        $rep2 = $this->makeReporte(1, 1);
        $this->assertTrue($p->view($user, $rep2));
    }

    private function makeUser(int $rol, int $dep = 1): Usuario
    {
        $u = new Usuario;
        $u->rol = $rol;
        $u->dependencia_id = $dep;
        $u->id = 99;

        return $u;
    }

    private function makeReporte(int $id, int $dep = 99): Reporte
    {
        $r = new Reporte;
        $r->id = $id;
        $r->dependencia_id = $dep;
        $r->creado_por = 99;

        return $r;
    }
}
