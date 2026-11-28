<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para los listados paginados: cada página filtra por dependencia y
 * ordena por fecha + id. Sin ellos, cada página ordenaba la tabla completa.
 */
return new class extends Migration
{
    /** @var array<string, array<int, array{0: string, 1: array<int, string>}>> */
    private array $indices = [
        'tickets' => [
            ['tickets_dep_fecha_solicitud_idx', ['dependencia_id', 'fecha_solicitud', 'id']],
            ['tickets_dep_asignado_idx', ['dependencia_id', 'usuario_asignado_id']],
        ],
        'servicios' => [
            ['servicios_dep_fecha_vencimiento_idx', ['dependencia_id', 'fecha_vencimiento', 'id']],
            ['servicios_dep_asignado_idx', ['dependencia_id', 'usuario_asignado_id']],
        ],
        'reportes' => [
            ['reportes_dep_fecha_inicio_idx', ['dependencia_id', 'fecha_inicio', 'id']],
            ['reportes_dep_estatus_idx', ['dependencia_id', 'estatus']],
            ['reportes_dep_creado_por_idx', ['dependencia_id', 'creado_por']],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indices as $tabla => $lista) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach ($lista as [$nombre, $columnas]) {
                if (Schema::hasIndex($tabla, $nombre)) {
                    continue;
                }
                if (! Schema::hasColumns($tabla, $columnas)) {
                    continue;
                }
                Schema::table($tabla, fn (Blueprint $t) => $t->index($columnas, $nombre));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indices as $tabla => $lista) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach ($lista as [$nombre]) {
                if (Schema::hasIndex($tabla, $nombre)) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->dropIndex($nombre));
                }
            }
        }
    }
};
