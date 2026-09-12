<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r, DashboardService $svc)
    {
        $dep = $r->user()->dependencia_id;

        return response()->json([
            'tickets_por_asignar' => $svc->ticketsPorAsignar($dep),
            'servicios_proximos' => $svc->serviciosProximos($dep),
            'actividad_reciente' => $svc->actividadReciente($dep),
        ]);
    }

    public function ticketsPorAsignar(Request $r, DashboardService $svc)
    {
        return response()->json($svc->ticketsPorAsignar($r->user()->dependencia_id));
    }

    public function serviciosProximos(Request $r, DashboardService $svc)
    {
        return response()->json($svc->serviciosProximos($r->user()->dependencia_id));
    }

    public function actividadReciente(Request $r, DashboardService $svc)
    {
        return response()->json($svc->actividadReciente($r->user()->dependencia_id));
    }
}
