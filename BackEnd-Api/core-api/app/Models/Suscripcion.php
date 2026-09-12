<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

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
