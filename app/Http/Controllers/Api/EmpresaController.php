<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Usuario;
use App\Services\UploadStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmpresaController extends Controller
{
    private ?UploadStorageService $uploads = null;

    public function __construct(?UploadStorageService $uploads = null)
    {
        $this->uploads = $uploads;

        if (! $this->uploads && class_exists(UploadStorageService::class)) {
            try {
                $this->uploads = app(UploadStorageService::class);
            } catch (\Throwable) {
                $this->uploads = null;
            }
        }
    }

    /**
     * GET /api/v1/empresa o /api/empresa
     * Obtiene los datos comerciales y de configuración de la empresa (dependencia) actual
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $empresa = $this->empresaDelUsuario($request);

            if (! $empresa) {
                $user = $request->user();
                $id = $user->dependencia_id ?? $user->id_dependencia ?? null;
                if ($id) {
                    $empresa = Dependencia::withoutGlobalScopes()->find($id);
                }
            }

            if (! $empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada.',
                ], 404);
            }

            $datos = $this->formatEmpresa($empresa);

            return response()->json([
                'success' => true,
                'data' => $datos,
                'id' => $datos['id'],
                'idDependencia' => $datos['idDependencia'],
                'nombre' => $datos['nombre'],
                'slug' => $datos['slug'],
                'rfc' => $datos['rfc'],
                'tipoLicencia' => $datos['tipoLicencia'],
                'tipo_licencia' => $datos['tipo_licencia'],
                'fechaExpiracionLicencia' => $datos['fechaExpiracionLicencia'],
                'fecha_expiracion' => $datos['fecha_expiracion'],
                'correoContacto' => $datos['correoContacto'],
                'correo_contacto' => $datos['correo_contacto'],
                'correoReportes' => $datos['correoReportes'],
                'correo_reportes' => $datos['correo_reportes'],
                'telefono' => $datos['telefono'],
                'datosFacturacion' => $datos['datosFacturacion'],
                'datos_facturacion' => $datos['datos_facturacion'],
                'activo' => $datos['activo'],
                'activa' => $datos['activa'],
                'fechaRegistro' => $datos['fechaRegistro'],
                'created_at' => $datos['created_at'],
                'limiteUsuarios' => $datos['limiteUsuarios'],
                'limite_usuarios' => $datos['limite_usuarios'],
                'logo' => $datos['logo'],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error en EmpresaController::show: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $user = $request->user();
            $fallbackId = (int) ($user->dependencia_id ?? $user->id_dependencia ?? 1);
            $fallbackNombre = 'Mi Empresa';
            if ($user && isset($user->dependencia) && ! empty($user->dependencia->nombre)) {
                $fallbackNombre = (string) $user->dependencia->nombre;
            }

            $fallback = [
                'id' => $fallbackId,
                'idDependencia' => $fallbackId,
                'nombre' => $fallbackNombre,
                'slug' => Str::slug($fallbackNombre) ?: 'empresa',
                'rfc' => null,
                'tipoLicencia' => 'trial',
                'tipo_licencia' => 'trial',
                'fechaExpiracionLicencia' => null,
                'fecha_expiracion' => null,
                'correoContacto' => null,
                'correo_contacto' => null,
                'correoReportes' => null,
                'correo_reportes' => null,
                'telefono' => null,
                'datosFacturacion' => null,
                'datos_facturacion' => null,
                'activo' => true,
                'activa' => true,
                'fechaRegistro' => null,
                'created_at' => null,
                'limiteUsuarios' => 10,
                'limite_usuarios' => 10,
                'logo' => null,
            ];

            return response()->json([
                'success' => true,
                'data' => $fallback,
                ...$fallback,
            ], 200);
        }
    }

    /**
     * PUT /api/v1/empresa o /api/empresa
     * Actualiza la información general y fiscal de la empresa
     */
    public function update(Request $request): JsonResponse
    {
        try {
            if (! $this->puedeEditar($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para modificar los datos de la empresa.',
                ], 403);
            }

            $empresa = $this->empresaDelUsuario($request);

            if (! $empresa) {
                $user = $request->user();
                $id = $user->dependencia_id ?? $user->id_dependencia ?? null;
                if ($id) {
                    $empresa = Dependencia::withoutGlobalScopes()->find($id);
                }
            }

            if (! $empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada.',
                ], 404);
            }

            // Actualizar campos comerciales y de contacto con seguridad
            $nombreInput = $request->input('nombre');
            if (! empty($nombreInput) && is_string($nombreInput)) {
                $empresa->nombre = trim($nombreInput);
            }

            if ($request->has('rfc')) {
                $rfcInput = $request->input('rfc');
                $empresa->rfc = ! empty($rfcInput) ? trim((string) $rfcInput) : null;
            }

            if ($request->has('correoContacto') || $request->has('correo_contacto')) {
                $correoContacto = $request->input('correoContacto', $request->input('correo_contacto'));
                $empresa->correo_contacto = ! empty($correoContacto) ? trim((string) $correoContacto) : null;
            }

            if ($request->has('correoReportes') || $request->has('correo_reportes')) {
                $correoReportes = $request->input('correoReportes', $request->input('correo_reportes'));
                $empresa->correo_reportes = ! empty($correoReportes) ? trim((string) $correoReportes) : null;
            }

            if ($request->has('telefono')) {
                $telefono = $request->input('telefono');
                $empresa->telefono = ! empty($telefono) ? trim((string) $telefono) : null;
            }

            if ($request->has('datosFacturacion') || $request->has('datos_facturacion')) {
                $datosFacturacion = $request->input('datosFacturacion', $request->input('datos_facturacion'));
                $empresa->datos_facturacion = ! empty($datosFacturacion) ? trim((string) $datosFacturacion) : null;
            }

            $empresa->save();

            $datos = $this->formatEmpresa($empresa);

            return response()->json([
                'success' => true,
                'message' => 'Datos de la empresa actualizados correctamente.',
                'data' => $datos,
                'id' => $datos['id'],
                'idDependencia' => $datos['idDependencia'],
                'nombre' => $datos['nombre'],
                'slug' => $datos['slug'],
                'rfc' => $datos['rfc'],
                'tipoLicencia' => $datos['tipoLicencia'],
                'tipo_licencia' => $datos['tipo_licencia'],
                'fechaExpiracionLicencia' => $datos['fechaExpiracionLicencia'],
                'fecha_expiracion' => $datos['fecha_expiracion'],
                'correoContacto' => $datos['correoContacto'],
                'correo_contacto' => $datos['correo_contacto'],
                'correoReportes' => $datos['correoReportes'],
                'correo_reportes' => $datos['correo_reportes'],
                'telefono' => $datos['telefono'],
                'datosFacturacion' => $datos['datosFacturacion'],
                'datos_facturacion' => $datos['datos_facturacion'],
                'activo' => $datos['activo'],
                'activa' => $datos['activa'],
                'fechaRegistro' => $datos['fechaRegistro'],
                'created_at' => $datos['created_at'],
                'limiteUsuarios' => $datos['limiteUsuarios'],
                'limite_usuarios' => $datos['limite_usuarios'],
                'logo' => $datos['logo'],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error en EmpresaController::update: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar los datos de la empresa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/empresa/logo o /api/empresa/logo
     * Sube y actualiza el logotipo oficial de la empresa (Multipart o Base64)
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        try {
            if (! $this->puedeEditar($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para cambiar el logotipo de la empresa.',
                ], 403);
            }

            $empresa = $this->empresaDelUsuario($request);

            if (! $empresa) {
                $user = $request->user();
                $id = $user->dependencia_id ?? $user->id_dependencia ?? null;
                if ($id) {
                    $empresa = Dependencia::withoutGlobalScopes()->find($id);
                }
            }

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

                if ($this->uploads && method_exists($this->uploads, 'storeFile')) {
                    $path = $this->uploads->storeFile(
                        $request->file('logo'),
                        'logos',
                        method_exists($this->uploads, 'visibilityFor') ? $this->uploads->visibilityFor(true) : 'public'
                    );
                    $ruta = $path;
                    $disco = method_exists($this->uploads, 'diskName') ? $this->uploads->diskName() : 'public';
                } else {
                    $stored = $request->file('logo')->store('logos', 'public');
                    $path = $stored;
                    $ruta = $stored;
                    $disco = 'public';
                }
            }
            // Opción 2: cadena base64 enviada en JSON
            elseif ($request->filled('logo')) {
                $logoInput = trim((string) $request->input('logo'));

                if ($this->uploads && method_exists($this->uploads, 'decodeImageDataUri')) {
                    $decoded = $this->uploads->decodeImageDataUri($logoInput);
                    if ($decoded && method_exists($this->uploads, 'storeContents')) {
                        $path = $this->uploads->storeContents(
                            $decoded['contents'],
                            'logos',
                            $decoded['extension'],
                            method_exists($this->uploads, 'visibilityFor') ? $this->uploads->visibilityFor(true) : 'public'
                        );
                        $ruta = $path;
                        $disco = method_exists($this->uploads, 'diskName') ? $this->uploads->diskName() : 'public';
                    }
                }

                if (! $path && str_starts_with($logoInput, 'http')) {
                    $path = $logoInput;
                } elseif (! $path && (str_starts_with($logoInput, '/uploads/') || str_starts_with($logoInput, 'logos/'))) {
                    $path = basename($logoInput) === $logoInput ? 'logos/'.$logoInput : ltrim($logoInput, '/');
                    $ruta = $path;
                    $disco = 'public';
                }
            }

            if (! $path) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionó una imagen válida para el logotipo.',
                ], 422);
            }

            $empresa->logo = $path;
            try {
                if (Schema::hasColumn('dependencias', 'logo_ruta')) {
                    $empresa->logo_ruta = $ruta;
                }
                if (Schema::hasColumn('dependencias', 'logo_disco')) {
                    $empresa->logo_disco = $disco;
                }
                $empresa->save();
            } catch (\Throwable) {
                DB::table('dependencias')
                    ->where('id', $empresa->id)
                    ->update([
                        'logo' => $path,
                        'updated_at' => now(),
                    ]);
                $empresa->refresh();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logotipo de la empresa actualizado correctamente.',
                'data' => $this->formatEmpresa($empresa),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error en EmpresaController::uploadLogo: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al subir el logotipo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/empresa/smtp
     * Devuelve la configuración SMTP de la empresa actual sin exponer la contraseña
     */
    public function getSmtp(Request $request): JsonResponse
    {
        if (! $this->puedeEditar($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar la configuración SMTP.',
            ], 403);
        }

        $empresa = $this->empresaDelUsuario($request);
        if (! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSmtp($empresa),
        ]);
    }

    /**
     * PUT /api/v1/empresa/smtp
     * Guarda la configuración SMTP personalizada de la empresa
     */
    public function updateSmtp(Request $request): JsonResponse
    {
        if (! $this->puedeEditar($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para modificar la configuración SMTP.',
            ], 403);
        }

        $empresa = $this->empresaDelUsuario($request);
        if (! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        $data = $request->validate([
            'smtp_activo' => 'sometimes|boolean',
            'smtp_host' => 'nullable|string|max:191',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|string|in:tls,ssl,none',
            'smtp_username' => 'nullable|string|max:191',
            'smtp_password' => 'nullable|string|max:500',
            'smtp_from_address' => 'nullable|email|max:191',
            'smtp_from_name' => 'nullable|string|max:191',
        ]);

        $activo = (bool) ($data['smtp_activo'] ?? $empresa->smtp_activo ?? false);
        $host = isset($data['smtp_host']) ? trim((string) $data['smtp_host']) : $empresa->smtp_host;
        $port = isset($data['smtp_port']) ? (int) $data['smtp_port'] : ($empresa->smtp_port ?: 587);
        $enc = isset($data['smtp_encryption']) ? strtolower(trim((string) $data['smtp_encryption'])) : ($empresa->smtp_encryption ?: 'tls');
        $username = isset($data['smtp_username']) ? trim((string) $data['smtp_username']) : $empresa->smtp_username;
        $fromAddress = isset($data['smtp_from_address']) && trim((string) $data['smtp_from_address']) !== ''
            ? trim((string) $data['smtp_from_address'])
            : $username;
        $fromName = isset($data['smtp_from_name']) && trim((string) $data['smtp_from_name']) !== ''
            ? trim((string) $data['smtp_from_name'])
            : $empresa->nombre;

        $empresa->smtp_activo = $activo;
        $empresa->smtp_host = $host ?: null;
        $empresa->smtp_port = $port ?: 587;
        $empresa->smtp_encryption = $enc ?: 'tls';
        $empresa->smtp_username = $username ?: null;
        $empresa->smtp_from_address = $fromAddress ?: null;
        $empresa->smtp_from_name = $fromName ?: null;

        if ($request->filled('smtp_password')) {
            $rawPass = (string) $request->input('smtp_password');
            if ($rawPass !== '' && ! preg_match('/^[•*]+$/u', $rawPass)) {
                $empresa->smtp_password = Crypt::encryptString($rawPass);
            }
        }

        if ($activo && (empty($empresa->smtp_host) || empty($empresa->smtp_username) || empty($empresa->smtp_password))) {
            return response()->json([
                'success' => false,
                'message' => 'Para activar el envío por SMTP completa el servidor (Host), el usuario/correo y la contraseña.',
            ], 422);
        }

        $empresa->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuración de correo saliente (SMTP) guardada correctamente.',
            'data' => $this->formatSmtp($empresa),
        ]);
    }

    /**
     * POST /api/v1/empresa/smtp/probar
     * Envía un correo de prueba con las credenciales proporcionadas o guardadas
     */
    public function probarSmtp(Request $request): JsonResponse
    {
        if (! $this->puedeEditar($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para probar la configuración SMTP.',
            ], 403);
        }

        $empresa = $this->empresaDelUsuario($request);
        if (! $empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        $user = $this->usuario($request);

        $host = trim((string) $request->input('smtp_host', $empresa->smtp_host ?? ''));
        $port = (int) $request->input('smtp_port', $empresa->smtp_port ?: 587);
        $enc = strtolower(trim((string) $request->input('smtp_encryption', $empresa->smtp_encryption ?: 'tls')));
        $username = trim((string) $request->input('smtp_username', $empresa->smtp_username ?? ''));
        $fromAddress = trim((string) $request->input('smtp_from_address', $empresa->smtp_from_address ?: $username));
        $fromName = trim((string) $request->input('smtp_from_name', $empresa->smtp_from_name ?: $empresa->nombre ?: 'CoreReports'));

        $rawPass = (string) $request->input('smtp_password', '');
        if ($rawPass !== '' && ! preg_match('/^[•*]+$/u', $rawPass)) {
            $password = $rawPass;
        } else {
            $password = $empresa->smtpPasswordDecrypted();
        }

        if ($host === '' || $username === '' || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'Completa Servidor SMTP (Host), Usuario y Contraseña antes de probar la conexión.',
            ], 422);
        }

        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $fromAddress = filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : 'no-reply@corereports.com';
        }

        $destinatario = trim((string) $request->input('destinatario', ''));
        if (! filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            $destinatario = filter_var($user->email, FILTER_VALIDATE_EMAIL)
                ? $user->email
                : (filter_var($empresa->correo_reportes, FILTER_VALIDATE_EMAIL)
                    ? $empresa->correo_reportes
                    : $fromAddress);
        }

        $mailerName = 'empresa_smtp_test_' . $empresa->id;
        $scheme = $enc === 'ssl' ? 'smtps' : 'smtp';

        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'scheme' => $scheme,
                'host' => $host,
                'port' => $port ?: 587,
                'encryption' => in_array($enc, ['tls', 'ssl'], true) ? $enc : null,
                'username' => $username,
                'password' => $password,
                'timeout' => 15,
            ],
        ]);

        try {
            Mail::purge($mailerName);
        } catch (\Throwable) {
        }

        try {
            $empresaNombre = $empresa->nombre ?: 'Tu Empresa';
            Mail::mailer($mailerName)->raw(
                "Hola {$user->nombre},\n\n"
                . "Esta es una prueba de conexión SMTP desde CoreReports para {$empresaNombre}.\n"
                . "Servidor: {$host}:{$port} (" . strtoupper($enc) . ")\n"
                . "Remitente: {$fromName} <{$fromAddress}>\n\n"
                . "Tu configuración de correo saliente para envío de reportes funciona correctamente.",
                function ($message) use ($destinatario, $fromAddress, $fromName, $empresaNombre) {
                    $message->to($destinatario)
                        ->from($fromAddress, $fromName)
                        ->subject("Prueba de conexión SMTP exitosa - {$empresaNombre}");
                }
            );

            return response()->json([
                'success' => true,
                'message' => "Conexión SMTP exitosa. Se envió un correo de prueba a {$destinatario}.",
                'destinatario' => $destinatario,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Fallo prueba SMTP para empresa ' . $empresa->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar al servidor SMTP: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSmtp(Dependencia $e): array
    {
        return [
            'smtp_activo' => (bool) ($e->smtp_activo ?? false),
            'smtp_host' => (string) ($e->smtp_host ?? ''),
            'smtp_port' => (int) ($e->smtp_port ?: 587),
            'smtp_encryption' => (string) ($e->smtp_encryption ?: 'tls'),
            'smtp_username' => (string) ($e->smtp_username ?? ''),
            'smtp_from_address' => (string) ($e->smtp_from_address ?: $e->smtp_username ?: ''),
            'smtp_from_name' => (string) ($e->smtp_from_name ?: $e->nombre ?: ''),
            'has_password' => ! empty($e->smtp_password),
        ];
    }

    /**
     * Resuelve el usuario autenticado (self-contained, no depende de base Controller).
     */
    protected function usuario(Request $request): Usuario
    {
        $user = $request->user();

        if (! $user && auth()->check()) {
            $user = auth()->user();
        }

        if ($user instanceof Usuario) {
            return $user;
        }

        if ($user && isset($user->id)) {
            $u = Usuario::find($user->id);
            if ($u) {
                return $u;
            }
        }

        abort(401, 'No autenticado.');
    }

    private function empresaDelUsuario(Request $request): ?Dependencia
    {
        try {
            $user = $this->usuario($request);

            if ($user->relationLoaded('dependencia') && $user->dependencia) {
                return $user->dependencia;
            }

            $idEmpresa = $user->dependencia_id ?? $user->id_dependencia ?? null;

            if ($idEmpresa) {
                $dep = Dependencia::find($idEmpresa);
                if ($dep) {
                    return $dep;
                }

                return Dependencia::withTrashed()->find($idEmpresa);
            }

            return $user->dependencia;
        } catch (\Throwable $e) {
            Log::error('Error en empresaDelUsuario: ' . $e->getMessage());

            return null;
        }
    }

    private function puedeEditar(Request $request): bool
    {
        try {
            $user = $this->usuario($request);

            return in_array((int) $user->rol, [1, 2], true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveLogoUrl(?Dependencia $e): ?string
    {
        if (! $e) {
            return null;
        }

        $path = $e->logo_ruta ?: $e->logo;
        if (! $path || ! is_string($path)) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        try {
            if ($this->uploads && method_exists($this->uploads, 'urlForDisk')) {
                $disk = $e->logo_disco ?: (method_exists($this->uploads, 'diskName') ? $this->uploads->diskName() : 'public');

                return $this->uploads->urlForDisk($disk, $path);
            }
            if ($this->uploads && method_exists($this->uploads, 'url')) {
                return $this->uploads->url($path);
            }
        } catch (\Throwable) {
            // Seguir con el fallback
        }

        try {
            return Storage::disk('public')->url($path);
        } catch (\Throwable) {
            return '/storage/' . ltrim($path, '/');
        }
    }

    /**
     * Formateador estándar de la empresa con compatibilidad camelCase y snake_case
     *
     * @return array<string, mixed>
     */
    private function formatEmpresa(Dependencia $e): array
    {
        $id = $e->id ?? $e->id_dependencia ?? 1;
        $fechaExp = $e->fecha_expiracion ?? $e->fecha_expiracion_licencia;

        $fechaExpStr = null;
        if ($fechaExp) {
            if ($fechaExp instanceof \DateTimeInterface) {
                $fechaExpStr = $fechaExp->format('Y-m-d');
            } else {
                $ts = strtotime((string) $fechaExp);
                $fechaExpStr = $ts ? date('Y-m-d', $ts) : (string) $fechaExp;
            }
        }

        $fechaRegStr = null;
        if ($e->created_at) {
            if ($e->created_at instanceof \DateTimeInterface) {
                $fechaRegStr = $e->created_at->format('Y-m-d');
            } else {
                $ts = strtotime((string) $e->created_at);
                $fechaRegStr = $ts ? date('Y-m-d', $ts) : (string) $e->created_at;
            }
        }

        $nombre = (string) ($e->nombre ?? 'Empresa');
        $slug = Str::slug($nombre);
        if (empty($slug)) {
            $slug = 'empresa-' . $id;
        }

        return [
            'id' => (int) $id,
            'idDependencia' => (int) $id,
            'nombre' => $nombre,
            'slug' => $slug,
            'rfc' => $e->rfc !== null && $e->rfc !== '' ? (string) $e->rfc : null,
            'tipoLicencia' => (string) ($e->tipo_licencia ?? 'trial'),
            'tipo_licencia' => (string) ($e->tipo_licencia ?? 'trial'),
            'fechaExpiracionLicencia' => $fechaExpStr,
            'fecha_expiracion' => $fechaExpStr,
            'correoContacto' => $e->correo_contacto !== null && $e->correo_contacto !== '' ? (string) $e->correo_contacto : null,
            'correo_contacto' => $e->correo_contacto !== null && $e->correo_contacto !== '' ? (string) $e->correo_contacto : null,
            'correoReportes' => $e->correo_reportes !== null && $e->correo_reportes !== '' ? (string) $e->correo_reportes : null,
            'correo_reportes' => $e->correo_reportes !== null && $e->correo_reportes !== '' ? (string) $e->correo_reportes : null,
            'telefono' => $e->telefono !== null && $e->telefono !== '' ? (string) $e->telefono : null,
            'datosFacturacion' => $e->datos_facturacion !== null && $e->datos_facturacion !== '' ? (string) $e->datos_facturacion : null,
            'datos_facturacion' => $e->datos_facturacion !== null && $e->datos_facturacion !== '' ? (string) $e->datos_facturacion : null,
            'activo' => (bool) ($e->activa ?? true),
            'activa' => (bool) ($e->activa ?? true),
            'fechaRegistro' => $fechaRegStr,
            'created_at' => $fechaRegStr,
            'limiteUsuarios' => (int) ($e->limite_usuarios ?? 10),
            'limite_usuarios' => (int) ($e->limite_usuarios ?? 10),
            'logo' => $this->resolveLogoUrl($e),
        ];
    }
}
