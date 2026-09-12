<?php

namespace App\Traits;

use App\Models\BitacoraActividad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Auditoría de cambios sin dependencias externas: registra created/updated/
 * deleted en bitacora_actividades. Úsalo en modelos trazables
 * (Ticket, Servicio, Reporte, MaterialCatalogo...).
 */
trait RegistraBitacora
{
    public static function bootRegistraBitacora(): void
    {
        static::created(function (Model $model) {
            $model->registrarBitacora('creado', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            if ($model->wasChanged()) {
                $model->registrarBitacora('actualizado', $model->getOriginal(), $model->getChanges());
            }
        });

        static::deleted(function (Model $model) {
            $model->registrarBitacora('eliminado', $model->getOriginal(), null);
        });
    }

    protected function registrarBitacora(string $accion, ?array $anteriores, ?array $nuevos): void
    {
        // Evitar registrar la propia bitácora y llamadas sin contexto (seeders, tests CLI)
        if (in_array(static::class, [BitacoraActividad::class], true)) {
            return;
        }

        try {
            BitacoraActividad::create([
                'dependencia_id' => $this->dependencia_id ?? (Auth::user()->dependencia_id ?? null),
                'usuario_id' => Auth::id(),
                'accion' => $accion,
                'modelo_tipo' => static::class,
                'modelo_id' => $this->getKey(),
                'valores_anteriores' => $anteriores !== null ? $this->filtraBitacora($anteriores) : null,
                'valores_nuevos' => $nuevos !== null ? $this->filtraBitacora($nuevos) : null,
                'direccion_ip' => request()?->ip(),
                'creado_en' => now(),
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe romper la operación principal
            report($e);
        }
    }

    /** Oculta campos sensibles y limita el tamaño del payload. */
    protected function filtraBitacora(array $valores): array
    {
        $ocultos = array_merge(
            $this->getHidden(),
            ['password', 'remember_token']
        );

        return collect($valores)
            ->except($ocultos)
            ->map(fn ($v) => is_array($v) ? json_encode($v) : $v)
            ->all();
    }
}
