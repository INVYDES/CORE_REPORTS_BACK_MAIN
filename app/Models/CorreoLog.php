<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorreoLog extends Model
{
    protected $table = 'correo_logs';

    protected $fillable = [
        'usuario_id',
        'dependencia_id',
        'tipo',
        'clave',
        'destinatario',
        'asunto',
        'estado',
        'error',
        'metadata',
        'enviado_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'enviado_at' => 'datetime',
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
}
