<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $r){
        $q=Ticket::with(['asignado','area','equipo'])->where('dependencia_id',$r->user()->dependencia_id);
        if($r->estatus) $q->where('estatus',$r->estatus);
        if($r->prioridad) $q->where('prioridad',$r->prioridad);
        if($r->search) $q->where(fn($qq)=>$qq->where('folio','like',"%{$r->search}%")->orWhere('asunto','like',"%{$r->search}%"));
        if($r->sin_asignar) $q->whereNull('usuario_asignado_id');
        return $q->orderByDesc('fecha_solicitud')->paginate($r->get('per_page',15));
    }
    public function store(Request $r){
        $data=$r->validate(['folio'=>'required|string','asunto'=>'required|string|max:200','descripcion'=>'nullable','solicitante'=>'nullable','cargo'=>'nullable','prioridad'=>'sometimes|in:alta,media,baja','estatus'=>'sometimes|in:abierto,en_proceso,resuelto,cerrado','fecha_limite'=>'nullable|date','sla_horas'=>'nullable|integer','usuario_asignado_id'=>'nullable|exists:usuarios,id','area_id'=>'nullable|exists:areas,id','equipo_id'=>'nullable|exists:equipos,id']);
        $data['dependencia_id']=$r->user()->dependencia_id;
        $data['folio']=$data['folio'] ?? 'TKT-'.now()->format('YmdHis');
        return response()->json(Ticket::create($data),201);
    }
    public function show(Ticket $ticket){ return $ticket->load(['asignado','area','equipo','historial','reportes']); }
    public function update(Request $r, Ticket $ticket){
        $data=$r->validate(['asunto'=>'sometimes','descripcion'=>'sometimes','prioridad'=>'sometimes|in:alta,media,baja','estatus'=>'sometimes|in:abierto,en_proceso,resuelto,cerrado','fecha_limite'=>'sometimes|nullable|date','sla_horas'=>'sometimes|nullable|integer','usuario_asignado_id'=>'sometimes|nullable|exists:usuarios,id','area_id'=>'sometimes|nullable|exists:areas,id','equipo_id'=>'sometimes|nullable|exists:equipos,id','fecha_atencion'=>'sometimes|nullable|date']);
        $ticket->update($data); return $ticket;
    }
    public function destroy(Ticket $ticket){ $ticket->delete(); return response()->json(['message'=>'deleted']); }
    public function asignar(Request $r, Ticket $ticket){
        $data=$r->validate(['usuario_asignado_id'=>'required|exists:usuarios,id']);
        $ticket->update(['usuario_asignado_id'=>$data['usuario_asignado_id'], 'estatus'=> $ticket->estatus==='abierto' ? 'en_proceso' : $ticket->estatus]);
        // Notificación
        \App\Models\Notificacion::create(['dependencia_id'=>$ticket->dependencia_id,'tipo'=>'ticket_asignado','referencia_tipo'=>'ticket','referencia_id'=>$ticket->id,'destinatario'=>$ticket->asignado->email ?? '','asunto'=>'Ticket asignado','cuerpo'=>"Ticket {$ticket->folio} asignado",'estatus'=>'pendiente']);
        return $ticket->load('asignado');
    }
}
