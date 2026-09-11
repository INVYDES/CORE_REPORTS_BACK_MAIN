<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reporte;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReporteController extends Controller
{
    public function index(Request $r){
        $q=Reporte::with(['creador','responsable','ticket','servicio','ejecutores.usuario','materiales.material'])->where('dependencia_id',$r->user()->dependencia_id);
        if($r->tipo) $q->where('tipo',$r->tipo);
        if($r->estatus) $q->where('estatus',$r->estatus);
        if($r->search) $q->where(fn($qq)=>$qq->where('folio','like',"%{$r->search}%")->orWhere('desarrollo','like',"%{$r->search}%"));
        return $q->orderByDesc('fecha_inicio')->paginate($r->get('per_page',15));
    }

    public function store(Request $r, InventarioService $inv){
        $data=$r->validate([
            'folio'=>'required|string','tipo'=>'required|in:ticket,servicio,libre','ticket_id'=>'nullable|exists:tickets,id','servicio_id'=>'nullable|exists:servicios,id',
            'categoria'=>'nullable|in:preventivo,correctivo,diagnostico,instalacion,mejora','fecha_inicio'=>'required|date','fecha_fin'=>'required|date|after:fecha_inicio',
            'desarrollo'=>'nullable','estatus'=>'sometimes|in:abierto,parcial,finalizado,descartado','area_id'=>'nullable|exists:areas,id','equipo_id'=>'nullable|exists:equipos,id',
            'hora_salida'=>'nullable|date','hora_llegada'=>'nullable|date','hora_inicio_diagnostico'=>'nullable|date','hora_inicio_trabajo'=>'nullable|date','hora_fin_trabajo'=>'nullable|date','hora_regreso'=>'nullable|date',
            'es_retrabajo'=>'sometimes|boolean','reporte_origen_id'=>'nullable|exists:reportes,id','responsable_id'=>'nullable|exists:usuarios,id',
            'ejecutores'=>'sometimes|array','ejecutores.*'=>'exists:usuarios,id',
            'materiales'=>'sometimes|array','materiales.*.material_id'=>'required_with:materiales|exists:materiales_catalogo,id','materiales.*.cantidad'=>'required_with:materiales|numeric|min:0.01',
            'costo_mano_obra'=>'sometimes|numeric','costo_materiales'=>'sometimes|numeric'
        ]);
        // limite reportes mensuales
        $dep=$r->user()->dependencia;
        $countMes=Reporte::where('dependencia_id',$dep->id)->whereYear('fecha_inicio',now()->year)->whereMonth('fecha_inicio',now()->month)->count();
        if($countMes >= $dep->limite_reportes_mensuales) return response()->json(['message'=>'Límite reportes mensuales alcanzado'],422);

        return DB::transaction(function() use ($r,$data,$inv){
            $data['dependencia_id']=$r->user()->dependencia_id;
            $data['creado_por']=$r->user()->id;
            if($data['tipo']==='libre'){ $data['ticket_id']=null; $data['servicio_id']=null; }
            if($data['tipo']==='ticket') $data['servicio_id']=null;
            if($data['tipo']==='servicio') $data['ticket_id']=null;
            $ejecutores=$data['ejecutores'] ?? []; unset($data['ejecutores']);
            $materiales=$data['materiales'] ?? []; unset($data['materiales']);
            $reporte=Reporte::create($data);
            foreach($ejecutores as $uid){ $reporte->ejecutores()->create(['dependencia_id'=>$reporte->dependencia_id,'usuario_id'=>$uid]); }
            foreach($materiales as $m){
                $mat=\App\Models\MaterialCatalogo::findOrFail($m['material_id']);
                $reporte->materiales()->create(['dependencia_id'=>$reporte->dependencia_id,'material_id'=>$m['material_id'],'cantidad'=>$m['cantidad'],'costo_unitario'=>$mat->costo_unitario]);
                $inv->registrarSalida($reporte->dependencia_id,$m['material_id'], (float)$m['cantidad'],'reporte',$reporte->id,$r->user()->id);
            }
            // evidencias si vienen como archivos
            if($r->hasFile('evidencias')){
                foreach($r->file('evidencias') as $file){
                    $path=$file->store('evidencias/'.$reporte->dependencia_id,'public');
                    $reporte->evidencias()->create(['dependencia_id'=>$reporte->dependencia_id,'url'=>Storage::url($path),'tipo_archivo'=>$file->getClientMimeType(),'peso_kb'=> (int)($file->getSize()/1024),'subido_por'=>$r->user()->id]);
                }
            }
            return response()->json($reporte->load(['ejecutores','materiales','evidencias']),201);
        });
    }

    public function show(Reporte $reporte){ return $reporte->load(['creador','responsable','ticket','servicio','area','equipo','ejecutores.usuario','materiales.material','evidencias','origen']); }
    public function update(Request $r, Reporte $reporte){
        $data=$r->validate(['desarrollo'=>'sometimes','estatus'=>'sometimes|in:abierto,parcial,finalizado,descartado','categoria'=>'sometimes','fecha_inicio'=>'sometimes|date','fecha_fin'=>'sometimes|date','hora_salida'=>'sometimes|nullable|date','hora_llegada'=>'sometimes|nullable|date','hora_inicio_diagnostico'=>'sometimes|nullable|date','hora_inicio_trabajo'=>'sometimes|nullable|date','hora_fin_trabajo'=>'sometimes|nullable|date','hora_regreso'=>'sometimes|nullable|date','costo_mano_obra'=>'sometimes|numeric','costo_materiales'=>'sometimes|numeric']);
        $reporte->update($data); return $reporte->load(['ejecutores','materiales']);
    }
    public function destroy(Reporte $reporte){ $reporte->delete(); return response()->json(['message'=>'deleted']); }

    public function conformidad(Request $r, Reporte $reporte){
        $data=$r->validate(['conformidad_estatus'=>'required|in:aprobado,rechazado','conformidad_firmado_por'=>'required|string','ftfr'=>'sometimes|boolean']);
        $reporte->update(['conformidad_estatus'=>$data['conformidad_estatus'],'conformidad_firmado_por'=>$data['conformidad_firmado_por'],'conformidad_fecha'=>now(),'ftfr'=>$data['ftfr'] ?? $reporte->ftfr]);
        return $reporte;
    }

    public function pdf(Reporte $reporte){
        // placeholder: return json, implementar html2pdf en frontend o dompdf aquí
        return response()->json(['message'=>'PDF generation to implement','reporte'=>$reporte->load(['ejecutores.usuario','materiales.material'])]);
    }
}
