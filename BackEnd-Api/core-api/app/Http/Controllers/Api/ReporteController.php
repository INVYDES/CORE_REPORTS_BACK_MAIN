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
use App\Services\UploadStorageService;
use App\Support\XlsxWriter;
use App\Traits\EnsuresDependencia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    use EnsuresDependencia;

    public function __construct(private readonly UploadStorageService $uploads) {}

    public function index(Request $request): JsonResponse
    {
        $q = $this->filteredQuery($request)
            ->with(['creador', 'responsable', 'ticket', 'servicio', 'ejecutores.usuario', 'materiales.material']);

        return response()->json($q->orderByDesc('fecha_inicio')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    /**
     * GET /api/v1/reportes/export?formato=csv|xlsx
     *
     * Exporta el listado de reportes aplicando los mismos filtros del index.
     * CSV se emite con BOM UTF-8 y las celdas se sanean contra inyeccion de
     * formulas. XLSX se genera con el escritor OOXML interno (sin ext-zip).
     */
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

        if ($formato === 'xlsx') {
            return response()->streamDownload(function () use ($headers, $rows): void {
                $out = fopen('php://output', 'w');

                if ($out === false) {
                    return;
                }

                XlsxWriter::stream($out, 'Reportes', $headers, $rows);
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

        return [
            $r->folio,
            $r->tipo,
            $r->ticket?->folio ?: ($r->servicio?->folio ?: ''),
            $r->categoria,
            $r->estatus,
            $r->fecha_inicio->format('Y-m-d H:i'),
            $r->fecha_fin->format('Y-m-d H:i'),
            $r->area?->nombre ?: '',
            $r->equipo?->nombre ?: '',
            $r->responsable ? trim($r->responsable->nombre.' '.$r->responsable->apellidos) : '',
            $r->creador ? trim($r->creador->nombre.' '.$r->creador->apellidos) : '',
            $r->ejecutores->map(fn ($e) => $e->usuario ? trim($e->usuario->nombre.' '.$e->usuario->apellidos) : null)->filter()->implode(', '),
            $r->desarrollo,
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

    /**
     * Evita inyeccion de formulas al abrir el CSV en Excel/LibreOffice.
     */
    private function safeCsvCell(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }

    public function store(StoreReporteRequest $request, InventarioService $inv): JsonResponse
    {
        $this->authorize('create', Reporte::class);

        $data = $request->validated();

        // Límite mensual de reportes por licencia
        $dep = $this->usuario($request)->dependencia;
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

        $materiales = collect((array) $request->input('materiales', []))
            ->map(fn ($m) => [
                'material' => MaterialCatalogo::find((int) $m['material_id']),
                'cantidad' => (float) $m['cantidad'],
            ])
            ->filter(fn ($m) => $m['material'] !== null)
            ->values();

        if ($request->input('materiales') !== null && count($materiales) !== count($request->input('materiales'))) {
            return response()->json(['message' => 'Algún material no existe en tu dependencia'], 422);
        }

        // Limites adicionales para evidencias: maximo 20 archivos y 5 MB por
        // imagen (los documentos pueden llegar a 10 MB, validado en el FormRequest).
        $archivos = (array) $request->file('evidencias', []);
        $totalEvidencias = count($archivos) + count((array) $request->input('evidenciaFotografica', []));

        if ($totalEvidencias > 20) {
            throw ValidationException::withMessages([
                'evidencias' => 'Puedes adjuntar como maximo 20 archivos por reporte.',
            ]);
        }

        foreach ($archivos as $archivo) {
            if ($this->uploads->isImage($archivo) && $archivo->getSize() > 5 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'evidencias' => 'Cada imagen debe pesar como maximo 5 MB.',
                ]);
            }
        }

        $reporte = DB::transaction(function () use ($request, $data, $ejecutores, $materiales, $inv) {
            $data['dependencia_id'] = $this->usuario($request)->dependencia_id;
            $data['creado_por'] = $this->usuario($request)->id;
            $data['folio'] = $data['folio'] ?? Reporte::generarFolio($this->usuario($request)->dependencia_id);
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
                    $this->usuario($request)->id
                );
            }

            $carpetaEvidencias = 'evidencias/'.$reporte->dependencia_id;

            // Evidencias por multipart. Las imagenes se guardan en /imagenes y
            // los documentos (PDF/Excel/CSV) en /documentos, para que las
            // fotografias se puedan mostrar inline por URL desde IONOS.
            if ($request->hasFile('evidencias')) {
                foreach ($request->file('evidencias') as $file) {
                    $isImage = $this->uploads->isImage($file);
                    $carpeta = $carpetaEvidencias.'/'.($isImage ? 'imagenes' : 'documentos');
                    $path = $this->uploads->storeFile(
                        $file,
                        $carpeta,
                        $this->uploads->visibilityFor($isImage)
                    );

                    $reporte->evidencias()->create([
                        'dependencia_id' => $reporte->dependencia_id,
                        'url' => $this->uploads->url($path),
                        'ruta' => $path,
                        'disco' => $this->uploads->diskName(),
                        'tipo_archivo' => $this->uploads->mimeType($file),
                        'peso_kb' => (int) ceil($file->getSize() / 1024),
                        'subido_por' => $this->usuario($request)->id,
                    ]);
                }
            }

            // Evidencias fotográficas en base64 o array de URLs/base64
            $evidenciasInput = $request->input('evidenciaFotografica');
            if (is_array($evidenciasInput)) {
                foreach ($evidenciasInput as $evi) {
                    $valor = is_string($evi) ? $evi : ($evi['url'] ?? null);
                    $valor = is_string($valor) ? trim($valor) : null;

                    if ($valor === null || $valor === '') {
                        continue;
                    }

                    $decoded = $this->uploads->decodeImageDataUri($valor);
                    $ruta = null;
                    $disco = null;

                    if ($decoded) {
                        // Mismo límite que las imágenes multipart: 5 MB.
                        if (strlen($decoded['contents']) > 5 * 1024 * 1024) {
                            throw ValidationException::withMessages([
                                'evidenciaFotografica' => 'Cada imagen debe pesar como maximo 5 MB.',
                            ]);
                        }

                        $path = $this->uploads->storeContents(
                            $decoded['contents'],
                            $carpetaEvidencias.'/imagenes',
                            $decoded['extension'],
                            $this->uploads->visibilityFor(true)
                        );
                        $url = $this->uploads->url($path);
                        $ruta = $path;
                        $disco = $this->uploads->diskName();
                        $mime = $decoded['mime'];
                        $pesoKb = (int) ceil(strlen($decoded['contents']) / 1024);
                    } elseif (str_starts_with($valor, 'data:')) {
                        // Un data-URI que no es una imagen raster (JPG/PNG/GIF/WebP)
                        // no se acepta: evita guardar documentos embebidos como si
                        // fueran referencias remotas.
                        throw ValidationException::withMessages([
                            'evidenciaFotografica' => 'Solo se admiten imagenes JPG, PNG, GIF o WebP en base64. Sube los documentos como archivos.',
                        ]);
                    } else {
                        // Referencia ya almacenada (URL absoluta o ruta relativa)
                        if (str_starts_with($valor, 'http')) {
                            $url = $valor;
                        } else {
                            $ruta = ltrim($valor, '/');
                            $disco = $this->uploads->diskName();
                            $url = $this->uploads->url($ruta);
                        }
                        $mime = is_array($evi) ? ($evi['tipoArchivo'] ?? $evi['tipo_archivo'] ?? null) : null;
                        $pesoKb = 0;
                    }

                    $reporte->evidencias()->create([
                        'dependencia_id' => $reporte->dependencia_id,
                        'url' => $url,
                        'ruta' => $ruta,
                        'disco' => $disco,
                        'tipo_archivo' => $mime,
                        'peso_kb' => $pesoKb,
                        'subido_por' => $this->usuario($request)->id,
                    ]);
                }
            }

            // La sincronización de estatus del ticket/servicio origen la hace
            // ReporteObserver (registrado en AppServiceProvider).
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

        // ReporteObserver sincroniza el estatus del ticket/servicio origen.
        $reporte->update($data);

        return response()->json($reporte->load(['ejecutores', 'materiales']));
    }

    public function destroy(Request $request, Reporte $reporte, InventarioService $inv): JsonResponse
    {
        $this->authorize('delete', $reporte);

        DB::transaction(function () use ($request, $reporte, $inv): void {
            // Devolver al stock los materiales consumidos por el reporte.
            /** @var \App\Models\ReporteMaterial $item */
            foreach ($reporte->materiales()->get() as $item) {
                $inv->revertirSalida(
                    $reporte->dependencia_id,
                    (int) $item->material_id,
                    (float) $item->cantidad,
                    $reporte->id,
                    $this->usuario($request)->id,
                    'Devolución por eliminación del reporte '.$reporte->folio
                );
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

    public function pdf(Reporte $reporte): Response
    {
        $this->ensureOwnDependencia($reporte);

        $reporte->load(['creador', 'responsable', 'ticket', 'servicio', 'area', 'equipo', 'ejecutores.usuario', 'materiales.material', 'evidencias']);
        $empresa = $reporte->dependencia;

        // Logo y evidencias se incrustan como data-URI leidos del disco: el
        // render del PDF no descarga URLs, asi que una referencia remota del
        // cliente no puede usarse para pedir recursos de la red interna (SSRF).
        $logo = $empresa?->logo_ruta
            ? $this->uploads->inlineImage($empresa->logo_ruta, $empresa->logo_disco ?: null)
            : $this->uploads->inlineImage($empresa?->logo);

        $pdf = Pdf::setOption([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'chroot' => base_path(),
        ])->loadView('reportes.pdf', [
            'reporte' => $reporte,
            'empresa' => $empresa,
            'logo' => $logo,
            'generado' => now(),
        ])->setPaper('letter', 'portrait');

        return $pdf->download($reporte->folio.'.pdf');
    }
}
