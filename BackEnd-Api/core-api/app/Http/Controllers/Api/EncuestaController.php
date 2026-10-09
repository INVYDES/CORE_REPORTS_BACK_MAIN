<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encuesta;
use App\Models\Reporte;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EncuestaController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $q = Encuesta::with('reporte');

        if ($request->filled('reporte_id')) {
            $q->where('reporte_id', (int) $request->reporte_id);
        }
        if ($request->filled('calificacion')) {
            $q->where('calificacion', (int) $request->calificacion);
        }

        return response()->json($q->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $reporteId = (int) $request->input('reporte_id', $request->input('idReporte', 0));
        $calificacion = (int) $request->input('calificacion', 0);
        $comentario = $request->input('comentario', $request->input('comentarios'));
        $respondidoPor = $request->input('respondido_por', $request->input('nombreFirma'));

        if (! $reporteId) {
            return response()->json(['message' => 'El ID del reporte es obligatorio.'], 422);
        }
        if ($calificacion < 1 || $calificacion > 10) {
            return response()->json(['message' => 'Debes asignar una calificación válida.'], 422);
        }

        // Si la calificación es de 1 a 10 (escala front), normalizar a 1 a 5 si la base de datos lo requiere o almacenar
        $califGuardar = $calificacion > 5 ? (int) round($calificacion / 2) : $calificacion;

        $reporte = Reporte::find($reporteId);
        if (! $reporte) {
            return response()->json(['message' => 'Reporte no encontrado.'], 404);
        }

        $encuesta = Encuesta::updateOrCreate(
            ['reporte_id' => $reporte->id],
            [
                'dependencia_id' => $reporte->dependencia_id,
                'calificacion' => $califGuardar,
                'comentario' => $comentario,
                'respondido_por' => $respondidoPor ?? trim($request->user()->nombre.' '.$request->user()->apellidos),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Evaluación de servicio guardada con éxito.',
            'data' => [
                'idEncuesta' => $encuesta->id,
                'idReporte' => $encuesta->reporte_id,
                'calificacion' => (int) $encuesta->calificacion,
                'comentarios' => $encuesta->comentario,
                'nombreFirma' => $encuesta->respondido_por,
                'fecha' => $encuesta->created_at ? $encuesta->created_at->toDateTimeString() : now()->toDateTimeString(),
            ],
        ], 200);
    }

    public function show(Encuesta $encuesta): JsonResponse
    {
        $this->ensureOwnDependencia($encuesta);

        return response()->json($encuesta->load('reporte'));
    }

    /**
     * GET /api/v1/encuestas/reporte/{idReporte} o /api/encuestas/{idReporte}
     */
    public function porReporte(Request $request, $idReporte): JsonResponse
    {
        $encuesta = Encuesta::where('reporte_id', $idReporte)->first();

        if (! $encuesta) {
            return response()->json([
                'success' => false,
                'message' => 'No hay encuesta registrada para este reporte.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'idEncuesta' => $encuesta->id,
                'idReporte' => $encuesta->reporte_id,
                'calificacion' => (int) $encuesta->calificacion,
                'comentarios' => $encuesta->comentario,
                'nombreFirma' => $encuesta->respondido_por,
                'fecha' => $encuesta->created_at ? $encuesta->created_at->toDateTimeString() : null,
            ],
        ], 200);
    }
}
