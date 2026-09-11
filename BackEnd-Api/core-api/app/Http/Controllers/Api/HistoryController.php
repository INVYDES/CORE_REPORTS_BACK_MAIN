<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Servicio;
use App\Models\Reporte;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $depId = $request->user()->dependencia_id;
        $from = $request->query('from');
        $to = $request->query('to');
        $tipo = $request->query('tipo'); // ticket|servicio|reporte
        $limit = (int) $request->query('per_page', 50);

        $tickets = collect();
        $servicios = collect();
        $reportes = collect();

        if (!$tipo || $tipo === 'ticket') {
            $q = Ticket::withoutGlobalScope(\App\Scopes\DependenciaScope::class)->where('dependencia_id', $depId);
            if ($from) $q->whereDate('fecha_solicitud', '>=', $from);
            if ($to) $q->whereDate('fecha_solicitud', '<=', $to);
            $tickets = $q->orderByDesc('fecha_solicitud')->limit($limit)->get()->map(fn($t)=> [
                'tipo'=>'ticket','id'=>$t->id,'folio'=>$t->folio,'fecha'=>$t->fecha_solicitud,'titulo'=>$t->asunto,'estatus'=>$t->estatus,'prioridad'=>$t->prioridad,
            ]);
        }
        if (!$tipo || $tipo === 'servicio') {
            $q = Servicio::withoutGlobalScope(\App\Scopes\DependenciaScope::class)->where('dependencia_id', $depId);
            if ($from) $q->whereDate('fecha_asignacion', '>=', $from);
            if ($to) $q->whereDate('fecha_asignacion', '<=', $to);
            $servicios = $q->orderByDesc('fecha_asignacion')->limit($limit)->get()->map(fn($s)=> [
                'tipo'=>'servicio','id'=>$s->id,'folio'=>$s->folio,'fecha'=>$s->fecha_asignacion ?? $s->fecha_vencimiento,'titulo'=>$s->asunto,'estatus'=>$s->estatus,'categoria'=>$s->categoria,
            ]);
        }
        if (!$tipo || $tipo === 'reporte') {
            $q = Reporte::withoutGlobalScope(\App\Scopes\DependenciaScope::class)->where('dependencia_id', $depId);
            if ($from) $q->whereDate('fecha_inicio', '>=', $from);
            if ($to) $q->whereDate('fecha_inicio', '<=', $to);
            $reportes = $q->orderByDesc('fecha_inicio')->limit($limit)->get()->map(fn($r)=> [
                'tipo'=>'reporte','id'=>$r->id,'folio'=>$r->folio,'fecha'=>$r->fecha_inicio,'titulo'=>substr($r->desarrollo ?? '',0,80),'estatus'=>$r->estatus,'categoria'=>$r->categoria,
            ]);
        }

        $merged = collect()->merge($tickets)->merge($servicios)->merge($reportes)->sortByDesc('fecha')->values()->take($limit);
        return response()->json(['data'=>$merged, 'counts'=>['tickets'=>$tickets->count(),'servicios'=>$servicios->count(),'reportes'=>$reportes->count()]]);
    }
}
