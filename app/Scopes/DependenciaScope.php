<?php

namespace App\Scopes;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class DependenciaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // Sin usuario autenticado (comandos, seeders) no se filtra.
        if (! $user instanceof Usuario) {
            return;
        }

        $builder->where($model->qualifyColumn('dependencia_id'), $user->dependencia_id);
    }
}
