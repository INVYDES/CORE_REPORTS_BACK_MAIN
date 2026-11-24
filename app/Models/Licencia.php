<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $nombre
 * @property string $codigo
 * @property string $tipo
 * @property int $max_usuarios
 * @property int $max_reportes_mensuales
 * @property string $precio
 * @property string|null $precio_anual
 * @property int $dias_prueba
 * @property string|null $descripcion
 * @property bool $activo
 * @property string|null $paypal_plan_id
 * @property string|null $mercadopago_plan_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Licencia extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nombre',
        'codigo',
        'tipo',
        'max_usuarios',
        'max_reportes_mensuales',
        'precio',
        'precio_anual',
        'dias_prueba',
        'descripcion',
        'activo',
        'paypal_plan_id',
        'mercadopago_plan_id',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_anual' => 'decimal:2',
        'activo' => 'boolean',
    ];

    /** @return HasMany<Suscripcion, $this> */
    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class, 'licencia_id');
    }
}
