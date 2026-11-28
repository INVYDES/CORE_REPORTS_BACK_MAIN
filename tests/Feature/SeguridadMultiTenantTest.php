<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Notificacion;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresiones de aislamiento entre empresas y abuso de endpoints.
 */
class SeguridadMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(string $nombre, array $extra = []): Dependencia
    {
        return Dependencia::create(array_merge([
            'nombre' => $nombre,
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(30),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ], $extra));
    }

    private function usuario(Dependencia $dep, int $rol, array $extra = []): Usuario
    {
        static $n = 0;
        $n++;

        return Usuario::create(array_merge([
            'dependencia_id' => $dep->id,
            'rol' => $rol,
            'nombre' => 'Usuario',
            'apellidos' => 'Prueba',
            'email' => "seg{$n}@empresa.com",
            'password' => 'password123',
            'estado' => true,
        ], $extra));
    }

    public function test_admin_no_puede_modificar_usuarios_de_otra_empresa(): void
    {
        $adminA = $this->usuario($this->dependencia('Empresa A'), 1);
        $victima = $this->usuario($this->dependencia('Empresa B'), 2);

        $this->actingAs($adminA, 'sanctum')
            ->putJson("/api/v1/usuarios/{$victima->id}", ['password' => 'tomada123', 'email' => 'atacante@x.com'])
            ->assertStatus(403);

        $victima->refresh();
        $this->assertTrue(Hash::check('password123', $victima->password));
        $this->assertNotSame('atacante@x.com', $victima->email);
    }

    public function test_admin_no_puede_eliminar_usuarios_de_otra_empresa(): void
    {
        $adminA = $this->usuario($this->dependencia('Empresa A'), 1);
        $victima = $this->usuario($this->dependencia('Empresa B'), 2);

        $this->actingAs($adminA, 'sanctum')
            ->deleteJson("/api/v1/usuarios/{$victima->id}")
            ->assertStatus(403);

        $this->assertNotSoftDeleted($victima);
    }

    public function test_admin_si_puede_modificar_usuarios_de_su_empresa(): void
    {
        $dep = $this->dependencia('Empresa A');
        $admin = $this->usuario($dep, 1);
        $tecnico = $this->usuario($dep, 3);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/usuarios/{$tecnico->id}", ['nombre' => 'Renombrado'])
            ->assertOk();

        $this->assertSame('Renombrado', $tecnico->fresh()->nombre);
    }

    public function test_listar_usuarios_no_marca_correos_como_verificados(): void
    {
        $dep = $this->dependencia('Empresa A');
        $admin = $this->usuario($dep, 1);
        $pendiente = $this->usuario($dep, 3);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/usuarios')->assertOk();

        $this->assertNull($pendiente->fresh()->email_verified_at);
    }

    public function test_id_de_empleado_no_puede_tener_forma_de_correo(): void
    {
        $dep = $this->dependencia('Empresa A');
        $admin = $this->usuario($dep, 1);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/usuarios', [
                'nombre' => 'Nuevo',
                'apellidos' => 'Usuario',
                'email' => 'nuevo@empresa.com',
                'password' => 'password123',
                'rol' => 3,
                'numero_empleado' => 'victima1@empresa.com',
            ])
            ->assertStatus(422);
    }

    public function test_notificacion_no_envia_a_correos_externos(): void
    {
        $dep = $this->dependencia('Empresa A');
        $tecnico = $this->usuario($dep, 3);
        $ticket = Ticket::create([
            'dependencia_id' => $dep->id,
            'folio' => 'TKT-000001-0001',
            'asunto' => 'Ticket',
            'estatus' => 'abierto',
        ]);

        $this->actingAs($tecnico, 'sanctum')
            ->postJson('/api/v1/notificaciones', [
                'tipo' => 'aviso',
                'referencia_tipo' => 'ticket',
                'referencia_id' => $ticket->id,
                'destinatario' => 'cualquiera@externo.com',
                'asunto' => 'Spam',
            ])
            ->assertStatus(422);

        $this->assertSame(0, Notificacion::withoutGlobalScopes()->count());
    }

    public function test_solo_administradores_cambian_el_plan(): void
    {
        $dep = $this->dependencia('Empresa A', ['tipo_licencia' => 'mensual']);
        $tecnico = $this->usuario($dep, 3);

        $this->actingAs($tecnico, 'sanctum')
            ->postJson('/api/v1/licencias/1/comprar-mercadopago')
            ->assertStatus(403);

        $this->assertNotNull($dep->fresh()->fecha_expiracion);
    }

    public function test_admin_no_baja_a_gratis_con_plan_de_pago_vigente(): void
    {
        $dep = $this->dependencia('Empresa A', ['tipo_licencia' => 'mensual']);
        $admin = $this->usuario($dep, 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/licencias/1/comprar-mercadopago')
            ->assertStatus(422);

        $this->assertSame('mensual', $dep->fresh()->tipo_licencia);
        $this->assertNotNull($dep->fresh()->fecha_expiracion);
    }

    public function test_durante_la_prueba_de_un_plan_de_pago_si_puede_pasar_a_gratis(): void
    {
        $dep = $this->dependencia('Empresa A', ['tipo_licencia' => 'mensual']);
        \App\Models\Suscripcion::create([
            'dependencia_id' => $dep->id,
            'proveedor' => 'registro',
            'plan' => 'mensual',
            'estado' => 'trial',
            'fecha_inicio' => now(),
            'fecha_expiracion' => now()->addDays(30),
        ]);
        $admin = $this->usuario($dep, 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/licencias/1/comprar-mercadopago')
            ->assertOk();

        $this->assertSame('trial', $dep->fresh()->tipo_licencia);
    }

    public function test_editar_usuario_con_id_antiguo_fuera_de_formato_no_se_bloquea(): void
    {
        $dep = $this->dependencia('Empresa A');
        $admin = $this->usuario($dep, 1);
        $legado = $this->usuario($dep, 3, ['numero_empleado' => 'AB']);

        // El formulario reenvía el mismo ID: debe permitir guardar otros cambios.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/usuarios/{$legado->id}", ['nombre' => 'Nuevo', 'numero_empleado' => 'AB'])
            ->assertOk();

        // Pero un ID nuevo sí debe cumplir el formato.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/usuarios/{$legado->id}", ['numero_empleado' => 'x@y.com'])
            ->assertStatus(422);
    }

    public function test_portal_publico_no_acepta_id_numerico(): void
    {
        $dep = $this->dependencia('Taller Norte');

        $this->getJson("/api/v1/public/empresa/{$dep->id}")->assertNotFound();
        $this->getJson('/api/v1/public/empresa/taller-norte')->assertOk();
    }

    public function test_portal_publico_rechaza_empresas_inactivas(): void
    {
        $this->dependencia('Taller Cerrado', ['activa' => false]);

        $this->getJson('/api/v1/public/empresa/taller-cerrado')->assertNotFound();
    }

    public function test_ticket_publico_notifica_a_administradores_y_no_a_clientes(): void
    {
        $dep = $this->dependencia('Taller Sur');
        $admin = $this->usuario($dep, 1);
        $cliente = $this->usuario($dep, 0);

        $this->postJson('/api/v1/public/empresa/taller-sur/tickets', [
            'lugar' => 'Sucursal 1',
            'solicitante' => 'Cliente Externo',
            'telefono' => '5550000000',
            'asunto' => 'No enciende',
            'descripcion' => 'El equipo no enciende.',
        ])->assertCreated();

        $destinatarios = Notificacion::withoutGlobalScopes()->pluck('usuario_id')->all();
        $this->assertContains($admin->id, $destinatarios);
        $this->assertNotContains($cliente->id, $destinatarios);
    }
}
