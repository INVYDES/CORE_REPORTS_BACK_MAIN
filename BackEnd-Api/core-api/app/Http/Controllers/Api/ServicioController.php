<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    public function index(Request $r){
        $q=Servicio::with(['asignado','area','equipo'])->where('dependencia_id',$r->user()->dependencia_id);
        if($r->estatus) $q->where('estatus',$r->estatus);
        if($r->categoria) $q->where('categoria',$r->categoria);
        if($r->search) $q->where(fn($qq)=>$qq->where('folio','like',"%{$r->search}%")->orWhere('asunto','like',"%{$r->search}%"));
        return $q->orderBy('fecha_vencimiento')->paginate($r->get('per_page',15));
    }
    public function store(Request $r){
        $data=$r->validate(['folio'=>'required','asunto'=>'required','descripcion'=>'nullable','categoria'=>'nullable|in:preventivo,correctivo,instalacion,mejora,diagnostico','fecha_asignacion'=>'nullable|date','fecha_vencimiento'=>'nullable|date','usuario_asignado_id'=>'nullable|exists:usuarios,id','area_id'=>'nullable|exists:areas,id','equipo_id'=>'nullable|exists:equipos,id','prioridad'=>'nullable','solicitante'=>'nullable','cargo'=>'nullable']);
        $data['dependencia_id']=$r->user()->dependencia_id;
        return response()->json(Servicio::create($data),201);
    }
    public function show(Servicio $servicio){ return $servicio->load(['asignado','area','equipo','historial','reportes']); }
    public function update(Request $r, Servicio $servicio){
        $data=$r->validate(['asunto'=>'sometimes','descripcion'=>'sometimes','categoria'=>'sometimes','fecha_asignacion'=>'sometimes|nullable|date','fecha_vencimiento'=>'sometimes|nullable|date','estatus'=>'sometimes|in:programado,en_proceso,realizado,vencido','prioridad'=>'sometimes','usuario_asignado_id'=>'sometimes|nullable|exists:usuarios,id','area_id'=>'sometimes|nullable|exists:areas,id','equipo_id'=>'sometimes|nullable|exists:equipos,id']);
        $servicio->update($data); return $servicio;
    }
    public function destroy(Servicio $servicio){ $servicio->delete(); return response()->json(['message'=>'deleted']); }
}
