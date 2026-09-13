<?php

namespace App\Http\Controllers;

use App\Models\Reporte;
use App\Models\ReporteMaterial;
use App\Models\ReporteEvidencia;
use App\Models\ReporteEjecutor;
use App\Models\Ticket;
use App\Models\ServicioProgramado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReporteController extends Controller
{
    /**
     * GET /api/reportes
     * Lista todos los reportes técnicos de la empresa
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $idEmpresa = $user->id_dependencia;

        $query = Reporte::where('id_dependencia', $idEmpresa);

        // Si el usuario no es Admin Técnico (rol 1) ni Admin Comercial (rol 2), solo ve sus propios reportes (creador o ejecutor)
        if (!in_array((int)$user->rol, [1, 2])) {
            $query->where(function ($q) use ($user) {
                $q->where('creado_por', $user->id_usuario)
                  ->orWhereHas('ejecutores', function ($sub) use ($user) {
                      $sub->where('id_usuario', $user->id_usuario);
                  });
            });
        }

        $reportes = $query
            ->with(['materiales', 'evidencias', 'ejecutores.usuario', 'encuesta', 'creador', 'subdependencia', 'dependencia'])
            ->orderBy('id_reporte', 'desc')
            ->get()
            ->map(function ($r) {
                return $this->formatReporte($r);
            });

        return response()->json([
            'success' => true,
            'message' => 'Reportes obtenidos correctamente.',
            'data'    => $reportes
        ], 200);
    }

    /**
     * POST /api/reportes
     * Guarda una nueva hoja de servicio con materiales, evidencias y ejecutores
     */
    public function store(Request $request)
    {
        $idEmpresa = $request->user()->id_dependencia;
        $creadorId = $request->user()->id_usuario;

        $validator = Validator::make($request->all(), [
            'tipoReporte'           => 'required|in:Ticket,Servicio Programado,Libre',
            'categoria'             => 'required|string',
            'fechaHoraInicio'       => 'required',
            'fechaHoraFin'          => 'required',
            'desarrolloActividades' => 'required|string',
        ], [
            'tipoReporte.required'           => 'Debes seleccionar el tipo de reporte.',
            'categoria.required'             => 'La categoría del servicio es requerida.',
            'fechaHoraInicio.required'       => 'La fecha y hora de inicio es requerida.',
            'fechaHoraFin.required'          => 'La fecha y hora de fin es requerida.',
            'desarrolloActividades.required' => 'El desarrollo de actividades es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos incompletos o inválidos.',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Generar folio único (ej. REP-2026-A9B1)
        $folio = 'REP-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4));

        // Formateadores seguros para tipos de datos de MySQL
        $formatDate = function ($dateStr) {
            if (empty($dateStr)) return now()->toDateString();
            if (strpos($dateStr, 'T') !== false) {
                return explode('T', $dateStr)[0];
            }
            $t = strtotime($dateStr);
            return $t ? date('Y-m-d', $t) : now()->toDateString();
        };

        $formatDateTime = function ($dateTimeStr) {
            if (empty($dateTimeStr)) return now()->toDateTimeString();
            $str = str_replace('T', ' ', trim($dateTimeStr));
            $parts = explode(' ', $str);
            if (count($parts) === 2) {
                $timeParts = explode(':', $parts[1]);
                if (count($timeParts) === 2) {
                    return $parts[0] . ' ' . str_pad($timeParts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($timeParts[1], 2, '0', STR_PAD_LEFT) . ':00';
                }
            }
            $t = strtotime($str);
            return $t ? date('Y-m-d H:i:s', $t) : now()->toDateTimeString();
        };

        $formatTime = function ($timeStr) {
            if (empty($timeStr)) return null;
            $timeStr = trim($timeStr);
            if (strpos($timeStr, 'T') !== false) {
                $parts = explode('T', $timeStr);
                $timeStr = $parts[1] ?? '';
            } elseif (strpos($timeStr, ' ') !== false) {
                $parts = explode(' ', $timeStr);
                $timeStr = end($parts);
            }
            $t = explode(':', $timeStr);
            if (count($t) >= 2) {
                $h = (int) $t[0];
                $m = (int) $t[1];
                $s = isset($t[2]) ? (int) $t[2] : 0;
                return sprintf('%02d:%02d:%02d', $h, $m, $s);
            }
            $timestamp = strtotime($timeStr);
            return $timestamp ? date('H:i:s', $timestamp) : null;
        };

        // Sanitización de IDs foráneos
        $idSub = (!empty($request->idSubdependencia) && is_numeric($request->idSubdependencia) && (int)$request->idSubdependencia > 0)
            ? (int)$request->idSubdependencia
            : null;
        if ($idSub !== null) {
            $subExists = \App\Models\Subdependencia::where('id_dependencia', $idEmpresa)->where('id_subdependencia', $idSub)->exists();
            if (!$subExists) $idSub = null;
        }

        $origenId = (!empty($request->origenDatosId) && is_numeric($request->origenDatosId) && (int)$request->origenDatosId > 0)
            ? (int)$request->origenDatosId
            : null;

        $respId = (!empty($request->responsableId) && is_numeric($request->responsableId) && (int)$request->responsableId > 0)
            ? (int)$request->responsableId
            : null;
        if ($respId !== null) {
            $userExists = \App\Models\User::where('id_dependencia', $idEmpresa)->where('id_usuario', $respId)->exists();
            if (!$userExists) $respId = null;
        }

        $actividades = $request->desarrolloActividades;
        if (!empty($request->conclusionTrabajo)) {
            $actividades .= "\n\nConclusión:\n" . $request->conclusionTrabajo;
        }

        try {
            return DB::transaction(function () use (
                $request, $idEmpresa, $creadorId, $folio,
                $formatDate, $formatDateTime, $formatTime,
                $idSub, $origenId, $respId, $actividades
            ) {
                $reporte = Reporte::create([
                    'folio'                  => $folio,
                    'id_dependencia'         => $idEmpresa,
                    'id_subdependencia'      => $idSub,
                    'creado_por'             => $creadorId,
                    'tipo_reporte'           => $request->tipoReporte,
                    'origen_datos_id'        => $origenId,
                    'categoria'              => $request->categoria,
                    'fecha_elaboracion'      => $formatDate($request->fechaElaboracion),
                    'responsable_id'         => $respId,
                    'responsable_texto'      => substr((string) ($request->responsableTexto ?: ''), 0, 150),
                    'cargo_texto'            => substr((string) ($request->cargoTexto ?: ''), 0, 100),
                    'fecha_hora_inicio'      => $formatDateTime($request->fechaHoraInicio),
                    'fecha_hora_fin'         => $formatDateTime($request->fechaHoraFin),
                    'hora_salida_base'       => $formatTime($request->horaSalidaBase),
                    'hora_llegada_sitio'     => $formatTime($request->horaLlegadaSitio),
                    'hora_regreso_a_base'    => $formatTime($request->horaRegresoABase),
                    'falla_reportada'        => (string) ($request->fallaReportada ?: ''),
                    'desarrollo_actividades' => $actividades,
                    'es_retrabajo'           => (bool) ($request->esRetrabajo ?? false),
                    'estatus_reporte'        => $request->estatusReporte ?: 'Cerrado / Trabajo Finalizado',
                ]);

                // Guardar ejecutores
                if (!empty($request->ejecutoresIds) && is_array($request->ejecutoresIds)) {
                    foreach ($request->ejecutoresIds as $idUser) {
                        $idUser = (int) $idUser;
                        if ($idUser > 0) {
                            $userExists = \App\Models\User::where('id_usuario', $idUser)->exists();
                            if ($userExists) {
                                ReporteEjecutor::firstOrCreate([
                                    'id_reporte' => $reporte->id_reporte,
                                    'id_usuario' => $idUser,
                                ]);
                            }
                        }
                    }
                }

                // Guardar materiales
                if (!empty($request->materiales) && is_array($request->materiales)) {
                    foreach ($request->materiales as $mat) {
                        if (!empty($mat['material'])) {
                            ReporteMaterial::create([
                                'id_reporte' => $reporte->id_reporte,
                                'material'   => substr((string) $mat['material'], 0, 255),
                                'cantidad'   => is_numeric($mat['cantidad'] ?? null) ? (float) $mat['cantidad'] : 1.0,
                                'unidad'     => !empty($mat['unidad']) ? substr((string) $mat['unidad'], 0, 50) : 'Pieza',
                            ]);
                        }
                    }
                }

                // Guardar evidencias fotográficas
                if (!empty($request->evidenciaFotografica) && is_array($request->evidenciaFotografica)) {
                    $uploadDir = public_path('uploads/evidencias');
                    if (!file_exists($uploadDir)) {
                        @mkdir($uploadDir, 0777, true);
                    }

                    foreach ($request->evidenciaFotografica as $evi) {
                        if (!empty($evi['url'])) {
                            $urlToSave = (string) $evi['url'];

                            // Si viene en formato base64, guardarlo como archivo físico en el servidor
                            if (preg_match('/^data:image\/(\w+);base64,/', $urlToSave, $matches)) {
                                $imageType = strtolower($matches[1]);
                                $base64Data = substr($urlToSave, strpos($urlToSave, ',') + 1);
                                $decodedData = base64_decode($base64Data);

                                if ($decodedData !== false) {
                                    $fileName = 'evi_' . uniqid() . '.' . ($imageType === 'jpeg' ? 'jpg' : $imageType);
                                    file_put_contents($uploadDir . DIRECTORY_SEPARATOR . $fileName, $decodedData);
                                    $urlToSave = '/uploads/evidencias/' . $fileName;
                                }
                            }

                            ReporteEvidencia::create([
                                'id_reporte' => $reporte->id_reporte,
                                'url'        => substr($urlToSave, 0, 255),
                                'comentario' => !empty($evi['comentario']) ? (string) $evi['comentario'] : null,
                            ]);
                        }
                    }
                }

                // Si el reporte proviene de un Ticket o Servicio y se finalizó, actualizar su estatus
                if ($reporte->estatus_reporte === 'Cerrado / Trabajo Finalizado' && $reporte->origen_datos_id) {
                    if ($reporte->tipo_reporte === 'Ticket') {
                        Ticket::where('id_dependencia', $idEmpresa)
                            ->where('id', $reporte->origen_datos_id)
                            ->update(['estatus' => 'Resuelto']);
                    } elseif ($reporte->tipo_reporte === 'Servicio Programado') {
                        ServicioProgramado::where('id_dependencia', $idEmpresa)
                            ->where('id', $reporte->origen_datos_id)
                            ->update(['estatus' => 'Realizado']);
                    }
                }

                $reporte->load(['materiales', 'evidencias', 'ejecutores.usuario', 'encuesta', 'creador', 'subdependencia', 'dependencia']);

                return response()->json([
                    'success' => true,
                    'message' => 'Reporte técnico guardado con éxito en MySQL.',
                    'data'    => $this->formatReporte($reporte)
                ], 201);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/reportes/{id}
     * Obtiene el detalle de un reporte específico
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $idEmpresa = $user->id_dependencia;

        $query = Reporte::where('id_dependencia', $idEmpresa);

        // Si el usuario no es Admin Técnico (rol 1) ni Admin Comercial (rol 2), solo puede ver si es creador o ejecutor
        if (!in_array((int)$user->rol, [1, 2])) {
            $query->where(function ($q) use ($user) {
                $q->where('creado_por', $user->id_usuario)
                  ->orWhereHas('ejecutores', function ($sub) use ($user) {
                      $sub->where('id_usuario', $user->id_usuario);
                  });
            });
        }

        $reporte = $query
            ->with(['materiales', 'evidencias', 'ejecutores.usuario', 'encuesta', 'creador', 'subdependencia', 'dependencia'])
            ->find($id);

        if (!$reporte) {
            return response()->json([
                'success' => false,
                'message' => 'Reporte no encontrado o no autorizado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatReporte($reporte)
        ], 200);
    }

    /**
     * PUT /api/reportes/{id}
     * Actualiza el estatus o conclusión del reporte
     */
    public function update(Request $request, $id)
    {
        $idEmpresa = $request->user()->id_dependencia;
        $reporte = Reporte::where('id_dependencia', $idEmpresa)->find($id);

        if (!$reporte) {
            return response()->json([
                'success' => false,
                'message' => 'Reporte no encontrado.'
            ], 404);
        }

        $formatDate = function ($dateStr) {
            if (empty($dateStr)) return null;
            return date('Y-m-d', strtotime($dateStr));
        };

        $formatDateTime = function ($str) {
            if (empty($str)) return null;
            $str = str_replace('T', ' ', trim($str));
            $parts = explode(' ', $str);
            if (count($parts) === 2) {
                $timeParts = explode(':', $parts[1]);
                if (count($timeParts) === 2) {
                    return $parts[0] . ' ' . str_pad($timeParts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($timeParts[1], 2, '0', STR_PAD_LEFT) . ':00';
                }
            }
            $t = strtotime($str);
            return $t ? date('Y-m-d H:i:s', $t) : null;
        };

        $formatTime = function ($timeStr) {
            if (empty($timeStr)) return null;
            $timeStr = trim($timeStr);
            if (strpos($timeStr, 'T') !== false) {
                $parts = explode('T', $timeStr);
                $timeStr = $parts[1] ?? '';
            } elseif (strpos($timeStr, ' ') !== false) {
                $parts = explode(' ', $timeStr);
                $timeStr = end($parts);
            }
            $t = explode(':', $timeStr);
            if (count($t) >= 2) {
                $h = (int) $t[0];
                $m = (int) $t[1];
                $s = isset($t[2]) ? (int) $t[2] : 0;
                return sprintf('%02d:%02d:%02d', $h, $m, $s);
            }
            $timestamp = strtotime($timeStr);
            return $timestamp ? date('H:i:s', $timestamp) : null;
        };

        return DB::transaction(function () use (
            $request, $idEmpresa, $reporte,
            $formatDate, $formatDateTime, $formatTime
        ) {
            if ($request->has('tipoReporte') || $request->has('tipo_reporte')) {
                $reporte->tipo_reporte = $request->input('tipoReporte', $request->input('tipo_reporte'));
            }
            if ($request->has('origenDatosId') || $request->has('origen_datos_id')) {
                $reporte->origen_datos_id = $request->input('origenDatosId', $request->input('origen_datos_id'));
            }
            if ($request->has('categoria')) {
                $reporte->categoria = $request->categoria;
            }
            if ($request->has('fechaElaboracion') || $request->has('fecha_elaboracion')) {
                $reporte->fecha_elaboracion = $formatDate($request->input('fechaElaboracion', $request->input('fecha_elaboracion')));
            }
            if ($request->has('idSubdependencia') || $request->has('id_subdependencia')) {
                $idSub = $request->input('idSubdependencia', $request->input('id_subdependencia'));
                $reporte->id_subdependencia = (!empty($idSub) && is_numeric($idSub) && (int)$idSub > 0) ? (int)$idSub : null;
            }
            if ($request->has('responsableTexto') || $request->has('responsable_texto')) {
                $reporte->responsable_texto = substr((string) ($request->input('responsableTexto', $request->input('responsable_texto')) ?: ''), 0, 150);
            }
            if ($request->has('cargoTexto') || $request->has('cargo_texto')) {
                $reporte->cargo_texto = substr((string) ($request->input('cargoTexto', $request->input('cargo_texto')) ?: ''), 0, 100);
            }
            if ($request->has('fechaHoraInicio') || $request->has('fecha_hora_inicio')) {
                $reporte->fecha_hora_inicio = $formatDateTime($request->input('fechaHoraInicio', $request->input('fecha_hora_inicio')));
            }
            if ($request->has('fechaHoraFin') || $request->has('fecha_hora_fin')) {
                $reporte->fecha_hora_fin = $formatDateTime($request->input('fechaHoraFin', $request->input('fecha_hora_fin')));
            }
            if ($request->has('horaSalidaBase') || $request->has('hora_salida_base')) {
                $reporte->hora_salida_base = $formatTime($request->input('horaSalidaBase', $request->input('hora_salida_base')));
            }
            if ($request->has('horaLlegadaSitio') || $request->has('hora_llegada_sitio')) {
                $reporte->hora_llegada_sitio = $formatTime($request->input('horaLlegadaSitio', $request->input('hora_llegada_sitio')));
            }
            if ($request->has('horaRegresoABase') || $request->has('hora_regreso_a_base')) {
                $reporte->hora_regreso_a_base = $formatTime($request->input('horaRegresoABase', $request->input('hora_regreso_a_base')));
            }
            if ($request->has('fallaReportada') || $request->has('falla_reportada')) {
                $reporte->falla_reportada = (string) ($request->input('fallaReportada', $request->input('falla_reportada')) ?: '');
            }

            if ($request->has('desarrolloActividades') || $request->has('desarrollo_actividades')) {
                $act = $request->input('desarrolloActividades', $request->input('desarrollo_actividades'));
                if (!empty($request->conclusionTrabajo)) {
                    $act .= "\n\nConclusión:\n" . $request->conclusionTrabajo;
                }
                $reporte->desarrollo_actividades = $act;
            }

            if ($request->has('esRetrabajo') || $request->has('es_retrabajo')) {
                $reporte->es_retrabajo = (bool) $request->input('esRetrabajo', $request->input('es_retrabajo'));
            }

            if ($request->has('estatusReporte') || $request->has('estatus_reporte')) {
                $reporte->estatus_reporte = $request->input('estatusReporte', $request->input('estatus_reporte'));
            }

            $reporte->save();

            // Sincronizar ejecutores
            $ejecutoresIds = $request->input('ejecutoresIds', $request->input('ejecutores_ids'));
            if (is_array($ejecutoresIds)) {
                ReporteEjecutor::where('id_reporte', $reporte->id_reporte)->delete();
                foreach ($ejecutoresIds as $idUser) {
                    $idUser = (int) $idUser;
                    if ($idUser > 0) {
                        ReporteEjecutor::create([
                            'id_reporte' => $reporte->id_reporte,
                            'id_usuario' => $idUser,
                        ]);
                    }
                }
            }

            // Sincronizar materiales
            if (is_array($request->materiales)) {
                ReporteMaterial::where('id_reporte', $reporte->id_reporte)->delete();
                foreach ($request->materiales as $mat) {
                    if (!empty($mat['material'])) {
                        ReporteMaterial::create([
                            'id_reporte' => $reporte->id_reporte,
                            'material'   => substr((string) $mat['material'], 0, 255),
                            'cantidad'   => is_numeric($mat['cantidad'] ?? null) ? (float) $mat['cantidad'] : 1.0,
                            'unidad'     => !empty($mat['unidad']) ? substr((string) $mat['unidad'], 0, 50) : 'Pieza',
                        ]);
                    }
                }
            }

            // Sincronizar evidencias fotográficas
            $evidencias = $request->input('evidenciaFotografica', $request->input('evidencias'));
            if (is_array($evidencias)) {
                ReporteEvidencia::where('id_reporte', $reporte->id_reporte)->delete();
                $uploadDir = public_path('uploads/evidencias');
                if (!file_exists($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }

                foreach ($evidencias as $evi) {
                    if (!empty($evi['url'])) {
                        $urlToSave = (string) $evi['url'];
                        if (preg_match('/^data:image\/(\w+);base64,/', $urlToSave, $matches)) {
                            $imageType = strtolower($matches[1]);
                            $base64Data = substr($urlToSave, strpos($urlToSave, ',') + 1);
                            $decodedData = base64_decode($base64Data);
                            if ($decodedData !== false) {
                                $fileName = 'evi_' . uniqid() . '.' . ($imageType === 'jpeg' ? 'jpg' : $imageType);
                                file_put_contents($uploadDir . DIRECTORY_SEPARATOR . $fileName, $decodedData);
                                $urlToSave = '/uploads/evidencias/' . $fileName;
                            }
                        }

                        ReporteEvidencia::create([
                            'id_reporte' => $reporte->id_reporte,
                            'url'        => substr($urlToSave, 0, 255),
                            'comentario' => !empty($evi['comentario']) ? (string) $evi['comentario'] : null,
                        ]);
                    }
                }
            }

            // Si el reporte se cerró, sincronizar el estatus del Ticket o Servicio origen
            if ($reporte->estatus_reporte === 'Cerrado / Trabajo Finalizado' && $reporte->origen_datos_id) {
                if ($reporte->tipo_reporte === 'Ticket') {
                    Ticket::where('id_dependencia', $idEmpresa)
                        ->where('id', $reporte->origen_datos_id)
                        ->update(['estatus' => 'Resuelto']);
                } elseif ($reporte->tipo_reporte === 'Servicio Programado') {
                    ServicioProgramado::where('id_dependencia', $idEmpresa)
                        ->where('id', $reporte->origen_datos_id)
                        ->update(['estatus' => 'Realizado']);
                }
            }

            $reporte->load(['materiales', 'evidencias', 'ejecutores.usuario', 'encuesta', 'creador', 'subdependencia', 'dependencia']);

            return response()->json([
                'success' => true,
                'message' => 'Reporte actualizado con éxito.',
                'data'    => $this->formatReporte($reporte)
            ], 200);
        });
    }

    /**
     * Da formato camelCase uniforme a los atributos para el Frontend en Vue 3
     */
    private function formatReporte(Reporte $r): array
    {
        $materiales = $r->materiales->map(function ($m) {
            return [
                'idItem'   => $m->id_item,
                'material' => $m->material,
                'cantidad' => (float) $m->cantidad,
                'unidad'   => $m->unidad,
            ];
        })->toArray();

        $evidencias = $r->evidencias->map(function ($e) {
            $url = $e->url;
            if ($url && str_starts_with($url, '/uploads/')) {
                $url = url($url);
            }
            return [
                'url'        => $url,
                'comentario' => $e->comentario,
            ];
        })->toArray();

        $ejecutoresIds = $r->ejecutores->pluck('id_usuario')->toArray();

        $empresaLogo = null;
        if ($r->dependencia && $r->dependencia->logo) {
            $empresaLogo = str_starts_with($r->dependencia->logo, 'http') ? $r->dependencia->logo : url($r->dependencia->logo);
        }

        return [
            'idReporte'             => $r->id_reporte,
            'folio'                 => $r->folio,
            'idDependencia'         => $r->id_dependencia,
            'idSubdependencia'      => $r->id_subdependencia,
            'nombreSubdependencia'  => $r->subdependencia ? $r->subdependencia->nombre : null,
            'empresaNombre'         => $r->dependencia ? $r->dependencia->nombre : null,
            'empresaLogo'           => $empresaLogo,
            'creadoPor'             => $r->creado_por,
            'tipoReporte'           => $r->tipo_reporte,
            'origenDatosId'         => $r->origen_datos_id,
            'categoria'             => $r->categoria,
            'fechaElaboracion'      => $r->fecha_elaboracion,
            'responsableId'         => $r->responsable_id,
            'responsableTexto'      => $r->responsable_texto,
            'cargoTexto'            => $r->cargo_texto,
            'fechaHoraInicio'       => $r->fecha_hora_inicio,
            'fechaHoraFin'          => $r->fecha_hora_fin,
            'horaSalidaBase'        => $r->hora_salida_base,
            'horaLlegadaSitio'      => $r->hora_llegada_sitio,
            'horaRegresoABase'      => $r->hora_regreso_a_base,
            'fallaReportada'        => $r->falla_reportada,
            'desarrolloActividades' => $r->desarrollo_actividades,
            'materiales'            => $materiales,
            'evidenciaFotografica'  => $evidencias,
            'ejecutoresIds'         => $ejecutoresIds,
            'esRetrabajo'           => (bool) $r->es_retrabajo,
            'estatusReporte'        => $r->estatus_reporte,
            'encuesta'              => $r->encuesta ? [
                'idEncuesta'   => $r->encuesta->id_encuesta,
                'calificacion' => (int) $r->encuesta->calificacion,
                'comentarios'  => $r->encuesta->comentarios,
                'nombreFirma'  => $r->encuesta->nombre_firma,
                'fecha'        => $r->encuesta->fecha,
            ] : null,
        ];
    }
}
