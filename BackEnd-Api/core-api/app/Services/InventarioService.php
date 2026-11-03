<?php

namespace App\Services;

use App\Models\MaterialCatalogo;
use App\Models\MovimientoInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    public function registrarSalida(int $dependenciaId, int $materialId, float $cantidad, string $referenciaTipo, ?int $referenciaId, ?int $usuarioId, ?string $notas = null): MovimientoInventario
    {
        return DB::transaction(function () use ($dependenciaId, $materialId, $cantidad, $referenciaTipo, $referenciaId, $usuarioId, $notas) {
            // Bloqueo de fila: dos reportes simultáneos no pueden sobregirar
            // el mismo material.
            $mat = MaterialCatalogo::where('dependencia_id', $dependenciaId)
                ->lockForUpdate()
                ->findOrFail($materialId);

            if ((float) $mat->stock_actual < $cantidad) {
                throw ValidationException::withMessages([
                    'materiales' => "Stock insuficiente para {$mat->nombre}: disponible {$mat->stock_actual}, solicitado {$cantidad}.",
                ]);
            }

            $mat->decrement('stock_actual', $cantidad);

            return MovimientoInventario::create([
                'dependencia_id' => $dependenciaId,
                'material_id' => $materialId,
                'tipo' => 'salida',
                'cantidad' => $cantidad,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'usuario_id' => $usuarioId,
                'notas' => $notas,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Devuelve al stock lo consumido por un reporte (por ejemplo al eliminarlo).
     */
    public function revertirSalida(int $dependenciaId, int $materialId, float $cantidad, ?int $reporteId, ?int $usuarioId, ?string $notas = null): MovimientoInventario
    {
        return DB::transaction(function () use ($dependenciaId, $materialId, $cantidad, $reporteId, $usuarioId, $notas) {
            $mat = MaterialCatalogo::where('dependencia_id', $dependenciaId)
                ->lockForUpdate()
                ->findOrFail($materialId);

            $mat->increment('stock_actual', $cantidad);

            return MovimientoInventario::create([
                'dependencia_id' => $dependenciaId,
                'material_id' => $materialId,
                'tipo' => 'entrada',
                'cantidad' => $cantidad,
                'referencia_tipo' => 'reporte',
                'referencia_id' => $reporteId,
                'usuario_id' => $usuarioId,
                'notas' => $notas,
                'created_at' => now(),
            ]);
        });
    }

    public function registrarEntrada(int $dependenciaId, int $materialId, float $cantidad, ?int $usuarioId, ?string $notas = null): MovimientoInventario
    {
        return DB::transaction(function () use ($dependenciaId, $materialId, $cantidad, $usuarioId, $notas) {
            $mat = MaterialCatalogo::where('dependencia_id', $dependenciaId)
                ->lockForUpdate()
                ->findOrFail($materialId);
            $mat->increment('stock_actual', $cantidad);

            return MovimientoInventario::create([
                'dependencia_id' => $dependenciaId,
                'material_id' => $materialId,
                'tipo' => 'entrada',
                'cantidad' => $cantidad,
                'referencia_tipo' => 'compra',
                'referencia_id' => null,
                'usuario_id' => $usuarioId,
                'notas' => $notas,
                'created_at' => now(),
            ]);
        });
    }
}
