<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReporteRequest;
use App\Models\Area;
use App\Models\Equipo;
use App\Models\MaterialCatalogo;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Services\InventarioService;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReporteController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $q = Reporte::with(['creador', 'responsable', 'ticket', 'servicio', 'ejecutores.usuario', 'materiales.material']);

        if ($request->filled('tipo')) {
            $q->where('tipo', $request->string('tipo'));
        }
        if ($request->filled('estatus')) {
            $q->where('estatus', $request->string('estatus'));
        }
        if ($request->filled('categoria')) {
            $q->where('categoria', $request->string('categoria'));
        }
        if ($request->filled('desde') || $request->filled('hasta')) {
            $q->whereBetween('fecha_inicio', [
                $request->date('desde')?->startOfDay() ?? now()->subYear()->startOfDay(),
                $request->date('hasta')?->endOfDay() ?? now()->endOfDay(),
            ]);
        }
        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(fn ($qq) => $qq
                ->where('folio', 'like', "%{$s}%")
                ->orWhere('desarrollo', 'like', "%{$s}%"));
        }

        return response()->json($q->orderByDesc('fecha_inicio')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(StoreReporteRequest $request, InventarioService $inv): JsonResponse
    {
        $this->authorize('create', Reporte::class);

        $data = $request->validated();

        // Límite mensual de reportes por licencia
        $dep = $request->user()->dependencia;
        $countMes = Reporte::whereYear('fecha_inicio', now()->year)
            ->whereMonth('fecha_inicio', now()->month)
            ->count();
        if ($countMes >= $dep->limite_reportes_mensuales) {
            return response()->json(['message' => 'Límite de reportes mensuales alcanzado'], 422);
        }

        // FKs validadas contra la dependencia activa
        $data['ticket_id'] = $this->validatedDependenciaId($request, 'ticket_id', \App\Models\Ticket::class);
        $data['servicio_id'] = $this->validatedDependenciaId($request, 'servicio_id', \App\Models\Servicio::class);
        $data['area_id'] = $this->validatedDependenciaId($request, 'area_id', Area::class);
        $data['equipo_id'] = $this->validatedDependenciaId($request, 'equipo_id', Equipo::class);
        $data['responsable_id'] = $this->validatedDependenciaId($request, 'responsable_id', Usuario::class);
        $data['reporte_origen_id'] = $this->validatedDependenciaId($request, 'reporte_origen_id', Reporte::class);

        $ejecutores = $this->validatedDependenciaUserIds($request, 'ejecutores');
        unset($data['ejecutores']);

        // Coherencia tipo ↔ origen
        if ($data['tipo'] === 'libre') {
            $data['ticket_id'] = null;
            $data['servicio_id'] = null;
        } elseif ($data['tipo'] === 'ticket') {
            $data['servicio_id'] = null;
            if ($data['ticket_id'] === null) {
                return response()->json(['message' => 'Un reporte de tipo ticket requiere ticket_id'], 422);
            }
        } elseif ($data['tipo'] === 'servicio') {
            $data['ticket_id'] = null;
            if ($data['servicio_id'] === null) {
                return response()->json(['message' => 'Un reporte de tipo servicio requiere servicio_id'], 422);
            }
        }

        $materiales = collect($request->input('materiales', []))
            ->map(fn ($m) => [
                'material' => MaterialCatalogo::find((int) $m['material_id']),
                'cantidad' => (float) $m['cantidad'],
            ])
            ->filter(fn ($m) => $m['material'] !== null)
            ->values();

        if ($request->input('materiales') !== null && count($materiales) !== count($request->input('materiales'))) {
            return response()->json(['message' => 'Algún material no existe en tu dependencia'], 422);
        }

        $reporte = DB::transaction(function () use ($request, $data, $ejecutores, $materiales, $inv) {
            $data['dependencia_id'] = $request->user()->dependencia_id;
            $data['creado_por'] = $request->user()->id;
            $data['folio'] = $data['folio'] ?? Reporte::generarFolio($request->user()->dependencia_id);
            $data['estatus'] = $data['estatus'] ?? 'abierto';

            $reporte = Reporte::create($data);

            foreach ($ejecutores as $uid) {
                $reporte->ejecutores()->create([
                    'dependencia_id' => $reporte->dependencia_id,
                    'usuario_id' => $uid,
                ]);
            }

            foreach ($materiales as $m) {
                $reporte->materiales()->create([
                    'dependencia_id' => $reporte->dependencia_id,
                    'material_id' => $m['material']->id,
                    'cantidad' => $m['cantidad'],
                    'costo_unitario' => $m['material']->costo_unitario,
                ]);
                $inv->registrarSalida(
                    $reporte->dependencia_id,
                    $m['material']->id,
                    $m['cantidad'],
                    'reporte',
                    $reporte->id,
                    $request->user()->id
                );
            }

            if ($request->hasFile('evidencias')) {
                foreach ($request->file('evidencias') as $file) {
                    $path = $file->store('evidencias/'.$reporte->dependencia_id, 'public');
                    $reporte->evidencias()->create([
                        'dependencia_id' => $reporte->dependencia_id,
                        'url' => Storage::url($path),
                        'tipo_archivo' => $file->getClientMimeType(),
                        'peso_kb' => (int) ($file->getSize() / 1024),
                        'subido_por' => $request->user()->id,
                    ]);
                }
            }

            return $reporte;
        });

        return response()->json($reporte->load(['ejecutores', 'materiales', 'evidencias']), 201);
    }

    public function show(Reporte $reporte): JsonResponse
    {
        $this->ensureOwnDependencia($reporte);

        return response()->json($reporte->load(['creador', 'responsable', 'ticket', 'servicio', 'area', 'equipo', 'ejecutores.usuario', 'materiales.material', 'evidencias', 'origen']));
    }

    public function update(Request $request, Reporte $reporte): JsonResponse
    {
        $this->authorize('update', $reporte);

        $data = $request->validate([
            'desarrollo' => 'sometimes|nullable|string',
            'estatus' => 'sometimes|in:abierto,parcial,finalizado,descartado',
            'categoria' => 'sometimes|in:preventivo,correctivo,diagnostico,instalacion,mejora',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date',
            'hora_salida' => 'sometimes|nullable|date',
            'hora_llegada' => 'sometimes|nullable|date',
            'hora_inicio_diagnostico' => 'sometimes|nullable|date',
            'hora_inicio_trabajo' => 'sometimes|nullable|date',
            'hora_fin_trabajo' => 'sometimes|nullable|date',
            'hora_regreso' => 'sometimes|nullable|date',
            'costo_mano_obra' => 'sometimes|numeric|min:0',
            'costo_materiales' => 'sometimes|numeric|min:0',
        ]);

        $reporte->update($data);

        return response()->json($reporte->load(['ejecutores', 'materiales']));
    }

    public function destroy(Reporte $reporte): JsonResponse
    {
        $this->authorize('delete', $reporte);
        $reporte->delete();

        return response()->json(['message' => 'deleted']);
    }

    public function conformidad(Request $request, Reporte $reporte): JsonResponse
    {
        $this->authorize('conformidad', $reporte);

        $data = $request->validate([
            'conformidad_estatus' => 'required|in:aprobado,rechazado',
            'conformidad_firmado_por' => 'required|string|max:150',
            'ftfr' => 'sometimes|boolean',
        ]);

        $reporte->update([
            'conformidad_estatus' => $data['conformidad_estatus'],
            'conformidad_firmado_por' => $data['conformidad_firmado_por'],
            'conformidad_fecha' => now(),
            'ftfr' => $data['ftfr'] ?? $reporte->ftfr,
        ]);

        return response()->json($reporte);
    }

    public function pdf(Reporte $reporte): JsonResponse
    {
        $this->ensureOwnDependencia($reporte);

        return response()->json(['message' => 'PDF generation to implement', 'reporte' => $reporte->load(['ejecutores.usuario', 'materiales.material'])]);
    }
}
