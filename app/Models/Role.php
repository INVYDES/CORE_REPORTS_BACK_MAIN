<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['id', 'nombre', 'codigo', 'descripcion', 'es_base'];

    protected $casts = ['es_base' => 'boolean'];
}
