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
     * El plan gratuito se conserva sin fecha de expiración y no consume el
     * periodo de prueba de los planes de pago.
     */
    public function esPlanGratuito(Licencia $licencia): bool
    {
        return $licencia->codigo === 'trial'
            || ($licencia->tipo === 'trial' && (float) $licencia->precio === 0.0);
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
     * Activa un plan de pago durante su mes gratis sin pasar por la pasarela.
     */
    public function iniciarPruebaLicencia(Dependencia $dependencia, Licencia $licencia, string $metodo = 'registro', ?int $dias = null): Suscripcion
    {
        return DB::transaction(function () use ($dependencia, $licencia, $metodo, $dias) {
            if ($dependencia->trial_usado_at !== null) {
                throw new \DomainException('El periodo de prueba ya fue utilizado.');
            }

            $maxUsuarios = (int) ($licencia->max_usuarios ?? 10);
            $maxReportes = (int) ($licencia->max_reportes_mensuales ?? 100);
            $dias = $dias ?? max(1, (int) ($licencia->dias_prueba ?: 30));
            $expiracion = now()->addDays($dias);

            $dependencia->update([
                'tipo_licencia' => $this->tipoDependencia($licencia),
                'fecha_expiracion' => $expiracion,
                'limite_usuarios' => $maxUsuarios,
                'limite_reportes_mensuales' => $maxReportes,
                'activa' => true,
                'trial_usado_at' => now(),
            ]);

            $suscripcion = Suscripcion::firstOrNew(['dependencia_id' => $dependencia->id]);
            $suscripcion->proveedor = $metodo;
            $suscripcion->plan = $licencia->codigo ?: $this->tipoDependencia($licencia);
            $suscripcion->estado = 'trial';
            $suscripcion->limite_usuarios = $maxUsuarios;
            $suscripcion->limite_reportes_mensuales = $maxReportes;
            $suscripcion->fecha_inicio = now();
            $suscripcion->fecha_expiracion = $expiracion;
            $suscripcion->renovacion_automatica = false;
            if ($licencia->exists) {
                $suscripcion->licencia_id = $licencia->id;
            }
            $suscripcion->save();

            Log::info('Periodo de prueba de plan activado', [
                'dependencia_id' => $dependencia->id,
                'licencia_id' => $licencia->id,
                'dias' => $dias,
            ]);

            return $suscripcion;
        });
    }

    /**
     * Activa o renueva la licencia de una dependencia, de forma atómica.
     */
    public function activarLicencia(Dependencia $dependencia, Licencia $licencia, string $metodo, float $monto = 0, ?string $paymentId = null): Suscripcion
    {
        return DB::transaction(function () use ($dependencia, $licencia, $metodo, $monto, $paymentId) {
            $tipo = $licencia->tipo ?? 'mensual';
            $esGratuito = $this->esPlanGratuito($licencia);

            if (! $esGratuito && $tipo === 'trial' && ! $this->puedeActivarTrial($dependencia)) {
                throw new \DomainException('El periodo de prueba ya fue utilizado.');
            }

            $maxUsuarios = (int) ($licencia->max_usuarios ?? 10);
            $maxReportes = (int) ($licencia->max_reportes_mensuales ?? 100);
            $dias = match ($tipo) {
                'anual' => 365,
                'trial' => (int) ($licencia->dias_prueba ?: 30),
                default => 30,
            };

            $expiracion = null;
            if (! $esGratuito) {
                $base = $dependencia->fecha_expiracion
                    && $dependencia->fecha_expiracion->copy()->endOfDay()->isFuture()
                    ? $dependencia->fecha_expiracion->copy()
                    : now();
                $expiracion = $base->addDays($dias);
            }

            $cambios = [
                'tipo_licencia' => $esGratuito ? 'trial' : $this->tipoDependencia($licencia),
                'fecha_expiracion' => $expiracion,
                'limite_usuarios' => $maxUsuarios,
                'limite_reportes_mensuales' => $maxReportes,
                'activa' => true,
            ];

            if (! $esGratuito && $dependencia->trial_usado_at === null) {
                $cambios['trial_usado_at'] = now();
            }

            $dependencia->update($cambios);

            $suscripcion = Suscripcion::firstOrNew(['dependencia_id' => $dependencia->id]);
            $suscripcion->proveedor = $metodo;
            $suscripcion->plan = $esGratuito ? 'gratis' : ($licencia->codigo ?: $this->tipoDependencia($licencia));
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
            1 => $this->licenciaFallback(1, 'Gratis', 'trial', 0, null, 0, 2, 999999, 'Plan gratuito para 2 usuarios, sin fecha de expiración'),
            2 => $this->licenciaFallback(2, '5 usuarios - Mensual', 'mensual', 399, null, 30, 5, 999999, '5 usuarios - $399 MXN al mes · incluye 1 mes gratis'),
            3 => $this->licenciaFallback(3, '5 usuarios - Anual', 'anual', 399, 3990, 30, 5, 999999, '5 usuarios - $3,990 MXN al año · incluye 1 mes gratis'),
            4 => $this->licenciaFallback(4, '10 usuarios - Mensual', 'mensual', 699, null, 30, 10, 999999, '10 usuarios - $699 MXN al mes · incluye 1 mes gratis'),
            5 => $this->licenciaFallback(5, '10 usuarios - Anual', 'anual', 699, 6990, 30, 10, 999999, '10 usuarios - $6,990 MXN al año · incluye 1 mes gratis'),
            6 => $this->licenciaFallback(6, '30 usuarios - Mensual', 'mensual', 1999, null, 30, 30, 999999, '30 usuarios - $1,999 MXN al mes · incluye 1 mes gratis'),
            7 => $this->licenciaFallback(7, '30 usuarios - Anual', 'anual', 1999, 19990, 30, 30, 999999, '30 usuarios - $19,990 MXN al año · incluye 1 mes gratis'),
        ];
    }

    private function tipoDependencia(Licencia $licencia): string
    {
        return in_array($licencia->tipo, ['mensual', 'anual'], true) ? $licencia->tipo : 'mensual';
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
