<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría (tabla bitacora_actividades, ya existente en el esquema).
 */
class BitacoraActividad extends Model
{
    public $timestamps = false;

    protected $table = 'bitacora_actividades';

    protected $fillable = [
        'dependencia_id',
        'usuario_id',
        'accion',
        'modelo_tipo',
        'modelo_id',
        'valores_anteriores',
        'valores_nuevos',
        'direccion_ip',
        'creado_en',
    ];

    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array',
        'creado_en' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }
}
