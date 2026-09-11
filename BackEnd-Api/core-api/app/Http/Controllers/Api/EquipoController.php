<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipo;
use Illuminate\Http\Request;

class EquipoController extends Controller
{
    public function index(Request $r){
        return Equipo::where('dependencia_id',$r->user()->dependencia_id)->with('area')->paginate(15);
    }
    public function store(Request $r){
        $data=$r->validate(['codigo'=>'required','nombre'=>'required','area_id'=>'nullable|exists:areas,id','marca'=>'nullable','modelo'=>'nullable','numero_serie'=>'nullable','fecha_instalacion'=>'nullable|date']);
        $data['dependencia_id']=$r->user()->dependencia_id;
        return response()->json(Equipo::create($data),201);
    }
    public function show(Equipo $equipo){ return $equipo->load('area'); }
    public function update(Request $r, Equipo $equipo){ $equipo->update($r->validate(['codigo'=>'sometimes','nombre'=>'sometimes','area_id'=>'sometimes|nullable|exists:areas,id','marca'=>'sometimes','modelo'=>'sometimes','numero_serie'=>'sometimes','fecha_instalacion'=>'sometimes|nullable|date','activo'=>'sometimes|boolean'])); return $equipo; }
    public function destroy(Equipo $equipo){ $equipo->delete(); return response()->json(['message'=>'deleted']); }
}
