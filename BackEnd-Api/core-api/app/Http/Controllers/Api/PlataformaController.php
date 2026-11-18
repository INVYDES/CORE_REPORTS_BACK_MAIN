<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Licencia;
use App\Models\PagoLicencia;
use App\Models\Suscripcion;
use App\Models\Usuario;
use App\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlataformaController extends Controller
{
    public function resumen(): JsonResponse
    {
        $hoy = now()->startOfDay();

        $total = $this->dependencias()->count();
        $activas = $this->dependencias()
            ->where('activa', true)
            ->where(fn (Builder $q) => $q->whereNull('fecha_expiracion')->orWhereDate('fecha_expiracion', '>=', $hoy))
            ->count();
        $vencidas = $this->dependencias()
            ->where('activa', true)
            ->whereNotNull('fecha_expiracion')
            ->whereDate('fecha_expiracion', '<', $hoy)
            ->count();
        $desactivadas = $this->dependencias()->where('activa', false)->count();
        $trial = $this->dependencias()->where('tipo_licencia', 'trial')->count();

        $usuarios = Usuario::whereHas('dependencia', fn (Builder $q) => $q->where('es_plataforma', false))->count();
        $ingresosMes = (float) PagoLicencia::where('estado', 'aprobado')
            ->whereBetween('pagado_en', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('monto');

        $ultimosPagos = PagoLicencia::with(['dependencia:id,nombre', 'licencia:id,nombre'])
            ->latest('pagado_en')
            ->limit(5)
            ->get()
            ->map(fn (PagoLicencia $pago) => $this->formatearPago($pago));

        return response()->json([
            'data' => [
                'total_dependencias' => $total,
                'activas' => $activas,
                'vencidas' => $vencidas,
                'desactivadas' => $desactivadas,
                'trial' => $trial,
                'usuarios' => $usuarios,
                'ingresos_mes' => $ingresosMes,
                'ultimos_pagos' => $ultimosPagos,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $hoy = now()->startOfDay();

        $query = $this->dependencias()
            ->with('suscripcion')
            ->withCount($this->conteos());

        if ($request->filled('q')) {
            $texto = '%'.$request->string('q').'%';
            $query->where(fn (Builder $q) => $q
                ->where('nombre', 'like', $texto)
                ->orWhere('rfc', 'like', $texto)
                ->orWhere('correo_contacto', 'like', $texto)
                ->orWhere('correo_reportes', 'like', $texto));
        }

        match ($request->get('estado')) {
            'activa' => $query->where('activa', true)
                ->where(fn (Builder $q) => $q->whereNull('fecha_expiracion')->orWhereDate('fecha_expiracion', '>=', $hoy)),
            'vencida' => $query->where('activa', true)
                ->whereNotNull('fecha_expiracion')
                ->whereDate('fecha_expiracion', '<', $hoy),
            'desactivada' => $query->where('activa', false),
            'trial' => $query->where('tipo_licencia', 'trial'),
            default => null,
        };

        $pagina = $query->orderByDesc('created_at')->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Dependencia $dep) => $this->formatearDependencia($dep)),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
                'last_page' => $pagina->lastPage(),
            ],
        ]);
    }

    public function show(int $dependencia): JsonResponse
    {
        $dep = $this->dependencias()
            ->with('suscripcion')
            ->withCount($this->conteos())
            ->findOrFail($dependencia);

        $usuarios = $dep->usuarios()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellidos', 'email', 'rol', 'estado', 'ultimo_acceso']);

        $pagos = PagoLicencia::with('licencia:id,nombre')
            ->where('dependencia_id', $dep->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (PagoLicencia $pago) => $this->formatearPago($pago));

        return response()->json([
            'data' => array_merge($this->formatearDependencia($dep), [
                'usuarios' => $usuarios,
                'pagos' => $pagos,
            ]),
        ]);
    }

    public function updateLicencia(Request $request, int $dependencia): JsonResponse
    {
        $dep = $this->dependencias()->findOrFail($dependencia);

        $data = $request->validate([
            'tipo_licencia' => 'sometimes|in:trial,mensual,anual',
            'fecha_expiracion' => 'sometimes|nullable|date',
            'limite_usuarios' => 'sometimes|integer|min:1|max:99999',
            'limite_reportes_mensuales' => 'sometimes|integer|min:0',
            'activa' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($dep, $data) {
            $dep->fill($data)->save();
            $this->sincronizarSuscripcion($dep);
        });

        return response()->json([
            'message' => 'Licencia actualizada.',
            'data' => $this->formatearDependencia($this->recargar($dep)),
        ]);
    }

    public function toggleActiva(int $dependencia): JsonResponse
    {
        $dep = $this->dependencias()->findOrFail($dependencia);

        DB::transaction(function () use ($dep) {
            $dep->activa = ! $dep->activa;
            $dep->save();
            $this->sincronizarSuscripcion($dep);
        });

        return response()->json([
            'message' => $dep->activa ? 'Dependencia activada.' : 'Dependencia desactivada.',
            'data' => $this->formatearDependencia($this->recargar($dep)),
        ]);
    }

    public function destroy(int $dependencia): JsonResponse
    {
        $dep = $this->dependencias()->findOrFail($dependencia);

        DB::transaction(function () use ($dep) {
            $usuarios = Usuario::where('dependencia_id', $dep->id)->get();
            foreach ($usuarios as $usuario) {
                $usuario->tokens()->delete();
            }

            Usuario::where('dependencia_id', $dep->id)->update(['estado' => false]);

            if ($dep->suscripcion) {
                $dep->suscripcion->update(['estado' => 'cancelada']);
            }

            $dep->delete();
        });

        return response()->json(['message' => 'Dependencia eliminada.']);
    }

    public function pagos(Request $request): JsonResponse
    {
        $query = PagoLicencia::with(['dependencia:id,nombre', 'licencia:id,nombre']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('q')) {
            $texto = '%'.$request->string('q').'%';
            $query->whereHas('dependencia', fn (Builder $q) => $q->where('nombre', 'like', $texto));
        }

        $pagina = $query->latest()->paginate(min(max((int) $request->get('per_page', 20), 1), 100));

        return response()->json([
            'data' => collect($pagina->items())->map(fn (PagoLicencia $pago) => $this->formatearPago($pago)),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
                'last_page' => $pagina->lastPage(),
            ],
        ]);
    }

    public function catalogo(): JsonResponse
    {
        return response()->json([
            'data' => Licencia::orderBy('precio')->get(),
        ]);
    }

    public function updateCatalogo(Request $request, Licencia $licencia): JsonResponse
    {
        $data = $request->validate([
            'nombre' => 'sometimes|string|max:150',
            'precio' => 'sometimes|numeric|min:0',
            'precio_anual' => 'sometimes|nullable|numeric|min:0',
            'max_usuarios' => 'sometimes|integer|min:1|max:99999',
            'max_reportes_mensuales' => 'sometimes|integer|min:0',
            'dias_prueba' => 'sometimes|integer|min:0|max:365',
            'descripcion' => 'sometimes|nullable|string|max:500',
            'activo' => 'sometimes|boolean',
        ]);

        $licencia->update($data);

        return response()->json([
            'message' => 'Plan actualizado.',
            'data' => $licencia->fresh(),
        ]);
    }

    private function dependencias(): Builder
    {
        return Dependencia::query()->where('es_plataforma', false);
    }

    private function sincronizarSuscripcion(Dependencia $dep): void
    {
        $suscripcion = $dep->suscripcion ?: new Suscripcion(['dependencia_id' => $dep->id]);

        $suscripcion->proveedor = $suscripcion->proveedor ?: 'manual';
        $suscripcion->plan = $dep->tipo_licencia;
        $suscripcion->estado = $this->estadoSuscripcion($dep);
        $suscripcion->limite_usuarios = $dep->limite_usuarios;
        $suscripcion->limite_reportes_mensuales = $dep->limite_reportes_mensuales;
        $suscripcion->fecha_inicio = $suscripcion->fecha_inicio ?: now()->toDateString();
        $suscripcion->fecha_expiracion = $dep->fecha_expiracion;
        $suscripcion->save();
    }

    private function conteos(): array
    {
        return [
            'usuarios',
            'tickets as tickets_count' => fn (Builder $q) => $q->withoutGlobalScope(DependenciaScope::class),
            'servicios as servicios_count' => fn (Builder $q) => $q->withoutGlobalScope(DependenciaScope::class),
            'reportes as reportes_count' => fn (Builder $q) => $q->withoutGlobalScope(DependenciaScope::class),
        ];
    }

    private function recargar(Dependencia $dep): Dependencia
    {
        return $this->dependencias()
            ->with('suscripcion')
            ->withCount($this->conteos())
            ->findOrFail($dep->id);
    }

    private function estadoSuscripcion(Dependencia $dep): string
    {
        if (! $dep->activa) {
            return 'suspendida';
        }

        if ($dep->tipo_licencia === 'trial') {
            return 'trial';
        }

        if ($dep->fecha_expiracion && $dep->fecha_expiracion->copy()->endOfDay()->isPast()) {
            return 'vencida';
        }

        return 'activa';
    }

    private function formatearDependencia(Dependencia $dep): array
    {
        $vencida = $dep->fecha_expiracion !== null
            && $dep->fecha_expiracion->copy()->endOfDay()->isPast();

        return [
            'id' => $dep->id,
            'nombre' => $dep->nombre,
            'rfc' => $dep->rfc,
            'correo_contacto' => $dep->correo_contacto,
            'correo_reportes' => $dep->correo_reportes,
            'telefono' => $dep->telefono,
            'tipo_licencia' => $dep->tipo_licencia,
            'fecha_expiracion' => $dep->fecha_expiracion?->format('Y-m-d'),
            'limite_usuarios' => $dep->limite_usuarios,
            'limite_reportes_mensuales' => $dep->limite_reportes_mensuales,
            'activa' => $dep->activa,
            'vencida' => $vencida,
            'usuarios_count' => $dep->usuarios_count ?? null,
            'tickets_count' => $dep->tickets_count ?? null,
            'servicios_count' => $dep->servicios_count ?? null,
            'reportes_count' => $dep->reportes_count ?? null,
            'suscripcion' => $dep->suscripcion ? [
                'plan' => $dep->suscripcion->plan,
                'estado' => $dep->suscripcion->estado,
                'proveedor' => $dep->suscripcion->proveedor,
                'fecha_inicio' => $dep->suscripcion->fecha_inicio?->format('Y-m-d'),
                'fecha_expiracion' => $dep->suscripcion->fecha_expiracion?->format('Y-m-d'),
            ] : null,
            'created_at' => $dep->created_at?->toIso8601String(),
        ];
    }

    private function formatearPago(PagoLicencia $pago): array
    {
        return [
            'id' => $pago->id,
            'dependencia' => $pago->dependencia?->nombre,
            'licencia' => $pago->licencia?->nombre,
            'estado' => $pago->estado,
            'metodo' => $pago->metodo,
            'monto' => (float) $pago->monto,
            'moneda' => $pago->moneda,
            'payment_id' => $pago->payment_id,
            'pagado_en' => $pago->pagado_en?->toIso8601String(),
            'created_at' => $pago->created_at?->toIso8601String(),
        ];
    }
}
