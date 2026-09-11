<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    use BelongsToDependencia;
    protected $table = 'notificaciones';
    public $timestamps = false;
    protected $fillable = ['dependencia_id','usuario_id','tipo','titulo','referencia_tipo','referencia_id','destinatario','asunto','cuerpo','enviado_at','estatus','leida_at','created_at'];
    protected $casts = ['enviado_at'=>'datetime','leida_at'=>'datetime','created_at'=>'datetime'];
}
