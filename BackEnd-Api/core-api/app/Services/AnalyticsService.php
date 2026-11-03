<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /** @return Collection<int, \stdClass> */
    public function cumplimientoSla(int $dependenciaId, ?string $from = null, ?string $to = null): Collection
    {
        return DB::table('vw_ticket_sla')->where('dependencia_id', $dependenciaId)
            ->when($from, fn ($q) => $q->where('fecha_solicitud', '>=', $from))
            ->when($to, fn ($q) => $q->where('fecha_solicitud', '<=', $to))
            ->select('prioridad', DB::raw('AVG(a_tiempo) as cumplimiento'), DB::raw('COUNT(*) as total'))
            ->groupBy('prioridad')->get();
    }

    public function mttr(int $dependenciaId, ?string $from = null, ?string $to = null): ?object
    {
        return DB::table('vw_ticket_mttr')->where('dependencia_id', $dependenciaId)
            ->when($from, fn ($q) => $q->where('fecha_solicitud', '>=', $from))
            ->when($to, fn ($q) => $q->where('fecha_solicitud', '<=', $to))
            ->select(DB::raw('AVG(horas_reparacion) as mttr_horas'))->first();
    }

    public function horasHombre(int $dependenciaId, ?string $from = null, ?string $to = null): ?object
    {
        return DB::table('vw_horas_hombre')->where('dependencia_id', $dependenciaId)->select(DB::raw('SUM(minutos)/60 as horas'))->first();
    }

    /** @return Collection<int, \stdClass> */
    public function horasPorTecnico(int $dependenciaId): Collection
    {
        return DB::table('vw_horas_por_tecnico')->where('dependencia_id', $dependenciaId)
            ->select('usuario_id', DB::raw('SUM(minutos)/60 as horas'))->groupBy('usuario_id')->get();
    }

    public function retrabajos(int $dependenciaId): ?object
    {
        return DB::table('vw_reportes_retrabajo')->where('dependencia_id', $dependenciaId)
            ->select(DB::raw('AVG(es_retrabajo)*100 as pct_retrabajo'))->first();
    }

    /** @return Collection<int, \stdClass> */
    public function distribucionCategoria(int $dependenciaId): Collection
    {
        return DB::table('vw_reportes_categoria')->where('dependencia_id', $dependenciaId)
            ->select('categoria', DB::raw('COUNT(*) as total'))->groupBy('categoria')->get();
    }

    public function tiemposOperativos(int $dependenciaId): ?object
    {
        return DB::table('vw_tiempos_operativos')->where('dependencia_id', $dependenciaId)
            ->select(DB::raw('AVG(t_atencion_horas) as t_atencion'), DB::raw('AVG(t_diagnostico_horas) as t_diag'), DB::raw('AVG(t_utilizacion_horas) as t_util'))->first();
    }

    /** @return Collection<int, \stdClass> */
    public function volumenOperacion(int $dependenciaId): Collection
    {
        return DB::table('vw_volumen_operacion')->where('dependencia_id', $dependenciaId)
            ->select('tipo', DB::raw('COUNT(*) as total'))->groupBy('tipo')->get();
    }

    public function eficaciaCalidad(int $dependenciaId): ?object
    {
        return DB::table('vw_eficacia_calidad')->where('dependencia_id', $dependenciaId)
            ->select(DB::raw('AVG(ftfr) as pct_ftfr'), DB::raw('AVG(conforme) as pct_conformidad'), DB::raw('AVG(descartado) as pct_descartado'), DB::raw('AVG(abierta) as pct_abierta'))->first();
    }

    public function proactivoReactivo(int $dependenciaId): ?object
    {
        return DB::table('vw_proactivo_reactivo')->where('dependencia_id', $dependenciaId)
            ->select(DB::raw("SUM(CASE WHEN tipo='servicio' THEN 1 ELSE 0 END) as proactivo"), DB::raw("SUM(CASE WHEN tipo='ticket' THEN 1 ELSE 0 END) as reactivo"))->first();
    }

    public function conteoOperacion(int $dependenciaId): ?object
    {
        return DB::table('vw_conteo_operacion')->where('dependencia_id', $dependenciaId)->first();
    }
}
