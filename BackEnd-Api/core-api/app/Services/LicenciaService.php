<?php

namespace App\Services;

use App\Models\Dependencia;
use App\Models\Licencia;
use App\Models\Suscripcion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LicenciaService
{
    /**
     * Resuelve una licencia por id; si no hay registros en DB usa el
     * catálogo de respaldo. Nunca devuelve null para ids 1..3.
     */
    public function resolverLicencia(int $licenciaId): ?object
    {
        $licencia = Licencia::find($licenciaId);

        if ($licencia !== null) {
            return $licencia;
        }

        return $this->catalogoFallback()[$licenciaId] ?? null;
    }

    /**
     * Activa o renueva la licencia de una dependencia, de forma atómica.
     */
    public function activarLicencia(Dependencia $dependencia, object $licencia, string $metodo, float $monto = 0, ?string $paymentId = null): Suscripcion
    {
        return DB::transaction(function () use ($dependencia, $licencia, $metodo, $monto, $paymentId) {
            $tipo = $licencia->tipo ?? 'mensual';
            $maxUsuarios = (int) ($licencia->max_usuarios ?? 10);
            $maxReportes = (int) ($licencia->max_reportes_mensuales ?? 100);
            $dias = match ($tipo) {
                'anual' => 365,
                'trial' => (int) ($licencia->dias_prueba ?? 7),
                default => 30,
            };

            $dependencia->update([
                'tipo_licencia' => $tipo,
                'fecha_expiracion' => now()->addDays($dias)->toDateString(),
                'limite_usuarios' => $maxUsuarios,
                'limite_reportes_mensuales' => $maxReportes,
                'activa' => true,
            ]);

            $suscripcion = Suscripcion::firstOrNew(['dependencia_id' => $dependencia->id]);
            $suscripcion->proveedor = $metodo;
            $suscripcion->plan = $tipo;
            $suscripcion->estado = 'activa';
            $suscripcion->limite_usuarios = $maxUsuarios;
            $suscripcion->limite_reportes_mensuales = $maxReportes;
            $suscripcion->fecha_inicio = now()->toDateString();
            $suscripcion->fecha_expiracion = now()->addDays($dias)->toDateString();
            $suscripcion->renovacion_automatica = false;
            if ($paymentId !== null) {
                $suscripcion->mercadopago_payment_id = $paymentId;
            }
            if (isset($licencia->id) && is_numeric($licencia->id)) {
                $suscripcion->licencia_id = (int) $licencia->id;
            }
            $suscripcion->save();

            Log::info('Licencia activada', [
                'dependencia_id' => $dependencia->id,
                'licencia_id' => $licencia->id ?? null,
                'metodo' => $metodo,
                'monto' => $monto,
            ]);

            return $suscripcion;
        });
    }

    /** Catálogo de respaldo cuando la tabla licencias está vacía. */
    public function catalogoFallback(): array
    {
        return [
            1 => (object) [
                'id' => 1, 'nombre' => 'Trial', 'codigo' => 'trial', 'tipo' => 'trial',
                'max_usuarios' => 10, 'max_reportes_mensuales' => 100,
                'precio' => 0, 'precio_anual' => null, 'dias_prueba' => 7,
                'descripcion' => '7 días gratis para probar', 'activo' => true,
            ],
            2 => (object) [
                'id' => 2, 'nombre' => 'Mensual', 'codigo' => 'mensual', 'tipo' => 'mensual',
                'max_usuarios' => 10, 'max_reportes_mensuales' => 100,
                'precio' => 399, 'precio_anual' => null, 'dias_prueba' => 0,
                'descripcion' => 'Plan mensual - $399 MXN', 'activo' => true,
            ],
            3 => (object) [
                'id' => 3, 'nombre' => 'Anual', 'codigo' => 'anual', 'tipo' => 'anual',
                'max_usuarios' => 10, 'max_reportes_mensuales' => 100,
                'precio' => 399, 'precio_anual' => 3830.40, 'dias_prueba' => 0,
                'descripcion' => 'Plan anual - $3830.40 (20% descuento)', 'activo' => true,
            ],
        ];
    }
}
