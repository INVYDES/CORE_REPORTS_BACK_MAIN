<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de auditoría (tabla bitacora_actividades, ya existente en el esquema).
 */
/**
 * @property int $id
 * @property int $dependencia_id
 * @property int|null $usuario_id
 * @property string $accion
 * @property string $modelo_tipo
 * @property int $modelo_id
 * @property array<string, mixed>|null $valores_anteriores
 * @property array<string, mixed>|null $valores_nuevos
 * @property string|null $direccion_ip
 * @property \Illuminate\Support\Carbon|null $creado_en
 * @property-read Usuario|null $usuario
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

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** @return BelongsTo<Dependencia, $this> */
    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    /**
     * Registra un cambio de un modelo auditable. Nunca lanza excepciones:
     * la auditoría no debe romper la operación principal.
     *
     * @param  array<string, mixed>|null  $anteriores
     * @param  array<string, mixed>|null  $nuevos
     */
    public static function registrar(Model $model, string $accion, ?array $anteriores = null, ?array $nuevos = null): void
    {
        if ($model instanceof self) {
            return;
        }

        $usuario = Auth::user();

        if (! $usuario instanceof Usuario) {
            $usuario = null;
        }

        try {
            self::create([
                'dependencia_id' => $model->getAttribute('dependencia_id') ?? $usuario?->dependencia_id,
                'usuario_id' => $usuario?->id,
                'accion' => $accion,
                'modelo_tipo' => $model::class,
                'modelo_id' => $model->getKey(),
                'valores_anteriores' => $anteriores !== null ? self::filtrar($model, $anteriores) : null,
                'valores_nuevos' => $nuevos !== null ? self::filtrar($model, $nuevos) : null,
                'direccion_ip' => request()->ip(),
                'creado_en' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Oculta campos sensibles y normaliza valores anidados.
     *
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private static function filtrar(Model $model, array $valores): array
    {
        $ocultos = array_merge($model->getHidden(), ['password', 'remember_token']);

        return collect($valores)
            ->except($ocultos)
            ->map(fn ($v) => is_array($v) ? json_encode($v) : $v)
            ->all();
    }
}
