<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use App\Traits\RegistraBitacora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use BelongsToDependencia, RegistraBitacora, SoftDeletes;

    protected $table = 'tickets';

    protected $fillable = [
        'dependencia_id', 'area_id', 'equipo_id', 'folio', 'asunto', 'descripcion',
        'solicitante', 'cargo', 'prioridad', 'estatus', 'fecha_solicitud', 'fecha_atencion',
        'fecha_limite', 'sla_horas', 'usuario_asignado_id',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_atencion' => 'datetime',
        'fecha_limite' => 'datetime',
        'sla_horas' => 'integer',
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
        return $this->hasMany(TicketHistorial::class, 'ticket_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'ticket_id');
    }

    public function getSlaSemaforoAttribute(): string
    {
        if (! $this->fecha_limite || $this->estatus === 'cerrado' || $this->estatus === 'resuelto') {
            return 'cumplido';
        }
        $now = now();
        if ($now->gt($this->fecha_limite)) {
            return 'vencido';
        }
        if ($now->diffInHours($this->fecha_limite, false) <= 24) {
            return 'por_vencer';
        }

        return 'a_tiempo';
    }

    public function scopeAbiertos($q)
    {
        return $q->whereNotIn('estatus', ['cerrado', 'resuelto']);
    }

    public function scopeSinAsignar($q)
    {
        return $q->whereNull('usuario_asignado_id');
    }

    public function scopePorPrioridad($q, $p)
    {
        return $q->where('prioridad', $p);
    }

    /** Folio único por dependencia: TKT-YYYYMMDD-XXXX (secuencial diario). */
    public static function generarFolio(int $dependenciaId): string
    {
        $prefijo = 'TKT-'.now()->format('Ymd');

        $ultimo = static::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id', $dependenciaId)
            ->where('folio', 'like', $prefijo.'-%')
            ->orderByDesc('folio')
            ->value('folio');

        $secuencia = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;

        return sprintf('%s-%04d', $prefijo, $secuencia);
    }
}
