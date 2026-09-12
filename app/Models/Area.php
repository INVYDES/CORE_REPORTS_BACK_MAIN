<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model
{
    use BelongsToDependencia, SoftDeletes;

    protected $table = 'areas';

    protected $fillable = ['dependencia_id', 'codigo', 'nombre', 'descripcion', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'area_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'area_id');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'area_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'area_id');
    }
}
