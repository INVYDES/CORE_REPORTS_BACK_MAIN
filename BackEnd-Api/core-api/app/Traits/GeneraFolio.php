<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Creación atómica de registros con folio correlativo.
 *
 * `generarFolio()` toma el bloqueo de la secuencia de la dependencia; para que
 * ese bloqueo sirva de algo tiene que seguir vigente cuando se inserta la fila.
 * Este trait garantiza que ambas operaciones ocurran en la misma transacción.
 */
trait GeneraFolio
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createConFolio(array $attributes, int $dependenciaId): static
    {
        return DB::transaction(function () use ($attributes, $dependenciaId): static {
            $attributes['folio'] ??= static::generarFolio($dependenciaId);

            /** @var static $creado */
            $creado = static::create($attributes);

            return $creado;
        });
    }
}
