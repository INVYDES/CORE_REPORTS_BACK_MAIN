<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index(Request $r){
        return Area::where('dependencia_id',$r->user()->dependencia_id)->withCount(['tickets','servicios'])->paginate(15);
    }
    public function store(Request $r){
        $data=$r->validate(['codigo'=>'required|string|max:20','nombre'=>'required|string|max:150','descripcion'=>'nullable','activa'=>'sometimes|boolean']);
        $data['dependencia_id']=$r->user()->dependencia_id;
        return response()->json(Area::create($data),201);
    }
    public function show(Area $area){ return $area; }
    public function update(Request $r, Area $area){ $area->update($r->validate(['codigo'=>'sometimes','nombre'=>'sometimes','descripcion'=>'sometimes','activa'=>'sometimes|boolean'])); return $area; }
    public function destroy(Area $area){ $area->delete(); return response()->json(['message'=>'deleted']); }
}
