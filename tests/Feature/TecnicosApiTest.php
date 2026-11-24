<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TecnicosApiTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(string $nombre): Dependencia
    {
        return Dependencia::create([
            'nombre' => $nombre,
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(30),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);
    }

    private function usuario(Dependencia $dep, array $extra = []): Usuario
    {
        static $seq = 0;
        $seq++;

        return Usuario::create(array_merge([
            'dependencia_id' => $dep->id,
            'rol' => 3,
            'nombre' => 'Usuario',
            'apellidos' => 'Test',
            'email' => "user{$seq}@test.com",
            'password' => 'password123',
            'estado' => true,
        ], $extra));
    }

    public function test_lista_usuarios_activos_de_la_dependencia_con_alias_para_el_front(): void
    {
        $propia = $this->dependencia('Propia');
        $ajena = $this->dependencia('Ajena');

        $admin = $this->usuario($propia, ['rol' => 1, 'email' => 'admin@propia.com']);
        $tecnico = $this->usuario($propia, ['numero_empleado' => 'EMP-777', 'email' => 'tec@propia.com']);
        $inactivo = $this->usuario($propia, ['estado' => false, 'email' => 'baja@propia.com']);
        $this->usuario($ajena, ['email' => 'tec@ajena.com']);

        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/v1/tecnicos')
            ->assertOk()
            ->assertJsonPath('success', true);

        $tecnicos = collect($response->json('data'));

        $this->assertEqualsCanonicalizing([$admin->id, $tecnico->id], $tecnicos->pluck('id')->all());
        $this->assertFalse($tecnicos->contains('id', $inactivo->id));

        $fila = $tecnicos->firstWhere('id', $tecnico->id);

        $this->assertSame(3, $fila['rol']);
        $this->assertSame('EMP-777', $fila['numero_empleado']);
        $this->assertSame('EMP-777', $fila['numeroEmpleado']);
        $this->assertSame($propia->id, $fila['dependencia_id']);
        $this->assertSame($propia->id, $fila['idDependencia']);
        $this->assertSame('tec@propia.com', $fila['email']);
        $this->assertSame($tecnico->id, $fila['idUsuario']);
    }

    public function test_endpoint_legacy_sin_prefijo_v1_responde_igual(): void
    {
        $dep = $this->dependencia('Legacy');
        $admin = $this->usuario($dep, ['rol' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/tecnicos')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }
}
