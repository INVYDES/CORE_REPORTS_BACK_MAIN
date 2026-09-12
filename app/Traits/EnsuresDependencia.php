<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Autorización multi-tenant para controladores API.
 *
 * Los modelos con dependencia usan el global scope DependenciaScope, por lo
 * que findOrFail() ya está aislado por dependencia. Este trait añade:
 *  - checks explícitos de dependencia (defensa en profundidad)
 *  - validación de FKs del request contra la dependencia activa (anti cross-tenant)
 */
trait EnsuresDependencia
{
    /**
     * Aborta si el modelo no pertenece a la dependencia del usuario autenticado.
     * Con el global scope activo es defensa en profundidad; nunca debe fallar.
     */
    protected function ensureOwnDependencia(Model $model): void
    {
        if ($model->dependencia_id !== auth()->user()->dependencia_id) {
            abort(403, 'El recurso no pertenece a tu dependencia.');
        }
    }

    /**
     * Valida que una FK del request exista y pertenezca a la dependencia activa.
     * Devuelve el id saneado o null si el campo no vino en el request.
     */
    protected function validatedDependenciaId(Request $request, string $field, string $modelClass): ?int
    {
        $id = $request->input($field);

        if ($id === null || $id === '') {
            return null;
        }

        // find() ya aplica DependenciaScope: si existe, es de esta dependencia.
        $found = $modelClass::find((int) $id);

        if ($found === null) {
            abort(422, "El campo {$field} no existe en tu dependencia.");
        }

        return (int) $found->getKey();
    }

    /**
     * Valida un array de ids de usuarios (ejecutores) contra la dependencia activa.
     *
     * @return int[] ids validados
     */
    protected function validatedDependenciaUserIds(Request $request, string $field = 'ejecutores'): array
    {
        $ids = $request->input($field);

        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $found = \App\Models\Usuario::whereIn('id', array_map('intval', $ids))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($found) !== count(array_unique(array_map('intval', $ids)))) {
            abort(422, "Algunos usuarios en {$field} no existen en tu dependencia.");
        }

        return $found;
    }
}
