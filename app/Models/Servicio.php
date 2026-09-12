<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use App\Traits\RegistraBitacora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Servicio extends Model
{
    use BelongsToDependencia, RegistraBitacora, SoftDeletes;

    protected $table = 'servicios';

    protected $fillable = [
        'dependencia_id', 'area_id', 'equipo_id', 'folio', 'asunto', 'descripcion',
        'solicitante', 'cargo', 'categoria', 'usuario_asignado_id', 'fecha_asignacion', 'fecha_vencimiento', 'estatus', 'prioridad',
    ];

    protected $casts = [
        'fecha_asignacion' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_asignado_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(ServicioHistorial::class, 'servicio_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'servicio_id');
    }

    public function scopeProgramados($q)
    {
        return $q->whereIn('estatus', ['programado', 'en_proceso']);
    }

    public function scopeVencidos($q)
    {
        return $q->where('estatus', 'vencido');
    }

    public function scopeProximos($q)
    {
        return $q->whereIn('estatus', ['programado', 'en_proceso'])->orderBy('fecha_vencimiento');
    }
}
