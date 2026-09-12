<?php

namespace App\Traits;

use App\Models\Dependencia;
use App\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToDependencia
{
    protected static function bootBelongsToDependencia(): void
    {
        static::addGlobalScope(new DependenciaScope);

        static::creating(function ($model) {
            if (empty($model->dependencia_id) && auth()->check()) {
                $model->dependencia_id = auth()->user()->dependencia_id;
            }
        });
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    public function scopeSinDependenciaScope($query)
    {
        return $query->withoutGlobalScope(DependenciaScope::class);
    }
}
