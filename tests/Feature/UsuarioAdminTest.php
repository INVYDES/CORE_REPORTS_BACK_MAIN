<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioAdminTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(): Dependencia
    {
        return Dependencia::create([
            'nombre' => 'Empresa Usuarios',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(30),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);
    }

    private function usuario(Dependencia $dependencia, array $extra = []): Usuario
    {
        static $secuencia = 0;
        $secuencia++;

        return Usuario::create(array_merge([
            'dependencia_id' => $dependencia->id,
            'rol' => 3,
            'nombre' => 'Usuario',
            'apellidos' => 'Prueba',
            'email' => "usuario{$secuencia}@empresa.com",
            'password' => 'password123',
            'estado' => true,
        ], $extra));
    }

    public function test_alta_rechaza_un_id_de_empleado_ya_registrado(): void
    {
        $dependencia = $this->dependencia();
        $admin = $this->usuario($dependencia, [
            'rol' => 1,
            'numero_empleado' => 'ADM-100',
        ]);
        $this->usuario($dependencia, ['numero_empleado' => 'EMP-500']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/usuarios', [
                'nombre' => 'Nuevo',
                'apellidos' => 'Técnico',
                'email' => 'nuevo@empresa.com',
                'password' => 'password123',
                'rol' => 3,
                'numero_empleado' => 'EMP-500',
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.numero_empleado.0',
                'Ese ID de empleado ya está en uso. Elige otro.'
            );

        $this->assertDatabaseMissing('usuarios', ['email' => 'nuevo@empresa.com']);
    }

    public function test_alta_rechaza_variantes_de_mayusculas_del_id_de_empleado(): void
    {
        $dependencia = $this->dependencia();
        $admin = $this->usuario($dependencia, ['rol' => 1]);
        $this->usuario($dependencia, ['numero_empleado' => 'EMP-500']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/usuarios', [
                'nombre' => 'Nuevo',
                'apellidos' => 'Técnico',
                'email' => 'nuevo@empresa.com',
                'password' => 'password123',
                'rol' => 3,
                'numero_empleado' => 'emp-500',
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.numero_empleado.0',
                'Ese ID de empleado ya está en uso. Elige otro.'
            );
    }

    public function test_edicion_rechaza_repetir_el_id_de_otro_usuario_y_permite_el_propio(): void
    {
        $dependencia = $this->dependencia();
        $admin = $this->usuario($dependencia, [
            'rol' => 1,
            'numero_empleado' => 'ADM-100',
        ]);
        $objetivo = $this->usuario($dependencia, ['numero_empleado' => 'EMP-500']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/usuarios/{$objetivo->id}", ['numero_empleado' => 'adm-100'])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.numero_empleado.0',
                'Ese ID de empleado ya está en uso. Elige otro.'
            );

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/usuarios/{$objetivo->id}", ['numero_empleado' => 'EMP-500'])
            ->assertOk();

        $this->assertSame('EMP-500', $objetivo->fresh()->numero_empleado);
    }
}
