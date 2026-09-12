<?php

namespace Tests\Feature;

use App\Models\BitacoraActividad;
use App\Models\Dependencia;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_ticket_registra_bitacora(): void
    {
        $dep = Dependencia::create([
            'nombre' => 'Dep Test',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(7),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);

        $user = Usuario::create([
            'dependencia_id' => $dep->id,
            'numero_empleado' => 'EMP-X',
            'rol' => 1,
            'nombre' => 'Admin',
            'apellidos' => 'Test',
            'email' => 'audit@test.com',
            'password' => 'password123',
            'estado' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $ticket = Ticket::create([
            'folio' => 'TKT-TEST-0001',
            'asunto' => 'Ticket de prueba',
            'prioridad' => 'alta',
            'estatus' => 'abierto',
        ]);

        $bitacora = BitacoraActividad::where('modelo_tipo', Ticket::class)
            ->where('modelo_id', $ticket->id)
            ->where('accion', 'creado')
            ->first();

        $this->assertNotNull($bitacora, 'La creación del ticket debe quedar en bitácora');
        $this->assertEquals($dep->id, $bitacora->dependencia_id);
        $this->assertEquals($user->id, $bitacora->usuario_id);
        $this->assertEquals('Ticket de prueba', $bitacora->valores_nuevos['asunto']);
    }

    public function test_scopes_de_usuario(): void
    {
        $dep = Dependencia::create([
            'nombre' => 'Dep Scopes',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(7),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);

        Usuario::create([
            'dependencia_id' => $dep->id, 'rol' => Usuario::ROLE_ADMIN_TEC,
            'nombre' => 'A', 'apellidos' => 'Tec', 'email' => 'at@scopes.com',
            'password' => 'password123', 'estado' => true,
        ]);
        Usuario::create([
            'dependencia_id' => $dep->id, 'rol' => Usuario::ROLE_TECNICO,
            'nombre' => 'B', 'apellidos' => 'Tec', 'email' => 'tt@scopes.com',
            'password' => 'password123', 'estado' => false,
        ]);

        $this->assertEquals(1, Usuario::activos()->count());
        $this->assertEquals(1, Usuario::byRole(Usuario::ROLE_TECNICO)->count());
        $this->assertEquals(1, Usuario::admins()->count());
    }
}
