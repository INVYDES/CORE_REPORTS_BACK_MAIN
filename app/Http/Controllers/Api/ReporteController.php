<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ReportePdfMail;
use App\Models\Area;
use App\Models\Equipo;
use App\Models\MaterialCatalogo;
use App\Models\Reporte;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\InventarioService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
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

    /**
     * @return int[]
     */
    private function resolveUserIds(Request $request, string $field = 'ejecutores'): array
    {
        $ids = $request->input($field);
        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $depId = $this->authUser($request)->dependencia_id;
        $solicitados = array_values(array_unique(array_map('intval', $ids)));

        $encontrados = Usuario::where('dependencia_id', $depId)
            ->whereIn('id', $solicitados)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($encontrados) !== count($solicitados)) {
            throw ValidationException::withMessages([
                'ejecutores' => 'Uno o más ejecutores no pertenecen a tu dependencia.',
            ]);
        }

        return $encontrados;
    }

    private function generarFolioSecuencial(int $dependenciaId): string
    {
        $ahora = now('America/Mexico_City');
        $prefijo = 'REP-'.$ahora->format('ymd');
        $prefijoLegacy = 'REP-'.$ahora->format('Ymd');

        $ultimos = Reporte::withoutGlobalScopes()
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

    /**
     * Sincroniza directamente el estatus del Ticket o Servicio origen cuando se crea o edita un reporte,
     * sin depender de que ReporteObserver esté registrado en producción.
     */
    private function sincronizarOrigen(Reporte $reporte): void
    {
        try {
            $ahora = now('America/Mexico_City')->format('Y-m-d H:i:s');

            if ($reporte->ticket_id) {
                $ticket = Ticket::withoutGlobalScopes()->find((int) $reporte->ticket_id);
                if ($ticket) {
                    $fill = [
                        'fecha_atencion' => $ticket->fecha_atencion ?? $ahora,
                    ];
                    if ($reporte->estatus === 'finalizado') {
                        $fill['estatus'] = 'resuelto';
                        if (Schema::hasColumn('tickets', 'fecha_cierre')) {
                            $fill['fecha_cierre'] = $ticket->fecha_cierre ?? $ahora;
                        }
                    } elseif (in_array($reporte->estatus, ['abierto', 'parcial'], true) && $ticket->estatus === 'abierto') {
                        $fill['estatus'] = 'en_proceso';
                    }
                    $ticket->forceFill($fill)->save();
                }
            }

            if ($reporte->servicio_id) {
                $servicio = Servicio::withoutGlobalScopes()->find((int) $reporte->servicio_id);
                if ($servicio) {
                    if ($reporte->estatus === 'finalizado') {
                        $servicio->forceFill([
                            'estatus' => 'realizado',
                        ])->save();
                    } elseif (in_array($reporte->estatus, ['abierto', 'parcial'], true) && $servicio->estatus === 'programado') {
                        $servicio->forceFill([
                            'estatus' => 'en_proceso',
                        ])->save();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error en sincronizarOrigen: ' . $e->getMessage());
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if ($user && isset($user->dependencia_id)) {
            try {
                DB::table('reportes')
                    ->where('dependencia_id', (int) $user->dependencia_id)
                    ->where('folio', 'like', 'REP-20%')
                    ->update([
                        'folio' => DB::raw("CONCAT('REP-', SUBSTRING(folio, 7))"),
                    ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $q = $this->filteredQuery($request)
            ->with(['creador', 'responsable', 'ticket', 'servicio', 'ejecutores.usuario', 'materiales.material']);

        return response()->json($q->orderByDesc('fecha_inicio')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function export(Request $request): Response
    {
        $request->validate(['formato' => 'nullable|in:csv,xlsx']);

        $formato = (string) $request->get('formato', 'csv');
        $query = $this->filteredQuery($request)
            ->with(['creador', 'responsable', 'ticket', 'servicio', 'area', 'equipo', 'ejecutores.usuario', 'materiales.material', 'evidencias'])
            ->orderByDesc('fecha_inicio');

        $headers = [
            'Folio', 'Tipo', 'Origen', 'Categoria', 'Estatus', 'Fecha inicio', 'Fecha fin',
            'Area', 'Equipo', 'Responsable', 'Creado por', 'Ejecutores', 'Desarrollo',
            'Mano de obra', 'Materiales', 'Costo materiales', 'Total', 'Evidencias',
            'URL evidencias', 'Conformidad', 'Firmado por',
        ];

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $reporte) {
                yield $this->exportRow($reporte);
            }
        })();

        $sufijo = now()->format('Ymd-His');

        if ($formato === 'xlsx' && class_exists(\App\Support\XlsxWriter::class)) {
            return response()->streamDownload(function () use ($headers, $rows): void {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                \App\Support\XlsxWriter::stream($out, 'Reportes', $headers, $rows);
                fclose($out);
            }, 'reportes-'.$sufijo.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'safeCsvCell'], $row), ',', '"', '\\');
            }

            fclose($out);
        }, 'reportes-'.$sufijo.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function exportRow(Reporte $r): array
    {
        $evidencias = $r->evidencias;
        $cleanDesarrollo = preg_replace('/\n?<!--CORE_META:[A-Za-z0-9+\/=]+-->\s*$/', '', (string) ($r->desarrollo ?? ''));

        return [
            $r->folio,
            $r->tipo,
            $r->ticket?->folio ?: ($r->servicio?->folio ?: ''),
            $r->categoria,
            $r->estatus,
            $r->fecha_inicio?->format('Y-m-d H:i') ?? '',
            $r->fecha_fin?->format('Y-m-d H:i') ?? '',
            $r->area?->nombre ?: '',
            $r->equipo?->nombre ?: '',
            $r->responsable ? trim($r->responsable->nombre.' '.$r->responsable->apellidos) : '',
            $r->creador ? trim($r->creador->nombre.' '.$r->creador->apellidos) : '',
            $r->ejecutores->map(fn ($e) => $e->usuario ? trim($e->usuario->nombre.' '.$e->usuario->apellidos) : null)->filter()->implode(', '),
            $cleanDesarrollo,
            (float) $r->costo_mano_obra,
            $r->materiales->count(),
            (float) $r->costo_materiales,
            (float) $r->costo_mano_obra + (float) $r->costo_materiales,
            $evidencias->count(),
            $evidencias->map(fn ($e) => $e->url)->filter()->implode(' '),
            $r->conformidad_estatus ?? '',
            $r->conformidad_firmado_por ?? '',
        ];
    }

    /** @return Builder<Reporte> */
    private function filteredQuery(Request $request): Builder
    {
        $q = Reporte::query();

        $user = $request->user() ?? auth()->user();
        if ($user && (int) $user->rol === 3) {
            $q->where('creado_por', $user->id);
        } elseif ($request->filled('creado_por')) {
            $q->where('creado_por', (int) $request->get('creado_por'));
        }

        if ($request->filled('tipo')) {
            $q->where('tipo', $request->string('tipo'));
        }
        if ($request->filled('estatus')) {
            $q->where('estatus', $request->string('estatus'));
        }
        if ($request->filled('categoria')) {
            $q->where('categoria', $request->string('categoria'));
        }
        if ($request->filled('area_id')) {
            $q->where('area_id', (int) $request->get('area_id'));
        }
        if ($request->filled('equipo_id')) {
            $q->where('equipo_id', (int) $request->get('equipo_id'));
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

        return $q;
    }

    private function safeCsvCell(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }

    public function store(Request $request, InventarioService $inv): JsonResponse
    {
        try {
            $this->authorize('create', Reporte::class);

        $data = $request->validate([
            'folio' => 'sometimes|nullable|string|max:50',
            'tipo' => 'required|in:ticket,servicio,libre',
            'ticket_id' => 'nullable|integer',
            'servicio_id' => 'nullable|integer',
            'categoria' => 'nullable|in:preventivo,correctivo,diagnostico,instalacion,mejora',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'desarrollo' => 'nullable|string',
            'estatus' => 'sometimes|in:abierto,parcial,finalizado,descartado',
            'area_id' => 'nullable|integer',
            'equipo_id' => 'nullable|integer',
            'responsable_id' => 'nullable|integer',
            'hora_salida' => 'nullable|date',
            'hora_llegada' => 'nullable|date',
            'hora_inicio_diagnostico' => 'nullable|date',
            'hora_inicio_trabajo' => 'nullable|date',
            'hora_fin_trabajo' => 'nullable|date',
            'hora_regreso' => 'nullable|date',
            'es_retrabajo' => 'sometimes|boolean',
            'reporte_origen_id' => 'nullable|integer',
            'costo_mano_obra' => 'sometimes|numeric|min:0',
            'costo_materiales' => 'sometimes|numeric|min:0',
            'ejecutores' => 'sometimes|array',
            'ejecutores.*' => 'integer',
            'materiales' => 'sometimes|array',
            'materiales.*.material_id' => 'required_with:materiales|integer',
            'materiales.*.cantidad' => 'required_with:materiales|numeric|min:0.01',
            'evidencias' => 'sometimes|array|max:20',
            'evidencias.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,csv,xls,xlsx|max:10240',
            'evidenciaFotografica' => [
                'sometimes',
                'array',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach ((array) $value as $item) {
                        $url = is_array($item) ? ($item['url'] ?? null) : $item;
                        $url = is_string($url) ? trim($url) : '';

                        if ($url === '') {
                            $fail('La evidencia fotográfica no es válida.');

                            return;
                        }

                        if (preg_match('/^data:/i', $url)) {
                            if (! preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64,[A-Za-z0-9+/=\s]+$#i', $url)) {
                                $fail('La evidencia debe ser una imagen válida (JPEG, PNG, GIF o WEBP).');
                            }

                            continue;
                        }

                        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
                        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                            $fail('La evidencia debe ser una URL válida o una imagen en base64.');

                            return;
                        }
                    }
                },
            ],
        ]);

        $user = $this->authUser($request);
        $dep = $user->dependencia;
        if ($dep) {
            $countMes = Reporte::whereYear('fecha_inicio', now()->year)
                ->whereMonth('fecha_inicio', now()->month)
                ->count();
            if ($countMes >= $dep->limite_reportes_mensuales) {
                return response()->json(['message' => 'Límite de reportes mensuales alcanzado'], 422);
            }
        }

        $data['ticket_id'] = $this->resolveFk($request, 'ticket_id', Ticket::class);
        $data['servicio_id'] = $this->resolveFk($request, 'servicio_id', Servicio::class);
        $data['area_id'] = $this->resolveFk($request, 'area_id', Area::class);
        $data['equipo_id'] = $this->resolveFk($request, 'equipo_id', Equipo::class);
        $data['responsable_id'] = $this->resolveFk($request, 'responsable_id', Usuario::class);
        $data['reporte_origen_id'] = $this->resolveFk($request, 'reporte_origen_id', Reporte::class);

        $ejecutores = $this->resolveUserIds($request, 'ejecutores');
        unset($data['ejecutores'], $data['materiales'], $data['evidencias'], $data['evidenciaFotografica']);

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

        $depId = (int) $user->dependencia_id;
        $materiales = collect((array) $request->input('materiales', []))
            ->map(function ($m) use ($depId) {
                $materialId = (int) ($m['material_id'] ?? 0);
                $material = MaterialCatalogo::where('dependencia_id', $depId)->find($materialId);

                if ($material === null) {
                    throw ValidationException::withMessages([
                        'materiales' => "El material {$materialId} no pertenece a tu dependencia.",
                    ]);
                }

                return [
                    'material' => $material,
                    'cantidad' => (float) ($m['cantidad'] ?? 1),
                ];
            })
            ->values();

        $archivos = (array) $request->file('evidencias', []);
        $totalEvidencias = count($archivos) + count((array) $request->input('evidenciaFotografica', []));
        if ($totalEvidencias > 20) {
            throw ValidationException::withMessages([
                'evidencias' => 'Puedes adjuntar como maximo 20 archivos por reporte.',
            ]);
        }

        $hasRutaColumn = Schema::hasColumn('reporte_evidencias', 'ruta');

        $reporte = DB::transaction(function () use ($request, $user, $data, $ejecutores, $materiales, $inv, $hasRutaColumn) {
            $depId = (int) $user->dependencia_id;
            $data['dependencia_id'] = $depId;
            $data['creado_por'] = $user->id;

            $folioActual = trim((string) ($data['folio'] ?? ''));
            if ($folioActual === '' || preg_match('/^REP-\d{6}$/', $folioActual)) {
                $data['folio'] = $this->generarFolioSecuencial($depId);
            }
            $data['estatus'] = $data['estatus'] ?? 'abierto';

            $reporte = new Reporte;
            $reporte->forceFill($data);
            $reporte->save();

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
                    $user->id
                );
            }

            $carpetaEvidencias = 'evidencias/'.$reporte->dependencia_id;

            if ($request->hasFile('evidencias')) {
                foreach ($request->file('evidencias') as $file) {
                    $mime = $file->getMimeType() ?: 'application/octet-stream';
                    $isImage = str_starts_with($mime, 'image/');
                    $carpeta = $carpetaEvidencias.'/'.($isImage ? 'imagenes' : 'documentos');
                    $path = $file->store($carpeta, 'public');
                    $url = Storage::disk('public')->url($path);

                    $evPayload = [
                        'dependencia_id' => $reporte->dependencia_id,
                        'url' => $url,
                        'tipo_archivo' => $mime,
                        'peso_kb' => (int) ceil($file->getSize() / 1024),
                        'subido_por' => $user->id,
                    ];
                    if ($hasRutaColumn) {
                        $evPayload['ruta'] = $path;
                        $evPayload['disco'] = 'public';
                    }
                    $reporte->evidencias()->create($evPayload);
                }
            }

            $evidenciasInput = $request->input('evidenciaFotografica');
            if (is_array($evidenciasInput)) {
                foreach ($evidenciasInput as $evi) {
                    $valor = is_string($evi) ? $evi : ($evi['url'] ?? null);
                    $valor = is_string($valor) ? trim($valor) : null;

                    if ($valor === null || $valor === '') {
                        continue;
                    }

                    $ruta = null;
                    if (preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64,(.+)$#is', $valor, $m)) {
                        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                        $mime = 'image/'.($ext === 'jpg' ? 'jpeg' : $ext);
                        $bytes = base64_decode($m[2], true);
                        if ($bytes === false) {
                            continue;
                        }
                        $filename = $carpetaEvidencias.'/imagenes/'.uniqid('ev_', true).'.'.$ext;
                        Storage::disk('public')->put($filename, $bytes);
                        $ruta = $filename;
                        $url = Storage::disk('public')->url($filename);
                        $pesoKb = (int) ceil(strlen($bytes) / 1024);
                    } else {
                        $url = $valor;
                        $mime = is_array($evi) ? ($evi['tipoArchivo'] ?? $evi['tipo_archivo'] ?? null) : null;
                        $pesoKb = 0;
                    }

                    $evPayload = [
                        'dependencia_id' => $reporte->dependencia_id,
                        'url' => $url,
                        'tipo_archivo' => $mime,
                        'peso_kb' => $pesoKb,
                        'subido_por' => $user->id,
                    ];
                    if ($hasRutaColumn && $ruta) {
                        $evPayload['ruta'] = $ruta;
                        $evPayload['disco'] = 'public';
                    }
                    $reporte->evidencias()->create($evPayload);
                }
            }

            $this->sincronizarOrigen($reporte);

            return $reporte;
        });

        // Enviar reporte por correo si fue solicitado
        $destinatarios = array_values(array_filter(
            array_map('trim', (array) $request->input('destinatarios_correo', [])),
            fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL)
        ));

        $correoEnviado = false;
        $correoError = null;
        if ($request->boolean('enviar_correo') && !empty($destinatarios)) {
            try {
                $pdfContent = $this->renderPdfObject($reporte)->output();
                $comentario = $request->input('comentario_correo');
                Mail::to($destinatarios)->send(new ReportePdfMail($reporte, $pdfContent, $comentario));
                $correoEnviado = true;
            } catch (\Throwable $e) {
                Log::error('Error al enviar correo del reporte: ' . $e->getMessage(), [
                    'reporte_id' => $reporte->id,
                    'destinatarios' => $destinatarios,
                ]);
                $correoError = $e->getMessage();
            }
        }

        $resData = $reporte->load(['ejecutores', 'materiales', 'evidencias'])->toArray();
        $resData['correo_enviado'] = $correoEnviado;
        $resData['correo_error'] = $correoError;

        return response()->json($resData, 201);
    } catch (\Illuminate\Validation\ValidationException $ve) {
        throw $ve;
    } catch (\Illuminate\Auth\Access\AuthorizationException $ae) {
        return response()->json([
            'success' => false,
            'message' => 'No tienes permisos para crear reportes.',
        ], 403);
    } catch (\Throwable $e) {
        Log::error('Error al guardar reporte: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error al guardar reporte: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
}

    public function show(Request $request, Reporte $reporte): JsonResponse
    {
        try {
            $this->ensureOwnDependencia($request, $reporte);

            $relations = ['creador', 'responsable', 'ticket', 'servicio', 'area', 'equipo', 'ejecutores.usuario', 'materiales.material', 'evidencias'];
            if (method_exists($reporte, 'origen')) {
                $relations[] = 'origen';
            }
            $reporte->loadMissing($relations);

            return response()->json($reporte);
        } catch (\Throwable $e) {
            Log::error('Error en ReporteController::show: ' . $e->getMessage(), [
                'exception' => $e,
                'reporte_id' => $reporte->id ?? null,
            ]);

            return response()->json([
                'message' => 'Error al cargar reporte: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function update(Request $request, Reporte $reporte): JsonResponse
    {
        $this->authorize('update', $reporte);

        $data = $request->validate([
            'desarrollo' => 'sometimes|nullable|string',
            'estatus' => 'sometimes|in:abierto,parcial,finalizado,descartado',
            'categoria' => 'sometimes|in:preventivo,correctivo,diagnostico,instalacion,mejora',
            'area_id' => 'sometimes|nullable|integer',
            'es_retrabajo' => 'sometimes|boolean',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|nullable|date',
            'hora_salida' => 'sometimes|nullable|date',
            'hora_llegada' => 'sometimes|nullable|date',
            'hora_inicio_diagnostico' => 'sometimes|nullable|date',
            'hora_inicio_trabajo' => 'sometimes|nullable|date',
            'hora_fin_trabajo' => 'sometimes|nullable|date',
            'hora_regreso' => 'sometimes|nullable|date',
            'costo_mano_obra' => 'sometimes|numeric|min:0',
            'costo_materiales' => 'sometimes|numeric|min:0',
            'ejecutores' => 'sometimes|array',
            'ejecutores.*' => 'integer',
            'keep_evidencias' => 'sometimes|array',
            'keep_evidencias.*' => 'integer',
            'evidencias' => 'sometimes|array|max:20',
            'evidencias.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,csv,xls,xlsx|max:10240',
        ]);

        if (array_key_exists('fecha_fin', $data) && $data['fecha_fin'] === null) {
            unset($data['fecha_fin']);
        }
        if (array_key_exists('area_id', $data)) {
            $data['area_id'] = $this->resolveFk($request, 'area_id', Area::class);
        }

        unset($data['ejecutores'], $data['keep_evidencias'], $data['evidencias']);

        $user = $this->authUser($request);
        $hasRutaColumn = Schema::hasColumn('reporte_evidencias', 'ruta');

        DB::transaction(function () use ($request, $reporte, $data, $user, $hasRutaColumn) {
            $reporte->forceFill($data)->save();
            $this->sincronizarOrigen($reporte);

            if ($request->boolean('sync_ejecutores') || $request->has('ejecutores')) {
                $ejecutores = $this->resolveUserIds($request, 'ejecutores');
                $reporte->ejecutores()->delete();
                foreach ($ejecutores as $uid) {
                    $reporte->ejecutores()->create([
                        'dependencia_id' => $reporte->dependencia_id,
                        'usuario_id' => $uid,
                    ]);
                }
            }

            if ($request->boolean('sync_evidencias')) {
                $keepIds = array_map('intval', (array) $request->input('keep_evidencias', []));
                if (empty($keepIds)) {
                    $reporte->evidencias()->delete();
                } else {
                    $reporte->evidencias()->whereNotIn('id', $keepIds)->delete();
                }
            }

            if ($request->hasFile('evidencias')) {
                $carpetaEvidencias = 'evidencias/'.$reporte->dependencia_id;
                foreach ($request->file('evidencias') as $file) {
                    $mime = $file->getMimeType() ?: 'application/octet-stream';
                    $isImage = str_starts_with($mime, 'image/');
                    $carpeta = $carpetaEvidencias.'/'.($isImage ? 'imagenes' : 'documentos');
                    $path = $file->store($carpeta, 'public');
                    $url = Storage::disk('public')->url($path);

                    $evPayload = [
                        'dependencia_id' => $reporte->dependencia_id,
                        'url' => $url,
                        'tipo_archivo' => $mime,
                        'peso_kb' => (int) ceil($file->getSize() / 1024),
                        'subido_por' => $user->id,
                    ];
                    if ($hasRutaColumn) {
                        $evPayload['ruta'] = $path;
                        $evPayload['disco'] = 'public';
                    }
                    $reporte->evidencias()->create($evPayload);
                }
            }
        });

        return response()->json(
            $reporte->load(['creador', 'responsable', 'ticket', 'servicio', 'area', 'equipo', 'ejecutores.usuario', 'materiales.material', 'evidencias', 'origen'])
        );
    }

    public function destroy(Request $request, Reporte $reporte, InventarioService $inv): JsonResponse
    {
        $this->authorize('delete', $reporte);
        $user = $this->authUser($request);

        DB::transaction(function () use ($user, $reporte, $inv): void {
            foreach ($reporte->materiales()->get() as $item) {
                try {
                    $inv->revertirSalida(
                        $reporte->dependencia_id,
                        (int) $item->material_id,
                        (float) $item->cantidad,
                        $reporte->id,
                        $user->id,
                        'Devolución por eliminación del reporte '.$reporte->folio
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $reporte->delete();
        });

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

    private function resolveInlineImage(?string $path, ?string $disk = null): ?string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '' || \Illuminate\Support\Str::startsWith($path, 'data:')) {
            return \Illuminate\Support\Str::startsWith($path, 'data:image') ? $path : null;
        }

        $uploads = app(\App\Services\UploadStorageService::class);
        if (method_exists($uploads, 'inlineImage')) {
            try {
                $res = $uploads->inlineImage($path, $disk);
                if ($res) return $res;
            } catch (\Throwable) {
                // continuar al fallback directo
            }
        }

        // Fallback directo leyendo el archivo de almacenamiento
        try {
            $cleanPath = ltrim($path, '/');
            if (\Illuminate\Support\Str::startsWith($cleanPath, 'storage/')) {
                $cleanPath = substr($cleanPath, strlen('storage/'));
            }
            $diskName = $disk ?: (method_exists($uploads, 'diskName') ? $uploads->diskName() : 'public');
            if (Storage::disk($diskName)->exists($cleanPath)) {
                $bytes = Storage::disk($diskName)->get($cleanPath);
                if (is_string($bytes) && $bytes !== '') {
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg';
                    return 'data:'.$mime.';base64,'.base64_encode($bytes);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function renderPdfObject(Reporte $reporte): mixed
    {
        $reporte->loadMissing(['creador', 'responsable', 'ticket', 'servicio', 'area', 'equipo', 'ejecutores.usuario', 'materiales.material', 'evidencias', 'dependencia']);
        $rawDesarrollo = (string) ($reporte->desarrollo ?? '');
        $meta = [];
        if (preg_match('/<!--CORE_META:([\s\S]*?)-->\s*$/', $rawDesarrollo, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        $cleanDesarrollo = trim((string) preg_replace('/\n*<!--CORE_META:([\s\S]*?)-->\s*$/', '', $rawDesarrollo));
        $empresa = $reporte->dependencia;

        $logoSrc = null;
        if ($empresa?->logo_ruta) {
            $logoSrc = $this->resolveInlineImage($empresa->logo_ruta, $empresa->logo_disco);
        } elseif ($empresa?->logo) {
            $logoSrc = $this->resolveInlineImage($empresa->logo);
        }

        $e = fn ($val) => htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8');
        $empresaNombre = $e($empresa?->nombre ?? 'CoreReports');
        $folio = $e(preg_replace('/^(TKT|REP|SRV)-20(\d{2}\d*-)/i', '$1-$2', (string) $reporte->folio));
        $creador = $e($reporte->creador ? trim($reporte->creador->nombre.' '.$reporte->creador->apellidos) : '—');
        $puesto = $e($meta['cargo'] ?? ($reporte->creador?->puesto ?? 'Especialista Técnico'));
        $numEmpleado = $e($reporte->creador?->numero_empleado ?? str_pad((string) ($reporte->creador?->id ?? '001'), 3, '0', STR_PAD_LEFT));
        $correo = $e($reporte->creador?->email ?? $empresa?->email ?? '—');
        $fecha = $reporte->fecha_inicio ? $reporte->fecha_inicio->format('Y-m-d') : ($reporte->created_at ? $reporte->created_at->format('Y-m-d') : '—');

        $origenFolioClean = preg_replace('/^(Ticket|Servicio):\s*/i', '', (string) ($reporte->ticket?->folio ?? $reporte->servicio?->folio ?? '0000'));
        $origenFolio = $e(preg_replace('/^(TKT|REP|SRV)-20(\d{2}\d*-)/i', '$1-$2', (string) $origenFolioClean));
        $areaNombre = $e($meta['areaTexto'] ?? ($reporte->area?->nombre ?? 'Área General'));
        $responsableArea = $e($meta['responsable'] ?? ($reporte->responsable ? trim($reporte->responsable->nombre.' '.$reporte->responsable->apellidos) : '—'));

        $categoria = $e(ucfirst((string) ($reporte->categoria ?? '—')));
        $fallaRaw = $meta['falla'] ?? $meta['fallaLibre'] ?? ($reporte->ticket?->asunto ?? 'Sin falla reportada.');
        $conclusionRaw = $meta['conclusion'] ?? ($reporte->estatus === 'finalizado' ? 'Trabajo finalizado satisfactoriamente.' : 'Trabajo en proceso, pendiente de finalización.');

        $formatParagraphs = function ($text, $fallback = 'Sin información.') use ($e) {
            $raw = trim((string) $text);
            if ($raw === '') return '<p style="margin:0 0 10px 0;line-height:1.6;text-align:justify;color:#1e293b;font-size:9.5pt;">'.$fallback.'</p>';
            $lines = preg_split('/\r?\n+/', $raw);
            $lines = array_filter(array_map('trim', $lines));
            if (empty($lines)) return '<p style="margin:0 0 10px 0;line-height:1.6;text-align:justify;color:#1e293b;font-size:9.5pt;">'.$fallback.'</p>';
            $out = '';
            foreach ($lines as $line) {
                $out .= '<p style="margin:0 0 12px 0;line-height:1.6;text-align:justify;color:#1e293b;font-size:9.5pt;">'.$e($line).'</p>';
            }
            return $out;
        };

        $fallaHtml = $formatParagraphs($fallaRaw, 'Sin falla reportada.');
        $desarrolloHtml = $formatParagraphs($cleanDesarrollo, 'Sin descripción de actividades.');
        $conclusionHtml = $formatParagraphs($conclusionRaw, 'Trabajo en proceso, pendiente de finalización.');

        $inicio = $reporte->fecha_inicio ? $reporte->fecha_inicio->format('H:i') : '--:--';
        $fin = $reporte->fecha_fin ? $reporte->fecha_fin->format('H:i') : '--:--';

        $totalHoras = '0h 0m';
        if ($reporte->fecha_inicio && $reporte->fecha_fin) {
            $diffMins = $reporte->fecha_inicio->diffInMinutes($reporte->fecha_fin);
            $h = floor($diffMins / 60);
            $m = $diffMins % 60;
            $totalHoras = "{$h}h {$m}m";
        }

        $esEnTercero = isset($meta['esEnTercero'])
            ? (bool) $meta['esEnTercero']
            : (bool) ($reporte->hora_salida || $reporte->hora_llegada || $reporte->hora_regreso);
        $salidaBase = $reporte->hora_salida ? $reporte->hora_salida->format('H:i') : '--:--';
        $llegadaSitio = $reporte->hora_llegada ? $reporte->hora_llegada->format('H:i') : '--:--';
        $regresoBase = $reporte->hora_regreso ? $reporte->hora_regreso->format('H:i') : '--:--';
        $arrastreMins = 0;
        if ($reporte->hora_salida && $reporte->hora_llegada && $reporte->hora_llegada->gt($reporte->hora_salida)) {
            $arrastreMins += $reporte->hora_salida->diffInMinutes($reporte->hora_llegada);
        }
        if ($reporte->fecha_fin && $reporte->hora_regreso && $reporte->hora_regreso->gt($reporte->fecha_fin)) {
            $arrastreMins += $reporte->fecha_fin->diffInMinutes($reporte->hora_regreso);
        }
        $tiempoArrastre = sprintf('%dh %dm', (int) floor($arrastreMins / 60), $arrastreMins % 60);
        $tiemposHtml = $esEnTercero
            ? "<div><b>Tiempos:</b> &nbsp;&nbsp; <b>Salida Base:</b> {$salidaBase} &nbsp;&nbsp; <b>Llegada a Sitio:</b> {$llegadaSitio} &nbsp;&nbsp; <b>Inicio:</b> {$inicio} &nbsp;&nbsp; <b>Fin:</b> {$fin} &nbsp;&nbsp; <b>Regreso a Base:</b> {$regresoBase}</div><div style=\"margin-top:4px;\"><b>Trabajo Neto:</b> {$totalHoras} &nbsp;&nbsp;&nbsp;&nbsp; <b>Tiempo de Arrastre:</b> {$tiempoArrastre}</div>"
            : "<b>Tiempos:</b> &nbsp;&nbsp;&nbsp;&nbsp; <b>Inicio:</b> {$inicio} &nbsp;&nbsp;&nbsp;&nbsp; <b>Fin:</b> {$fin} &nbsp;&nbsp;&nbsp;&nbsp; <b>Total:</b> {$totalHoras}";

        // Materiales
        $matsHtml = '';
        if ($reporte->materiales->isNotEmpty()) {
            $matsRows = '';
            foreach ($reporte->materiales as $idx => $mat) {
                $nom = $e($mat->material?->nombre ?? 'Material');
                $cant = $e($mat->cantidad);
                $unidad = $e($mat->material?->unidad_medida ?? 'Pza');
                $num = $idx + 1;
                $matsRows .= "<tr><td style=\"padding:4px;border:1px solid #000;text-align:center;\">{$num}</td><td style=\"padding:4px;border:1px solid #000;\">{$nom}</td><td style=\"padding:4px;border:1px solid #000;text-align:center;\">{$cant}</td><td style=\"padding:4px;border:1px solid #000;text-align:center;\">{$unidad}</td></tr>";
            }
            $matsHtml = "<div style=\"font-weight:bold;font-size:10pt;margin-top:12px;margin-bottom:6px;\">MATERIALES UTILIZADOS</div><table style=\"width:100%;border-collapse:collapse;margin-bottom:14px;font-size:9pt;\"><thead><tr style=\"background:#f1f5f9;\"><th style=\"border:1px solid #000;padding:4px;width:40px;\">Item</th><th style=\"border:1px solid #000;padding:4px;text-align:left;\">Descripción</th><th style=\"border:1px solid #000;padding:4px;width:70px;\">Cantidad</th><th style=\"border:1px solid #000;padding:4px;width:70px;\">Unidad</th></tr></thead><tbody>{$matsRows}</tbody></table>";
        }

        // Evidencias (2 por fila)
        $evidsHtml = '';
        if ($reporte->evidencias->isNotEmpty()) {
            $evidsRows = '';
            foreach ($reporte->evidencias->chunk(2) as $pair) {
                $evidsRows .= '<tr style="page-break-inside:avoid;">';
                foreach ($pair as $item) {
                    $imgData = null;
                    try {
                        $imgData = $item->pdfImage();
                    } catch (\Throwable) {
                        $imgData = $this->resolveInlineImage($item->ruta ?: $item->url, $item->disco);
                    }
                    $coment = $e($item->comentario ?? '');
                    $evidsRows .= '<td style="width:50%;padding:4px 8px 12px 0;vertical-align:top;">';
                    if ($imgData) {
                        $evidsRows .= "<div style=\"border:1px solid #94a3b8;padding:2px;text-align:center;\"><img src=\"{$imgData}\" style=\"max-height:190px;max-width:100%;\" /></div>";
                    }
                    $evidsRows .= "<div style=\"font-size:8.5pt;margin-top:4px;\">{$coment}</div>";
                    $evidsRows .= '</td>';
                }
                if ($pair->count() === 1) {
                    $evidsRows .= '<td style="width:50%;"></td>';
                }
                $evidsRows .= '</tr>';
            }
            $evidsHtml = "<div style=\"font-weight:bold;font-size:10pt;margin-top:14px;margin-bottom:10px;\">EVIDENCIA FOTOGRÁFICA</div><table style=\"width:100%;border-collapse:collapse;\">{$evidsRows}</table>";
        }

        $logoHtml = $logoSrc
            ? "<img src=\"{$logoSrc}\" style=\"max-height:60px;max-width:90%;\" />"
            : "<div style=\"font-size:18px;font-weight:bold;\">".substr($empresaNombre, 0, 4)."</div>";

        $html = "<!DOCTYPE html><html lang=\"es\"><head><meta charset=\"UTF-8\"><style>
            * { box-sizing: border-box; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #000000; margin: 0; padding: 18px 24px; line-height: 1.45; }
            .header-box { width: 100%; border-collapse: collapse; border: 2px solid #000000; margin-bottom: 18px; }
            .header-logo-td { width: 27%; border-right: 2px solid #000000; text-align: center; vertical-align: middle; padding: 6px; }
            .header-info-td { width: 73%; padding: 0; vertical-align: middle; }
            .header-company-title { font-size: 13pt; font-weight: bold; text-align: center; padding: 6px 4px; border-bottom: 2px solid #000000; text-transform: uppercase; }
            .header-folio-bar { background: #9ca3af; color: #ffffff; font-size: 11pt; font-weight: bold; text-align: center; padding: 4px; border-bottom: 2px solid #000000; }
            .header-report-title { font-size: 9.5pt; font-weight: bold; text-align: center; padding: 4px; text-transform: uppercase; }
            .section-heading { font-weight: bold; font-size: 10pt; margin-top: 14px; margin-bottom: 6px; text-transform: uppercase; color: #000000; }
            .section-content { font-size: 9.5pt; margin-bottom: 14px; }
            .meta-grid-table { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-bottom: 12px; }
            .meta-grid-table td { padding: 2px 4px; vertical-align: top; }
            .separator-line { border: none; border-top: 2px solid #000000; margin: 14px 0; }
            .tiempos-line { font-size: 9.5pt; margin-top: 12px; margin-bottom: 14px; }
            .signatures-wrap { margin-top: 40px; page-break-inside: avoid; }
            .sig-table { width: 100%; border-collapse: collapse; }
            .sig-td { width: 44%; vertical-align: bottom; }
            .sig-label { font-size: 10pt; font-weight: bold; margin-bottom: 28px; }
            .sig-line { border-top: 1px solid #000000; width: 100%; }
            .sig-subtext { text-align: center; font-size: 8pt; font-weight: bold; letter-spacing: 0.5px; margin-top: 4px; }
            .footer-caption { text-align: center; font-size: 7.5pt; font-style: italic; color: #64748b; margin-top: 20px; }
        </style></head><body>
            <table class=\"header-box\">
                <tr>
                    <td class=\"header-logo-td\">{$logoHtml}</td>
                    <td class=\"header-info-td\">
                        <div class=\"header-company-title\">{$empresaNombre}</div>
                        <div class=\"header-folio-bar\">{$folio}</div>
                        <div class=\"header-report-title\">REPORTE DE MANTENIMIENTO / ORDEN DE TRABAJO ({$categoria})</div>
                    </td>
                </tr>
            </table>

            <div class=\"section-heading\">DATOS GENERALES</div>
            <table class=\"meta-grid-table\">
                <tr><td style=\"width:50%;\"><b>Responsable:</b> {$creador}</td><td style=\"width:50%;\"><b>OT/OC/Ticket:</b> {$origenFolio}</td></tr>
                <tr><td><b>Puesto:</b> {$puesto}</td><td><b>Fecha de solicitud:</b> {$fecha}</td></tr>
                <tr><td><b>No. Empleado:</b> {$numEmpleado}</td><td><b>Area:</b> {$areaNombre}</td></tr>
                <tr><td><b>Fecha:</b> {$fecha}</td><td><b>Responsable de Area:</b> {$responsableArea}</td></tr>
                <tr><td><b>Correos:</b> {$correo}</td><td></td></tr>
            </table>

            <hr class=\"separator-line\" />

            <div class=\"section-heading\">DESCRIPCIÓN DE LA FALLA REPORTADA</div>
            <div class=\"section-content\">{$fallaHtml}</div>

            <div class=\"section-heading\">DESCRIPCIÓN DEL SERVICIO</div>
            <div class=\"section-content\">{$desarrolloHtml}</div>

            <div class=\"section-heading\">CONCLUSIÓN DEL SERVICIO</div>
            <div class=\"section-content\">{$conclusionHtml}</div>

            <div class=\"tiempos-line\">
                {$tiemposHtml}
            </div>

            {$matsHtml}
            {$evidsHtml}

            <div class=\"signatures-wrap\">
                <table class=\"sig-table\">
                    <tr>
                        <td class=\"sig-td\">
                            <div class=\"sig-label\">Reviso:</div>
                            <div class=\"sig-line\"></div>
                            <div class=\"sig-subtext\">NOMBRE Y FIRMA</div>
                        </td>
                        <td style=\"width:12%;\"></td>
                        <td class=\"sig-td\">
                            <div class=\"sig-label\">Elaboro:</div>
                            <div class=\"sig-line\"></div>
                            <div class=\"sig-subtext\">NOMBRE Y FIRMA</div>
                        </td>
                    </tr>
                </table>
                <div class=\"footer-caption\">Generado con CoreReports para {$empresaNombre}</div>
            </div>
        </body></html>";

        // 1. Barryvdh\DomPDF\Facade\Pdf (versión moderna 2.x / 3.x)
        if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $facadeClass = 'Barryvdh\\DomPDF\\Facade\\Pdf';
            $pdf = $facadeClass::loadHTML($html)->setPaper('letter', 'portrait');
            if (method_exists($pdf, 'setOptions')) {
                $pdf->setOptions(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
            }
            return $pdf;
        }

        // 2. Barryvdh\DomPDF\Facade (versión clásica 0.x / 1.x)
        if (class_exists('Barryvdh\\DomPDF\\Facade')) {
            $facadeClass = 'Barryvdh\\DomPDF\\Facade';
            $pdf = $facadeClass::loadHTML($html)->setPaper('letter', 'portrait');
            if (method_exists($pdf, 'setOptions')) {
                $pdf->setOptions(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
            }
            return $pdf;
        }

        // 3. Alias global 'PDF'
        if (class_exists('PDF')) {
            $pdf = \PDF::loadHTML($html)->setPaper('letter', 'portrait');
            if (method_exists($pdf, 'setOptions')) {
                $pdf->setOptions(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
            }
            return $pdf;
        }

        // 4. Contenedor IoC de Laravel 'dompdf.wrapper'
        if (app()->bound('dompdf.wrapper')) {
            $pdf = app('dompdf.wrapper')->loadHTML($html)->setPaper('letter', 'portrait');
            if (method_exists($pdf, 'setOptions')) {
                $pdf->setOptions(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
            }
            return $pdf;
        }

        // 5. Registrar ServiceProvider manualmente si la clase existe pero no fue autodescubierta
        if (class_exists('Barryvdh\\DomPDF\\ServiceProvider')) {
            try {
                app()->register('Barryvdh\\DomPDF\\ServiceProvider');
                if (app()->bound('dompdf.wrapper')) {
                    $pdf = app('dompdf.wrapper')->loadHTML($html)->setPaper('letter', 'portrait');
                    return $pdf;
                }
            } catch (\Throwable) {
                // Continuar a otros métodos
            }
        }

        // 6. Dompdf nativo (dompdf/dompdf)
        if (!class_exists('Dompdf\\Dompdf')) {
            $manualAutoload = base_path('vendor/dompdf/dompdf/src/Autoloader.php');
            if (file_exists($manualAutoload)) {
                require_once $manualAutoload;
                if (class_exists('Dompdf\\Autoloader')) {
                    \Dompdf\Autoloader::register();
                }
            }
        }

        if (class_exists('Dompdf\\Dompdf')) {
            $options = class_exists('Dompdf\\Options')
                ? new \Dompdf\Options([
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                    'defaultFont' => 'DejaVu Sans',
                ])
                : null;
            $dompdf = $options ? new \Dompdf\Dompdf($options) : new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            return new class($dompdf) {
                public function __construct(private \Dompdf\Dompdf $dompdf) {}
                public function output(): string {
                    return (string) $this->dompdf->output();
                }
                public function download(string $filename = 'document.pdf') {
                    return response($this->output(), 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                    ]);
                }
            };
        }

        // 7. Fallback nativo: si DomPDF no está en vendor, usar ReportePdfService (cero dependencias externas)
        return new \App\Services\ReportePdfService($reporte);
    }

    public function pdf(Request $request, Reporte $reporte): Response
    {
        $this->ensureOwnDependencia($request, $reporte);

        try {
            $pdf = $this->renderPdfObject($reporte);
            return $pdf->download($reporte->folio.'.pdf');
        } catch (\Throwable $e) {
            Log::warning('renderPdfObject falló para reporte '.$reporte->id.', usando ReportePdfService nativo: '.$e->getMessage());
            try {
                $nativePdf = new \App\Services\ReportePdfService($reporte);
                return $nativePdf->download($reporte->folio.'.pdf');
            } catch (\Throwable $e2) {
                Log::error('ReportePdfService también falló: '.$e2->getMessage());
                abort(500, 'Error al generar el PDF: '.$e->getMessage());
            }
        }
    }

    public function enviarCorreo(Request $request, Reporte $reporte): JsonResponse
    {
        $this->ensureOwnDependencia($request, $reporte);

        $request->validate([
            'destinatarios' => 'required|array|min:1',
            'destinatarios.*' => 'required|email',
            'comentario' => 'nullable|string|max:500',
        ]);

        $destinatarios = array_values(array_filter(
            array_map('trim', (array) $request->input('destinatarios')),
            fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL)
        ));

        if (empty($destinatarios)) {
            return response()->json(['message' => 'Proporciona al menos un correo electrónico válido.'], 422);
        }

        try {
            $pdfContent = $this->renderPdfObject($reporte)->output();
            $comentario = $request->input('comentario');

            $mailer = config('mail.default');
            $fromAddress = config('mail.from.address');

            Mail::to($destinatarios)->send(new ReportePdfMail($reporte, $pdfContent, $comentario));

            if ($mailer === 'log') {
                return response()->json([
                    'message' => 'ATENCIÓN: El servidor tiene MAIL_MAILER=log en el archivo .env. El correo se guardó en el log del servidor (storage/logs/laravel.log) pero NO se transmitió por internet. Configure el SMTP en el archivo .env de IONOS.',
                    'destinatarios' => $destinatarios,
                    'mailer' => $mailer,
                    'from' => $fromAddress,
                ]);
            }

            return response()->json([
                'message' => 'Reporte en PDF enviado exitosamente por correo.',
                'destinatarios' => $destinatarios,
                'mailer' => $mailer,
                'from' => $fromAddress,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al enviar reporte por correo: ' . $e->getMessage(), [
                'reporte_id' => $reporte->id,
                'destinatarios' => $destinatarios,
            ]);

            return response()->json([
                'message' => 'No se pudo enviar el correo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
