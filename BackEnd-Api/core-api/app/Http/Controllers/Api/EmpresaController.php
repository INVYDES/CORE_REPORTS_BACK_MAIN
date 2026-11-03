<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Services\UploadStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmpresaController extends Controller
{
    public function __construct(private readonly UploadStorageService $uploads) {}

    /**
     * GET /api/v1/empresa o /api/empresa
     * Obtiene los datos comerciales y de configuración de la empresa (dependencia) actual
     */
    public function show(Request $request): JsonResponse
    {
        $empresa = $this->empresaDelUsuario($request);

        if (! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatEmpresa($empresa),
        ], 200);
    }

    /**
     * PUT /api/v1/empresa o /api/empresa
     * Actualiza la información general y fiscal de la empresa
     */
    public function update(Request $request): JsonResponse
    {
        if (! $this->puedeEditar($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para modificar los datos de la empresa.',
            ], 403);
        }

        $empresa = $this->empresaDelUsuario($request);

        if (! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        $campos = [
            'nombre' => 'nombre',
            'rfc' => 'rfc',
            'correoContacto' => 'correo_contacto',
            'correoReportes' => 'correo_reportes',
            'telefono' => 'telefono',
            'datosFacturacion' => 'datos_facturacion',
        ];

        foreach ($campos as $entrada => $columna) {
            $snake = Str::snake($entrada);

            if ($request->has($entrada)) {
                $empresa->{$columna} = trim((string) $request->input($entrada));
            } elseif ($request->has($snake)) {
                $empresa->{$columna} = trim((string) $request->input($snake));
            }
        }

        $empresa->save();

        return response()->json([
            'success' => true,
            'message' => 'Datos de la empresa actualizados correctamente.',
            'data' => $this->formatEmpresa($empresa),
        ], 200);
    }

    /**
     * POST /api/v1/empresa/logo o /api/empresa/logo
     * Sube y actualiza el logotipo oficial de la empresa (Multipart o Base64)
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        if (! $this->puedeEditar($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para cambiar el logotipo de la empresa.',
            ], 403);
        }

        $empresa = $this->empresaDelUsuario($request);

        if (! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        $path = null;
        $ruta = null;
        $disco = null;

        // Opción 1: archivo multipart tradicional
        if ($request->hasFile('logo')) {
            $validator = Validator::make($request->all(), [
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            ], [
                'logo.image' => 'El archivo debe ser una imagen válida.',
                'logo.max' => 'La imagen no debe superar los 4 MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $path = $this->uploads->storeFile(
                $request->file('logo'),
                'logos',
                $this->uploads->visibilityFor(true)
            );
            $ruta = $path;
            $disco = $this->uploads->diskName();
        }
        // Opción 2: cadena base64 enviada en JSON
        elseif ($request->filled('logo')) {
            $logoInput = trim((string) $request->input('logo'));

            $decoded = $this->uploads->decodeImageDataUri($logoInput);

            if ($decoded) {
                $path = $this->uploads->storeContents(
                    $decoded['contents'],
                    'logos',
                    $decoded['extension'],
                    $this->uploads->visibilityFor(true)
                );
                $ruta = $path;
                $disco = $this->uploads->diskName();
            } elseif (str_starts_with($logoInput, 'http')) {
                $path = $logoInput;
            } elseif (str_starts_with($logoInput, '/uploads/') || str_starts_with($logoInput, 'logos/')) {
                // Referencia a un logotipo ya almacenado
                $path = basename($logoInput) === $logoInput
                    ? 'logos/'.$logoInput
                    : ltrim($logoInput, '/');
                $ruta = $this->uploads->pathFromUrl($path) ?: $path;
                $disco = $this->uploads->diskName();
            }
        }

        if (! $path) {
            return response()->json([
                'success' => false,
                'message' => 'No se proporcionó una imagen válida para el logotipo.',
            ], 422);
        }

        $empresa->logo = $path;
        $empresa->logo_ruta = $ruta;
        $empresa->logo_disco = $disco;
        $empresa->save();

        return response()->json([
            'success' => true,
            'message' => 'Logotipo de la empresa actualizado correctamente.',
            'data' => $this->formatEmpresa($empresa),
        ], 200);
    }

    private function empresaDelUsuario(Request $request): ?Dependencia
    {
        $user = $this->usuario($request);
        $idEmpresa = $user->dependencia_id ?? $user->id_dependencia ?? null;

        return $idEmpresa ? Dependencia::find($idEmpresa) : null;
    }

    private function puedeEditar(Request $request): bool
    {
        return in_array((int) $this->usuario($request)->rol, [1, 2], true);
    }

    /**
     * Formateador estándar de la empresa con compatibilidad camelCase
     *
     * @return array<string, mixed>
     */
    private function formatEmpresa(Dependencia $e): array
    {
        $id = $e->id ?? $e->id_dependencia;
        $fechaExp = $e->fecha_expiracion ?? $e->fecha_expiracion_licencia;

        return [
            'idDependencia' => (int) $id,
            'nombre' => $e->nombre,
            'rfc' => $e->rfc,
            'tipoLicencia' => $e->tipo_licencia,
            'fechaExpiracionLicencia' => $fechaExp ? date('Y-m-d', strtotime((string) $fechaExp)) : null,
            'correoContacto' => $e->correo_contacto,
            'correoReportes' => $e->correo_reportes,
            'telefono' => $e->telefono,
            'datosFacturacion' => $e->datos_facturacion,
            'activo' => (bool) ($e->activa ?? true),
            'fechaRegistro' => $e->created_at ? $e->created_at->format('Y-m-d') : null,
            'limiteUsuarios' => (int) ($e->limite_usuarios ?? 10),
            'logo' => $e->logo_ruta
                ? $this->uploads->urlForDisk($e->logo_disco ?: $this->uploads->diskName(), $e->logo_ruta)
                : $this->uploads->url($e->logo),
        ];
    }
}
