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
 * @property string|null $categoria
 * @property int|null $usuario_asignado_id
 * @property \Illuminate\Support\Carbon|null $fecha_asignacion
 * @property \Illuminate\Support\Carbon|null $fecha_vencimiento
 * @property string $estatus
 * @property string|null $prioridad
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Usuario|null $asignado
 * @property-read Area|null $area
 * @property-read Equipo|null $equipo
 * @property-read Dependencia|null $dependencia
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ServicioHistorial> $historial
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Reporte> $reportes
 */
class Servicio extends Model
{
    use BelongsToDependencia, GeneraFolio, RegistraBitacora, SoftDeletes;

    protected $table = 'servicios';

    protected $fillable = [
        'dependencia_id', 'area_id', 'equipo_id', 'folio', 'asunto', 'descripcion',
        'solicitante', 'cargo', 'categoria', 'usuario_asignado_id', 'fecha_asignacion', 'fecha_vencimiento', 'estatus', 'prioridad',
    ];

    protected $casts = [
        'fecha_asignacion' => 'date',
        'fecha_vencimiento' => 'date',
    ];

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

    /** @return HasMany<ServicioHistorial, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(ServicioHistorial::class, 'servicio_id');
    }

    /** @return HasMany<Reporte, $this> */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'servicio_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeProgramados(Builder $query): Builder
    {
        return $query->whereIn('estatus', ['programado', 'en_proceso']);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVencidos(Builder $query): Builder
    {
        return $query->where('estatus', 'vencido');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeProximos(Builder $query): Builder
    {
        return $query->whereIn('estatus', ['programado', 'en_proceso'])->orderBy('fecha_vencimiento');
    }

    /** Folio único por dependencia: SRV-YYMMDD-XXXX (secuencial diario). */
    public static function generarFolio(int $dependenciaId): string
    {
        $prefijo = 'SRV-'.now()->format('ymd');

        // Secuencia serializada por dependencia; ver Ticket::generarFolio.
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
