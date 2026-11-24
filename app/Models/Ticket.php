<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use App\Traits\GeneraFolio;
use App\Traits\RegistraBitacora;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int|null $area_id
 * @property int|null $equipo_id
 * @property string $folio
 * @property string $asunto
 * @property string|null $descripcion
 * @property string|null $solicitante
 * @property string|null $cargo
 * @property string $prioridad
 * @property string $estatus
 * @property \Illuminate\Support\Carbon|null $fecha_solicitud
 * @property \Illuminate\Support\Carbon|null $fecha_atencion
 * @property \Illuminate\Support\Carbon|null $fecha_limite
 * @property int|null $sla_horas
 * @property int|null $usuario_asignado_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Usuario|null $asignado
 * @property-read Area|null $area
 * @property-read Equipo|null $equipo
 * @property-read Dependencia|null $dependencia
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketHistorial> $historial
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Reporte> $reportes
 */
class Ticket extends Model
{
    use BelongsToDependencia, GeneraFolio, RegistraBitacora, SoftDeletes;

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

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            $ticket->aplicarSla();
        });

        // Si cambia la prioridad y nadie capturo SLA ni fecha limite a mano,
        // se recalcula el compromiso con la nueva prioridad.
        static::updating(function (Ticket $ticket): void {
            if ($ticket->isDirty('prioridad')
                && ! $ticket->isDirty('sla_horas')
                && ! $ticket->isDirty('fecha_limite')) {
                $ticket->aplicarSla(force: true);
            }
        });
    }

    /** Horas de SLA configuradas para una prioridad (alta, media, baja). */
    public static function slaHorasPorPrioridad(?string $prioridad): int
    {
        $prioridad = in_array($prioridad, ['alta', 'media', 'baja'], true) ? $prioridad : 'media';

        return max(1, (int) config('sla.ticket.'.$prioridad, 24));
    }

    /**
     * Completa `sla_horas` y `fecha_limite` con el SLA por prioridad.
     *
     * Los valores capturados explicitamente por el cliente se respetan: solo
     * se rellenan los que vienen vacios (o todos con $force).
     */
    public function aplicarSla(bool $force = false): void
    {
        if ($force || ! $this->sla_horas) {
            $this->sla_horas = self::slaHorasPorPrioridad($this->prioridad);
        }

        if ($force || ! $this->fecha_limite) {
            $base = $this->fecha_solicitud ? Carbon::parse($this->fecha_solicitud) : now();
            $this->fecha_limite = $base->copy()->addHours((int) $this->sla_horas);
        }
    }

    /** @return BelongsTo<Usuario, $this> */
    public function asignado(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_asignado_id');
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    /** @return HasMany<TicketHistorial, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(TicketHistorial::class, 'ticket_id');
    }

    /** @return HasMany<Reporte, $this> */
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->whereNotIn('estatus', ['cerrado', 'resuelto']);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSinAsignar(Builder $query): Builder
    {
        return $query->whereNull('usuario_asignado_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePorPrioridad(Builder $query, ?string $prioridad): Builder
    {
        return $query->where('prioridad', $prioridad);
    }

    /** Folio único por dependencia: TKT-YYMMDD-XXXX (secuencial diario). */
    public static function generarFolio(int $dependenciaId): string
    {
        $prefijo = 'TKT-'.now()->format('ymd');

        // La secuencia se reserva bloqueando la fila de la dependencia (y la
        // lectura del último folio es bloqueante) mientras dure la transacción
        // que inserta el ticket: dos altas simultáneas se serializan y no
        // pueden calcular el mismo número. Usar createConFolio o llamar dentro
        // de DB::transaction.
        DB::table('dependencias')->where('id', $dependenciaId)->lockForUpdate()->first();

        $ultimo = static::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id', $dependenciaId)
            ->where('folio', 'like', $prefijo.'-%')
            ->orderByDesc('folio')
            ->lockForUpdate()
            ->value('folio');

        $secuencia = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;

        return sprintf('%s-%04d', $prefijo, $secuencia);
    }
}
