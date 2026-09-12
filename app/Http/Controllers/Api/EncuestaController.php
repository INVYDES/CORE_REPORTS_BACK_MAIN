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
        $this->authorize('create', Encuesta::class);

        $data = $request->validate([
            'reporte_id' => 'required|integer',
            'calificacion' => 'required|integer|between:1,5',
            'comentario' => 'nullable|string|max:2000',
            'respondido_por' => 'nullable|string|max:150',
        ], [], [
            'reporte_id' => 'reporte',
            'calificacion' => 'calificación',
        ]);

        $reporte = Reporte::findOrFail((int) $data['reporte_id']);

        if ($reporte->estatus !== 'finalizado') {
            return response()->json(['message' => 'Solo se puede encuestar un reporte finalizado.'], 422);
        }

        if (Encuesta::where('reporte_id', $reporte->id)->exists()) {
            return response()->json(['message' => 'Este reporte ya tiene encuesta.'], 422);
        }

        $encuesta = Encuesta::create([
            'reporte_id' => $reporte->id,
            'calificacion' => $data['calificacion'],
            'comentario' => $data['comentario'] ?? null,
            'respondido_por' => $data['respondido_por'] ?? trim($request->user()->nombre.' '.$request->user()->apellidos),
            'created_at' => now(),
        ]);

        return response()->json($encuesta->load('reporte'), 201);
    }

    public function show(Encuesta $encuesta): JsonResponse
    {
        $this->ensureOwnDependencia($encuesta);

        return response()->json($encuesta->load('reporte'));
    }
}
