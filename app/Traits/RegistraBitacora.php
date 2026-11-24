<?php

namespace App\Traits;

use App\Models\BitacoraActividad;
use Illuminate\Database\Eloquent\Model;

/**
 * Auditoría de cambios: registra created/updated/deleted en
 * bitacora_actividades. Úsalo en modelos trazables
 * (Ticket, Servicio, Reporte, MaterialCatalogo...).
 */
trait RegistraBitacora
{
    public static function bootRegistraBitacora(): void
    {
        static::created(function (Model $model) {
            BitacoraActividad::registrar($model, 'creado', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            if ($model->wasChanged()) {
                BitacoraActividad::registrar($model, 'actualizado', $model->getOriginal(), $model->getChanges());
            }
        });

        static::deleted(function (Model $model) {
            BitacoraActividad::registrar($model, 'eliminado', $model->getOriginal(), null);
        });
    }
}
