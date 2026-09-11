<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class DependenciaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Si no hay usuario autenticado, no filtrar (ej. comandos, seeders)
        if (!Auth::hasUser()) {
            return;
        }

        $user = Auth::user();

        // Superadmin sin dependencia_id no se filtra (si existiera)
        if (!isset($user->dependencia_id) || $user->dependencia_id === null) {
            return;
        }

        $builder->where($model->qualifyColumn('dependencia_id'), $user->dependencia_id);
    }
}
