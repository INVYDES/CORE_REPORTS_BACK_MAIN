<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class, 'licencia_id');
    }
}
