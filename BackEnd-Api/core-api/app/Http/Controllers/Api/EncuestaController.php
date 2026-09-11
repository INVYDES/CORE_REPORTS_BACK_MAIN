<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encuesta;
use App\Models\Reporte;
use Illuminate\Http\Request;

class EncuestaController extends Controller
{
    public function index(Request $request)
    {
        $q = Encuesta::with('reporte')->where('dependencia_id', $request->user()->dependencia_id);
        if ($request->reporte_id) $q->where('reporte_id', $request->reporte_id);
        return $q->orderByDesc('created_at')->paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'reporte_id' => 'required|exists:reportes,id',
            'calificacion' => 'required|integer|between:1,5',
            'comentario' => 'nullable|string|max:2000',
            'respondido_por' => 'nullable|string|max:150',
        ], [], [
            'reporte_id' => 'reporte',
            'calificacion' => 'calificación',
        ]);

        $reporte = Reporte::where('dependencia_id', $request->user()->dependencia_id)->findOrFail($data['reporte_id']);

        if ($reporte->estatus !== 'finalizado') {
            return response()->json(['message' => 'Solo se puede encuestar un reporte finalizado.'], 422);
        }

        if (Encuesta::where('reporte_id', $reporte->id)->exists()) {
            return response()->json(['message' => 'Este reporte ya tiene encuesta.'], 422);
        }

        $encuesta = Encuesta::create([
            'dependencia_id' => $request->user()->dependencia_id,
            'reporte_id' => $reporte->id,
            'calificacion' => $data['calificacion'],
            'comentario' => $data['comentario'] ?? null,
            'respondido_por' => $data['respondido_por'] ?? $request->user()->nombre.' '.$request->user()->apellidos,
            'created_at' => now(),
        ]);

        return response()->json($encuesta->load('reporte'), 201);
    }

    public function show(Encuesta $encuesta)
    {
        return $encuesta->load('reporte');
    }
}
