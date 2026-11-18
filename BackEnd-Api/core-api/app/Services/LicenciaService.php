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
     * cat�logo de respaldo. Nunca devuelve null para ids 1..7.
     */
    public function resolverLicencia(int $licenciaId): ?Licencia
    {
        return Licencia::find($licenciaId) ?? $this->catalogoFallback()[$licenciaId] ?? null;
    }

    /**
     * Una dependencia solo puede consumir el periodo de prueba una vez y solo
     * mientras siga en plan trial (un cliente de pago no puede "bajar" a trial).
     */
    public function puedeActivarTrial(Dependencia $dependencia): bool
    {
        return $dependencia->trial_usado_at === null
            && $dependencia->tipo_licencia === 'trial';
    }

    /**
     * Activa o renueva la licencia de una dependencia, de forma atómica.
     */
    public function activarLicencia(Dependencia $dependencia, Licencia $licencia, string $metodo, float $monto = 0, ?string $paymentId = null): Suscripcion
    {
        return DB::transaction(function () use ($dependencia, $licencia, $metodo, $monto, $paymentId) {
            $tipo = $licencia->tipo ?? 'mensual';

            if ($tipo === 'trial' && ! $this->puedeActivarTrial($dependencia)) {
                throw new \DomainException('El periodo de prueba ya fue utilizado.');
            }

            $maxUsuarios = (int) ($licencia->max_usuarios ?? 10);
            $maxReportes = (int) ($licencia->max_reportes_mensuales ?? 100);
            $dias = match ($tipo) {
                'anual' => 365,
                'trial' => (int) ($licencia->dias_prueba ?? 7),
                default => 30,
            };

            $expiracion = now()->addDays($dias);

            $cambios = [
                'tipo_licencia' => $tipo,
                'fecha_expiracion' => $expiracion,
                'limite_usuarios' => $maxUsuarios,
                'limite_reportes_mensuales' => $maxReportes,
                'activa' => true,
            ];

            if ($tipo === 'trial') {
                $cambios['trial_usado_at'] = now();
            }

            $dependencia->update($cambios);

            $suscripcion = Suscripcion::firstOrNew(['dependencia_id' => $dependencia->id]);
            $suscripcion->proveedor = $metodo;
            $suscripcion->plan = $tipo;
            $suscripcion->estado = 'activa';
            $suscripcion->limite_usuarios = $maxUsuarios;
            $suscripcion->limite_reportes_mensuales = $maxReportes;
            $suscripcion->fecha_inicio = now();
            $suscripcion->fecha_expiracion = $expiracion;
            $suscripcion->renovacion_automatica = false;
            if ($paymentId !== null) {
                $suscripcion->mercadopago_payment_id = $paymentId;
            }
            // El catálogo de respaldo no existe en la tabla licencias: solo
            // se enlaza cuando la licencia es un registro real.
            if ($licencia->exists) {
                $suscripcion->licencia_id = $licencia->id;
            }
            $suscripcion->save();

            Log::info('Licencia activada', [
                'dependencia_id' => $dependencia->id,
                'licencia_id' => $licencia->id,
                'metodo' => $metodo,
                'monto' => $monto,
            ]);

            return $suscripcion;
        });
    }

    /**
     * Catálogo de respaldo cuando la tabla licencias está vacía.
     *
     * @return array<int, Licencia>
     */
    public function catalogoFallback(): array
    {
        return [
            1 => $this->licenciaFallback(1, 'Gratis', 'trial', 0, null, 7, 2, 999999, 'Prueba gratuita de 7 d�as para 2 usuarios'),
            2 => $this->licenciaFallback(2, '5 usuarios - Mensual', 'mensual', 399, null, 0, 5, 999999, '5 usuarios - $399 MXN al mes'),
            3 => $this->licenciaFallback(3, '5 usuarios - Anual', 'anual', 399, 3990, 0, 5, 999999, '5 usuarios - $3,990 MXN al a�o'),
            4 => $this->licenciaFallback(4, '10 usuarios - Mensual', 'mensual', 699, null, 0, 10, 999999, '10 usuarios - $699 MXN al mes'),
            5 => $this->licenciaFallback(5, '10 usuarios - Anual', 'anual', 699, 6990, 0, 10, 999999, '10 usuarios - $6,990 MXN al a�o'),
            6 => $this->licenciaFallback(6, '30 usuarios - Mensual', 'mensual', 1999, null, 0, 30, 999999, '30 usuarios - $1,999 MXN al mes'),
            7 => $this->licenciaFallback(7, '30 usuarios - Anual', 'anual', 1999, 19990, 0, 30, 999999, '30 usuarios - $19,990 MXN al a�o'),
        ];
    }

    private function licenciaFallback(
        int $id,
        string $nombre,
        string $tipo,
        float $precio,
        ?float $precioAnual,
        int $diasPrueba,
        int $maxUsuarios,
        int $maxReportes,
        string $descripcion,
    ): Licencia {
        $licencia = new Licencia([
            'nombre' => $nombre,
            'codigo' => $tipo,
            'tipo' => $tipo,
            'max_usuarios' => $maxUsuarios,
            'max_reportes_mensuales' => $maxReportes,
            'precio' => $precio,
            'precio_anual' => $precioAnual,
            'dias_prueba' => $diasPrueba,
            'descripcion' => $descripcion,
            'activo' => true,
        ]);

        // Identificador estable del catálogo de respaldo (no es un registro DB).
        $licencia->forceFill(['id' => $id]);

        return $licencia;
    }
}
