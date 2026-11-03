<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int|null $usuario_id
 * @property string $tipo
 * @property string|null $titulo
 * @property string $referencia_tipo
 * @property int $referencia_id
 * @property string $destinatario
 * @property string $asunto
 * @property string|null $cuerpo
 * @property \Illuminate\Support\Carbon|null $enviado_at
 * @property string $estatus
 * @property \Illuminate\Support\Carbon|null $leida_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Dependencia|null $dependencia
 */
class Notificacion extends Model
{
    use BelongsToDependencia;

    protected $table = 'notificaciones';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'usuario_id', 'tipo', 'titulo', 'referencia_tipo', 'referencia_id', 'destinatario', 'asunto', 'cuerpo', 'enviado_at', 'estatus', 'leida_at', 'created_at'];

    protected $casts = ['enviado_at' => 'datetime', 'leida_at' => 'datetime', 'created_at' => 'datetime'];
}
