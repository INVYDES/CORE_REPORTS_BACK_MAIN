<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function sla(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->cumplimientoSla($this->usuario($r)->dependencia_id, $r->from, $r->to));
    }

    public function mttr(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->mttr($this->usuario($r)->dependencia_id, $r->from, $r->to));
    }

    public function horasHombre(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->horasHombre($this->usuario($r)->dependencia_id, $r->from, $r->to));
    }

    public function horasPorTecnico(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->horasPorTecnico($this->usuario($r)->dependencia_id));
    }

    public function retrabajos(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->retrabajos($this->usuario($r)->dependencia_id));
    }

    public function categoria(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->distribucionCategoria($this->usuario($r)->dependencia_id));
    }

    public function tiempos(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->tiemposOperativos($this->usuario($r)->dependencia_id));
    }

    public function volumen(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->volumenOperacion($this->usuario($r)->dependencia_id));
    }

    public function eficacia(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->eficaciaCalidad($this->usuario($r)->dependencia_id));
    }

    public function proactivoReactivo(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->proactivoReactivo($this->usuario($r)->dependencia_id));
    }

    public function conteo(Request $r, AnalyticsService $svc): JsonResponse
    {
        return response()->json($svc->conteoOperacion($this->usuario($r)->dependencia_id));
    }

    public function all(Request $r, AnalyticsService $svc): JsonResponse
    {
        $dep = $this->usuario($r)->dependencia_id;

        return response()->json([
            'sla' => $svc->cumplimientoSla($dep, $r->from, $r->to),
            'mttr' => $svc->mttr($dep, $r->from, $r->to),
            'horas_hombre' => $svc->horasHombre($dep),
            'horas_por_tecnico' => $svc->horasPorTecnico($dep),
            'retrabajos' => $svc->retrabajos($dep),
            'categoria' => $svc->distribucionCategoria($dep),
            'tiempos' => $svc->tiemposOperativos($dep),
            'volumen' => $svc->volumenOperacion($dep),
            'eficacia' => $svc->eficaciaCalidad($dep),
            'proactivo_reactivo' => $svc->proactivoReactivo($dep),
            'conteo' => $svc->conteoOperacion($dep),
        ]);
    }
}
