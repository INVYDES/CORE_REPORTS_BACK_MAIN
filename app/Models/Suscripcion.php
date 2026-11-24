<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int|null $licencia_id
 * @property string $proveedor
 * @property string|null $proveedor_suscripcion_id
 * @property string $plan
 * @property string $estado
 * @property int $limite_usuarios
 * @property int $limite_reportes_mensuales
 * @property \Illuminate\Support\Carbon $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_expiracion
 * @property bool $renovacion_automatica
 * @property string|null $mercadopago_payment_id
 * @property string|null $mercadopago_preference_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Dependencia|null $dependencia
 * @property-read Licencia|null $licencia
 */
class Suscripcion extends Model
{
    protected $table = 'suscripciones';

    protected $fillable = [
        'dependencia_id',
        'licencia_id',
        'proveedor',
        'proveedor_suscripcion_id',
        'plan',
        'estado',
        'limite_usuarios',
        'limite_reportes_mensuales',
        'fecha_inicio',
        'fecha_expiracion',
        'renovacion_automatica',
        'mercadopago_payment_id',
        'mercadopago_preference_id',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_expiracion' => 'date',
        'renovacion_automatica' => 'boolean',
        'limite_usuarios' => 'integer',
        'limite_reportes_mensuales' => 'integer',
    ];

    /** @return BelongsTo<Dependencia, $this> */
    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    /** @return BelongsTo<Licencia, $this> */
    public function licencia(): BelongsTo
    {
        return $this->belongsTo(Licencia::class, 'licencia_id');
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'activa'
            && (! $this->fecha_expiracion || $this->fecha_expiracion->isFuture());
    }
}
