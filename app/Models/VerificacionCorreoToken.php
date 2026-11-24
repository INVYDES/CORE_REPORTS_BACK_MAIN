<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificacionCorreoToken extends Model
{
    protected $table = 'verificacion_correo_tokens';

    protected $fillable = [
        'usuario_id',
        'token_hash',
        'code_hash',
        'sent_to',
        'attempts',
        'expires_at',
        'consumed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function vigente(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
