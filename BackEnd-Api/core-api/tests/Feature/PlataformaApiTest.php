<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlataformaApiTest extends TestCase
{
    use RefreshDatabase;

    private function plataforma(): Dependencia
    {
        return Dependencia::create([
            'nombre' => 'Plataforma CsRecords',
            'tipo_licencia' => 'anual',
            'fecha_expiracion' => null,
            'limite_usuarios' => 99999,
            'limite_reportes_mensuales' => 999999,
            'activa' => true,
            'es_plataforma' => true,
        ]);
    }

    private function dependencia(array $extra = []): Dependencia
    {
        return Dependencia::create(array_merge([
            'nombre' => 'Cliente Demo',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(5),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'correo_contacto' => 'cliente@demo.com',
            'activa' => true,
        ], $extra));
    }

    private function usuario(Dependencia $dep, int $rol = 1, string $email = 'admin@demo.com'): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => $rol,
            'nombre' => 'Usuario',
            'apellidos' => 'Demo',
            'email' => $email,
            'password' => 'password123',
            'estado' => true,
        ]);
    }

    public function test_super_admin_puede_listar_y_editar_licencias(): void
    {
        $plataforma = $this->plataforma();
        $super = $this->usuario($plataforma, 4, 'super@core.com');
        $cliente = $this->dependencia();

        $this->actingAs($super, 'sanctum')
            ->getJson('/api/v1/plataforma/dependencias')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['nombre' => 'Cliente Demo']);

        $this->actingAs($super, 'sanctum')
            ->patchJson('/api/v1/plataforma/dependencias/'.$cliente->id.'/licencia', [
                'tipo_licencia' => 'anual',
                'fecha_expiracion' => now()->addDays(90)->toDateString(),
                'limite_usuarios' => 25,
                'limite_reportes_mensuales' => 500,
                'activa' => true,
            ])
            ->assertOk();

        $this->assertSame('anual', $cliente->fresh()->tipo_licencia);
        $this->assertSame(25, $cliente->fresh()->limite_usuarios);

        $this->assertDatabaseHas('suscripciones', [
            'dependencia_id' => $cliente->id,
            'plan' => 'anual',
            'limite_usuarios' => 25,
        ]);
    }

    public function test_usuario_normal_no_puede_entrar_al_panel(): void
    {
        $cliente = $this->dependencia();
        $admin = $this->usuario($cliente);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/plataforma/dependencias')
            ->assertStatus(403);
    }

    public function test_dependencia_vencida_puede_renovar_pero_no_operar(): void
    {
        $cliente = $this->dependencia([
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->subDay(),
            'trial_usado_at' => null,
        ]);
        $admin = $this->usuario($cliente);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/me')
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/tickets')
            ->assertStatus(403);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/licencias/1/comprar-mercadopago')
            ->assertOk()
            ->assertJsonPath('pasarela', 'none');

        $this->assertNull($cliente->fresh()->fecha_expiracion);
    }

    public function test_eliminar_cliente_desactiva_usuarios_y_revoca_tokens(): void
    {
        $plataforma = $this->plataforma();
        $super = $this->usuario($plataforma, 4, 'super@core.com');
        $cliente = $this->dependencia();
        $admin = $this->usuario($cliente);
        $admin->createToken('prueba');

        $this->actingAs($super, 'sanctum')
            ->deleteJson('/api/v1/plataforma/dependencias/'.$cliente->id)
            ->assertOk();

        $this->assertSoftDeleted('dependencias', ['id' => $cliente->id]);
        $this->assertDatabaseHas('usuarios', ['id' => $admin->id, 'estado' => false]);
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_login_de_super_admin_devuelve_rol_de_plataforma(): void
    {
        $plataforma = $this->plataforma();
        $this->usuario($plataforma, 4, 'super@core.com');

        $this->postJson('/api/v1/login', [
            'email' => 'super@core.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.rol', 4);
    }
}
