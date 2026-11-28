<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Equipo;
use App\Models\Servicio;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServicioController extends Controller
{
    private function authUser(Request $request): Usuario
    {
        $user = $request->user() ?? auth()->user();
        if (! $user instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

        return $user;
    }

    private function ensureOwnDependencia(Request $request, Model $model): void
    {
        if ((int) $model->getAttribute('dependencia_id') !== (int) $this->authUser($request)->dependencia_id) {
            abort(403, 'El recurso no pertenece a tu dependencia.');
        }
    }

    private function resolveFk(Request $request, string $field, string $modelClass): ?int
    {
        $id = $request->input($field);
        if ($id === null || $id === '') {
            return null;
        }

        $depId = $this->authUser($request)->dependencia_id;
        $found = $modelClass::withoutGlobalScopes()
            ->where('dependencia_id', $depId)
            ->find((int) $id);

        if ($found === null) {
            return null;
        }

        return (int) $found->getKey();
    }

    private function generarFolioSecuencial(int $dependenciaId): string
    {
        // Bloquea la fila de la empresa (dentro de la transacción del alta) para
        // que dos altas simultáneas no calculen el mismo consecutivo.
        \App\Models\Dependencia::withoutGlobalScopes()->whereKey($dependenciaId)->lockForUpdate()->first();

        $ahora = now('America/Mexico_City');
        $prefijo = 'SRV-'.$ahora->format('ymd');
        $prefijoLegacy = 'SRV-'.$ahora->format('Ymd');

        $ultimos = Servicio::withoutGlobalScopes()
            ->where('dependencia_id', $dependenciaId)
            ->where(function ($q) use ($prefijo, $prefijoLegacy) {
                $q->where('folio', 'like', $prefijo.'-%')
                    ->orWhere('folio', 'like', $prefijoLegacy.'-%');
            })
            ->pluck('folio');

        $maxSecuencia = 0;
        foreach ($ultimos as $f) {
            $num = (int) substr((string) $f, -4);
            if ($num > $maxSecuencia) {
                $maxSecuencia = $num;
            }
        }

        return sprintf('%s-%04d', $prefijo, $maxSecuencia + 1);
    }

    private function sincronizarServiciosConReportesFinalizados(int $depId): void
    {
        try {
            $ahora = now('America/Mexico_City')->format('Y-m-d H:i:s');
            DB::table('servicios')
                ->where('dependencia_id', $depId)
                ->where('folio', 'like', 'SRV-20%')
                ->update([
                    'folio' => DB::raw("CONCAT('SRV-', SUBSTRING(folio, 7))"),
                ]);
            DB::table('servicios')
                ->where('dependencia_id', $depId)
                ->whereIn('estatus', ['programado', 'en_proceso', 'vencido'])
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('reportes')
                        ->whereColumn('reportes.servicio_id', 'servicios.id')
                        ->where('reportes.estatus', 'finalizado')
                        ->whereNull('reportes.deleted_at');
                })
                ->update([
                    'estatus' => 'realizado',
                    'updated_at' => $ahora,
                ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if ($user && isset($user->dependencia_id) && (int) $request->get('page', 1) <= 1) {
            $this->sincronizarServiciosConReportesFinalizados((int) $user->dependencia_id);
        }

        $q = Servicio::with(['asignado', 'area', 'equipo']);

        if ($user && (int) $user->rol === 3) {
            $q->where('usuario_asignado_id', $user->id);
        }

        if ($request->filled('estatus')) {
            $q->where('estatus', $request->string('estatus'));
        }
        if ($request->filled('categoria')) {
            $q->where('categoria', $request->string('categoria'));
        }
        if ($request->filled('prioridad')) {
            $q->where('prioridad', $request->string('prioridad'));
        }
        if ($request->filled('area_id')) {
            $q->where('area_id', (int) $request->get('area_id'));
        }
        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(fn ($qq) => $qq
                ->where('folio', 'like', "%{$s}%")
                ->orWhere('asunto', 'like', "%{$s}%"));
        }

        return response()->json($q->orderBy('fecha_vencimiento')->orderBy('id')->paginate(min(max((int) $request->get('per_page', 15), 1), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Servicio::class);

        if ($request->filled('prioridad')) {
            $request->merge(['prioridad' => strtolower((string) $request->input('prioridad'))]);
        }

        $data = $request->validate([
            'folio' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:~^[A-Za-z0-9][A-Za-z0-9._/-]*$~'],
            'asunto' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'categoria' => 'nullable|in:preventivo,correctivo,instalacion,mejora,diagnostico',
            'fecha_asignacion' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date',
            'prioridad' => 'nullable|in:alta,media,baja',
            'solicitante' => 'nullable|string|max:150',
            'cargo' => 'nullable|string|max:150',
            'usuario_asignado_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
            'equipo_id' => 'nullable|integer',
        ]);

        $user = $this->authUser($request);
        $depId = (int) $user->dependencia_id;

        $data['dependencia_id'] = $depId;
        $data['usuario_asignado_id'] = $this->resolveFk($request, 'usuario_asignado_id', Usuario::class);
        $data['area_id'] = $this->resolveFk($request, 'area_id', Area::class);
        $data['equipo_id'] = $this->resolveFk($request, 'equipo_id', Equipo::class);
        $data['estatus'] = $data['estatus'] ?? 'programado';

        $servicio = DB::transaction(function () use ($data, $depId) {
            $folioActual = trim((string) ($data['folio'] ?? ''));
            if ($folioActual === '' || preg_match('/^SRV-\d{6}$/', $folioActual)) {
                $data['folio'] = $this->generarFolioSecuencial($depId);
            }

            $srv = new Servicio;
            $srv->forceFill($data);
            $srv->save();

            return $srv;
        });

        return response()->json($servicio->load(['asignado', 'area', 'equipo']), 201);
    }

    public function show(Request $request, Servicio $servicio): JsonResponse
    {
        $this->ensureOwnDependencia($request, $servicio);
        $this->sincronizarServiciosConReportesFinalizados((int) $servicio->dependencia_id);
        $servicio->refresh();

        return response()->json($servicio->load(['asignado', 'area', 'equipo', 'historial', 'reportes']));
    }

    public function update(Request $request, Servicio $servicio): JsonResponse
    {
        $this->authorize('update', $servicio);

        if ($request->filled('prioridad')) {
            $request->merge(['prioridad' => strtolower((string) $request->input('prioridad'))]);
        }

        $data = $request->validate([
            'asunto' => 'sometimes|string|max:200',
            'descripcion' => 'sometimes|nullable|string',
            'solicitante' => 'sometimes|nullable|string|max:150',
            'cargo' => 'sometimes|nullable|string|max:150',
            'categoria' => 'sometimes|in:preventivo,correctivo,instalacion,mejora,diagnostico',
            'fecha_asignacion' => 'sometimes|nullable|date',
            'fecha_vencimiento' => 'sometimes|nullable|date',
            'estatus' => 'sometimes|in:programado,en_proceso,realizado,vencido',
            'prioridad' => 'sometimes|in:alta,media,baja',
            'usuario_asignado_id' => 'sometimes|nullable|integer',
            'area_id' => 'sometimes|nullable|integer',
            'equipo_id' => 'sometimes|nullable|integer',
        ]);

        if (array_key_exists('usuario_asignado_id', $data)) {
            $data['usuario_asignado_id'] = $this->resolveFk($request, 'usuario_asignado_id', Usuario::class);
        }
        if (array_key_exists('area_id', $data)) {
            $data['area_id'] = $this->resolveFk($request, 'area_id', Area::class);
        }
        if (array_key_exists('equipo_id', $data)) {
            $data['equipo_id'] = $this->resolveFk($request, 'equipo_id', Equipo::class);
        }

        $servicio->forceFill($data)->save();

        return response()->json($servicio->load(['asignado', 'area', 'equipo']));
    }

    public function destroy(Servicio $servicio): JsonResponse
    {
        $this->authorize('delete', $servicio);
        $servicio->delete();

        return response()->json(['message' => 'deleted']);
    }
}
