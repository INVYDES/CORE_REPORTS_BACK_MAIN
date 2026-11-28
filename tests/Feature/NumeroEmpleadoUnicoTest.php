<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NumeroEmpleadoUnicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_migracion_renombra_duplicados_y_deja_el_indice_unico(): void
    {
        // Simula la base anterior sin la restricción y con IDs repetidos.
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique('usuarios_numero_empleado_unique');
        });

        $dependencia = Dependencia::create([
            'nombre' => 'Empresa Duplicada',
            'tipo_licencia' => 'trial',
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);

        $usuario = fn (string $email, string $numero) => Usuario::create([
            'dependencia_id' => $dependencia->id,
            'rol' => 2,
            'nombre' => 'Admin',
            'apellidos' => 'Prueba',
            'email' => $email,
            'password' => 'password123',
            'numero_empleado' => $numero,
            'estado' => true,
        ]);

        $primero = $usuario('uno@empresa.com', 'ADM-001');
        $segundo = $usuario('dos@empresa.com', 'ADM-001');
        $tercero = $usuario('tres@empresa.com', 'adm-001');
        $existente = $usuario('cuatro@empresa.com', 'ADM-001-3');

        $migracion = require database_path(
            'migrations/2026_11_28_000002_add_unique_numero_empleado_to_usuarios_table.php'
        );
        $migracion->up();

        // El usuario más antiguo conserva su ID; el resto recibe un sufijo libre.
        $this->assertSame('ADM-001', $primero->fresh()->numero_empleado);
        $this->assertSame('ADM-001-'.$segundo->id, $segundo->fresh()->numero_empleado);
        $this->assertNotSame('ADM-001-3', $tercero->fresh()->numero_empleado);
        $this->assertSame('ADM-001-3', $existente->fresh()->numero_empleado);

        $ids = Usuario::pluck('numero_empleado')->map(fn ($id) => mb_strtolower((string) $id));
        $this->assertSame($ids->count(), $ids->unique()->count());

        $this->expectException(QueryException::class);
        $usuario('cinco@empresa.com', 'ADM-001');
    }
}
