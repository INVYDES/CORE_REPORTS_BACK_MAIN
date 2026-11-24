<?php

namespace App\Traits;

use App\Models\Dependencia;
use App\Models\Usuario;
use App\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToDependencia
{
    protected static function bootBelongsToDependencia(): void
    {
        static::addGlobalScope(new DependenciaScope);

        static::creating(function (Model $model) {
            $user = auth()->user();

            if (empty($model->getAttribute('dependencia_id')) && $user instanceof Usuario) {
                $model->setAttribute('dependencia_id', $user->dependencia_id);
            }
        });
    }

    /** @return BelongsTo<Dependencia, $this> */
    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSinDependenciaScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(DependenciaScope::class);
    }
}
