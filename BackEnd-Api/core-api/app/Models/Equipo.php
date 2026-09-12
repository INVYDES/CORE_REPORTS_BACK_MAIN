<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipo extends Model
{
    use BelongsToDependencia, SoftDeletes;

    protected $table = 'equipos';

    protected $fillable = ['dependencia_id', 'area_id', 'codigo', 'nombre', 'marca', 'modelo', 'numero_serie', 'fecha_instalacion', 'activo'];

    protected $casts = ['activo' => 'boolean', 'fecha_instalacion' => 'date'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
}
