<?php

namespace App\Services;

use App\Models\Reporte;
use App\Models\Servicio;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    /** @return Collection<int, Ticket> */
    public function ticketsPorAsignar(int $dependenciaId, int $limit = 5): Collection
    {
        return Ticket::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id', $dependenciaId)
            ->whereNull('usuario_asignado_id')
            ->whereNotIn('estatus', ['cerrado', 'resuelto'])
            ->orderByRaw("FIELD(prioridad,'alta','media','baja')")
            ->limit($limit)->get();
    }

    /** @return Collection<int, Servicio> */
    public function serviciosProximos(int $dependenciaId, int $limit = 5): Collection
    {
        return Servicio::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id', $dependenciaId)
            ->whereIn('estatus', ['programado', 'en_proceso'])
            ->orderBy('fecha_vencimiento')
            ->limit($limit)->get();
    }

    /** @return Collection<int, Reporte> */
    public function actividadReciente(int $dependenciaId, int $limit = 5): Collection
    {
        return Reporte::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id', $dependenciaId)
            ->with(['creador', 'ticket', 'servicio'])
            ->orderByDesc('fecha_inicio')
            ->limit($limit)->get();
    }
}
