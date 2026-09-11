<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use Illuminate\Http\Request;

class DependenciaController extends Controller
{
    public function index(){ return Dependencia::paginate(15); }
    public function show(Dependencia $dependencia){ return $dependencia; }
    public function update(Request $r, Dependencia $dependencia){
        $data=$r->validate(['nombre'=>'sometimes','telefono'=>'sometimes','correo_contacto'=>'sometimes|email','correo_reportes'=>'sometimes|email','tipo_licencia'=>'sometimes|in:trial,mensual,anual','fecha_expiracion'=>'sometimes|date','limite_usuarios'=>'sometimes|integer','limite_reportes_mensuales'=>'sometimes|integer','activa'=>'sometimes|boolean']);
        $dependencia->update($data); return $dependencia;
    }
}
