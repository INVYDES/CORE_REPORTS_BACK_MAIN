<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Servicio;
use App\Models\Reporte;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function ticketsPorAsignar(int $dependenciaId, int $limit = 5)
    {
        return Ticket::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id',$dependenciaId)
            ->whereNull('usuario_asignado_id')
            ->whereNotIn('estatus',['cerrado','resuelto'])
            ->orderByRaw("FIELD(prioridad,'alta','media','baja')")
            ->limit($limit)->get();
    }

    public function serviciosProximos(int $dependenciaId, int $limit = 5)
    {
        return Servicio::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id',$dependenciaId)
            ->whereIn('estatus',['programado','en_proceso'])
            ->orderBy('fecha_vencimiento')
            ->limit($limit)->get();
    }

    public function actividadReciente(int $dependenciaId, int $limit = 5)
    {
        return Reporte::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id',$dependenciaId)
            ->with(['creador','ticket','servicio'])
            ->orderByDesc('fecha_inicio')
            ->limit($limit)->get();
    }
}
