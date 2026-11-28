<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuarios')) {
            return;
        }

        // El ID de empleado se usa para iniciar sesión, así que debe ser único.
        // Antes de crear el índice hay que renombrar los duplicados históricos.
        $this->resolverDuplicados();

        Schema::table('usuarios', function (Blueprint $table) {
            $table->unique('numero_empleado', 'usuarios_numero_empleado_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('usuarios')) {
            return;
        }

        // Los IDs renombrados se conservan para no perder la referencia que ya
        // tienen los usuarios; solo se elimina la restricción.
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique('usuarios_numero_empleado_unique');
        });
    }

    /**
     * Conserva el ID del usuario más antiguo de cada grupo y renombra el resto
     * con un sufijo basado en su id (ej. ADM-001-7), sin chocar con otros IDs.
     */
    private function resolverDuplicados(): void
    {
        $usuarios = DB::table('usuarios')
            ->whereNotNull('numero_empleado')
            ->orderBy('id')
            ->get(['id', 'numero_empleado']);

        // Todos los IDs existentes (normalizados) para no generar colisiones.
        $existentes = [];
        foreach ($usuarios as $usuario) {
            $valor = trim((string) $usuario->numero_empleado);
            if ($valor !== '') {
                $existentes[mb_strtolower($valor)] = true;
            }
        }

        // IDs ya asignados en esta pasada: el primero de cada grupo se conserva.
        $reclamados = [];
        foreach ($usuarios as $usuario) {
            $original = (string) $usuario->numero_empleado;
            $valor = trim($original);

            if ($valor === '') {
                DB::table('usuarios')->where('id', $usuario->id)->update(['numero_empleado' => null]);

                continue;
            }

            $clave = mb_strtolower($valor);

            if (! isset($reclamados[$clave])) {
                $reclamados[$clave] = true;

                // Solo se normaliza el espacio sobrante si no hay duplicado.
                if ($valor !== $original) {
                    DB::table('usuarios')->where('id', $usuario->id)->update(['numero_empleado' => $valor]);
                }

                continue;
            }

            $nuevo = $this->idLibre($valor, (int) $usuario->id, $existentes + $reclamados);
            DB::table('usuarios')->where('id', $usuario->id)->update(['numero_empleado' => $nuevo]);
            $reclamados[mb_strtolower($nuevo)] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $ocupados
     */
    private function idLibre(string $base, int $id, array $ocupados): string
    {
        $intento = 1;

        do {
            $sufijo = $intento === 1 ? '-'.$id : '-'.$id.'-'.$intento;
            $candidato = mb_substr($base, 0, max(1, 50 - mb_strlen($sufijo))).$sufijo;
            $intento++;
        } while (isset($ocupados[mb_strtolower($candidato)]));

        return $candidato;
    }
};
