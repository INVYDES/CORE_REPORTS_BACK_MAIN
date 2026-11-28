<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDICE = 'usuarios_numero_empleado_lower_unique';

    public function up(): void
    {
        if (! Schema::hasTable('usuarios')) {
            return;
        }

        // En bases con colación sensible a mayúsculas, el índice plano deja
        // pasar variantes del mismo ID. Antes de crear el índice funcional se
        // renombran los duplicados que hayan quedado.
        $this->resolverDuplicados();

        if (Schema::hasIndex('usuarios', self::INDICE)) {
            return;
        }

        match ($this->driver()) {
            'mysql' => $this->crearEnMysql(),
            'sqlite', 'pgsql' => DB::statement(
                'CREATE UNIQUE INDEX '.self::INDICE.' ON usuarios (LOWER(numero_empleado))'
            ),
            // SQL Server requeriría una columna calculada; se deja el índice plano.
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasTable('usuarios') || ! Schema::hasIndex('usuarios', self::INDICE)) {
            return;
        }

        match ($this->driver()) {
            'mysql' => Schema::table(
                'usuarios',
                fn ($table) => $table->dropUnique(self::INDICE)
            ),
            'sqlite', 'pgsql' => DB::statement('DROP INDEX IF EXISTS '.self::INDICE),
            default => null,
        };
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    private function crearEnMysql(): void
    {
        $version = (string) (DB::selectOne('select version() as version')->version ?? '');

        // MariaDB no soporta índices funcionales: con su colación habitual
        // (case-insensitive) el índice plano ya cumple la misma función.
        if (str_contains(strtolower($version), 'mariadb')) {
            return;
        }

        if (version_compare($version, '8.0.13', '<')) {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDICE.' ON usuarios ((LOWER(numero_empleado)))'
        );
    }

    /**
     * Conserva el ID del usuario más antiguo de cada grupo y renombra el resto
     * con un sufijo basado en su id (ej. adm-001-7), sin chocar con otros IDs.
     */
    private function resolverDuplicados(): void
    {
        $usuarios = DB::table('usuarios')
            ->whereNotNull('numero_empleado')
            ->orderBy('id')
            ->get(['id', 'numero_empleado']);

        // Todos los IDs existentes, para no renombrar hacia uno ya ocupado.
        $usados = [];
        foreach ($usuarios as $usuario) {
            $valor = (string) $usuario->numero_empleado;
            if ($valor !== '') {
                $usados[mb_strtolower($valor)] = true;
            }
        }

        $vistos = [];

        foreach ($usuarios as $usuario) {
            $valor = (string) $usuario->numero_empleado;

            if ($valor === '') {
                continue;
            }

            $clave = mb_strtolower($valor);

            if (! isset($vistos[$clave])) {
                $vistos[$clave] = true;

                continue;
            }

            $nuevo = $this->idLibre($valor, (int) $usuario->id, $usados);
            DB::table('usuarios')->where('id', $usuario->id)->update(['numero_empleado' => $nuevo]);
            $usados[mb_strtolower($nuevo)] = true;
            $vistos[mb_strtolower($nuevo)] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $usados
     */
    private function idLibre(string $base, int $id, array $usados): string
    {
        $intento = 1;

        do {
            $sufijo = $intento === 1 ? '-'.$id : '-'.$id.'-'.$intento;
            $candidato = mb_substr($base, 0, max(1, 50 - mb_strlen($sufijo))).$sufijo;
            $intento++;
        } while (isset($usados[mb_strtolower($candidato)]));

        return $candidato;
    }
};
