<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaterialCatalogo;
use App\Services\InventarioService;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(Request $r){ return MaterialCatalogo::where('dependencia_id',$r->user()->dependencia_id)->paginate(15); }
    public function store(Request $r){
        $data=$r->validate(['nombre'=>'required','descripcion'=>'nullable','unidad_base'=>'required','costo_unitario'=>'sometimes|numeric','stock_actual'=>'sometimes|numeric','activo'=>'sometimes|boolean']);
        $data['dependencia_id']=$r->user()->dependencia_id;
        return response()->json(MaterialCatalogo::create($data),201);
    }
    public function show(MaterialCatalogo $material){ return $material; }
    public function update(Request $r, MaterialCatalogo $material){ $material->update($r->validate(['nombre'=>'sometimes','descripcion'=>'sometimes','unidad_base'=>'sometimes','costo_unitario'=>'sometimes|numeric','stock_actual'=>'sometimes|numeric','activo'=>'sometimes|boolean'])); return $material; }
    public function destroy(MaterialCatalogo $material){ $material->delete(); return response()->json(['message'=>'deleted']); }
    public function entrada(Request $r, MaterialCatalogo $material, InventarioService $svc){
        $data=$r->validate(['cantidad'=>'required|numeric|min:0.01','notas'=>'nullable']);
        $mov=$svc->registrarEntrada($material->dependencia_id,$material->id,(float)$data['cantidad'],$r->user()->id,$data['notas']??null);
        return response()->json($mov,201);
    }
    public function movimientos(Request $r, MaterialCatalogo $material){
        return \App\Models\MovimientoInventario::where('dependencia_id',$r->user()->dependencia_id)->where('material_id',$material->id)->orderByDesc('created_at')->paginate(20);
    }
}
