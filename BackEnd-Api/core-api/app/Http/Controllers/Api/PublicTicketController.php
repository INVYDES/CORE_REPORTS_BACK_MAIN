<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Notificacion;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\UploadStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicTicketController extends Controller
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

    private function findEmpresa(string $slug): Dependencia
    {
        $clean = strtolower(trim($slug));

        // 1. Si existe columna 'slug' en dependencias
        if (Schema::hasColumn('dependencias', 'slug')) {
            $dep = Dependencia::withoutGlobalScopes()->where('slug', $clean)->first();
            if ($dep) {
                return $dep;
            }
        }

        // 2. Si es ID numérico directo
        if (is_numeric($clean)) {
            $dep = Dependencia::withoutGlobalScopes()->find((int) $clean);
            if ($dep) {
                return $dep;
            }
        }

        // 3. Buscar por coincidencia con Str::slug(nombre) o nombre en minúsculas
        $todas = Dependencia::withoutGlobalScopes()->get();
        foreach ($todas as $d) {
            $candidateSlug = Str::slug($d->nombre);
            if ($candidateSlug === $clean || strtolower((string) $d->nombre) === $clean) {
                return $d;
            }
        }

        abort(404, 'Empresa no encontrada.');
    }

    private function generarFolioSecuencial(int $dependenciaId): string
    {
        $prefijo = 'TKT-'.now('America/Mexico_City')->format('Ymd');

        $ultimo = Ticket::withoutGlobalScopes()
            ->where('dependencia_id', $dependenciaId)
            ->where('folio', 'like', $prefijo.'-%')
            ->orderByDesc('folio')
            ->value('folio');

        $secuencia = $ultimo ? ((int) substr((string) $ultimo, -4)) + 1 : 1;

        return sprintf('%s-%04d', $prefijo, $secuencia);
    }

    /**
     * GET /api/v1/public/empresa/{slug}
     * Obtiene los datos públicos de la empresa para personalizar el portal de autoservicio
     */
    public function empresa(string $slug): JsonResponse
    {
        $empresa = $this->findEmpresa($slug);

        $logoUrl = null;
        try {
            $path = $empresa->logo_ruta ?: $empresa->logo;
            if ($path && is_string($path)) {
                $path = trim($path);
                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    $logoUrl = $path;
                } elseif ($this->uploads && method_exists($this->uploads, 'urlForDisk') && $empresa->logo_ruta) {
                    $disk = $empresa->logo_disco ?: (method_exists($this->uploads, 'diskName') ? $this->uploads->diskName() : 'public');
                    $logoUrl = $this->uploads->urlForDisk($disk, $empresa->logo_ruta);
                } elseif ($this->uploads && method_exists($this->uploads, 'url')) {
                    $logoUrl = $this->uploads->url($path);
                } else {
                    $logoUrl = Storage::disk('public')->url($path);
                }
            }
        } catch (\Throwable) {
            $logoUrl = null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre,
                'slug' => Str::slug($empresa->nombre),
                'logo' => $logoUrl,
                'telefono' => $empresa->telefono,
                'correoContacto' => $empresa->correo_contacto,
            ],
        ]);
    }

    /**
     * POST /api/v1/public/empresa/{slug}/tickets
     * Registra un ticket proveniente del enlace público del cliente
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $empresa = $this->findEmpresa($slug);

        $data = $request->validate([
            'lugar' => 'required|string|max:200',
            'solicitante' => 'required|string|max:150',
            'telefono' => 'required|string|max:50',
            'email' => 'nullable|email|max:150',
            'asunto' => 'required|string|max:200',
            'descripcion' => 'required|string|max:3000',
            'prioridad' => 'nullable|in:baja,media,alta',
            'evidencias' => 'nullable|array|max:3',
            'evidencias.*' => 'nullable|string', // Imágenes en Base64
        ]);

        $lugar = trim($data['lugar']);
        $solicitante = trim($data['solicitante']);
        $telefono = trim($data['telefono']);
        $email = !empty($data['email']) ? trim($data['email']) : 'No proporcionado';
        $asuntoBase = trim($data['asunto']);
        $descripcionFalla = trim($data['descripcion']);
        $prioridad = $data['prioridad'] ?? 'media';

        // Guardar imágenes de evidencia si existen
        $urlsEvidencias = [];
        $evidenciasInput = (array) ($request->input('evidencias', []));
        foreach ($evidenciasInput as $idx => $b64) {
            if (!is_string($b64)) {
                continue;
            }
            if (preg_match('#^data:image/(jpeg|jpg|png|webp);base64,(.+)$#is', $b64, $m)) {
                $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                $bytes = base64_decode($m[2], true);
                if ($bytes !== false) {
                    $path = 'evidencias_tickets/'.$empresa->id.'/tkt_'.uniqid('ext_', true).'.'.$ext;
                    try {
                        Storage::disk('public')->put($path, $bytes);
                        $urlsEvidencias[] = Storage::disk('public')->url($path);
                    } catch (\Throwable $e) {
                        Log::warning('No se pudo guardar imagen de evidencia externa: '.$e->getMessage());
                    }
                }
            }
        }

        // Construir descripción formateada con los datos del lugar y contacto (sin emojis)
        $descripcionFormateada = "Lugar / Sucursal: {$lugar}\n"
            . "Solicitante: {$solicitante}\n"
            . "Teléfono: {$telefono}\n"
            . "Correo: {$email}\n\n"
            . "Descripción de la Falla:\n{$descripcionFalla}";

        if (!empty($urlsEvidencias)) {
            $descripcionFormateada .= "\n\nEvidencias Fotográficas Adjuntas:\n";
            foreach ($urlsEvidencias as $num => $url) {
                $n = $num + 1;
                $descripcionFormateada .= "- Foto {$n}: {$url}\n";
            }
        }

        $asuntoCompleto = "[{$lugar}] {$asuntoBase}";
        if (mb_strlen($asuntoCompleto) > 200) {
            $asuntoCompleto = mb_substr($asuntoCompleto, 0, 197).'...';
        }

        $depId = (int) $empresa->id;

        $ticket = DB::transaction(function () use ($depId, $asuntoCompleto, $descripcionFormateada, $solicitante, $lugar, $telefono, $prioridad) {
            $folio = $this->generarFolioSecuencial($depId);

            $tkt = new Ticket();
            $tkt->forceFill([
                'dependencia_id' => $depId,
                'folio' => $folio,
                'asunto' => $asuntoCompleto,
                'descripcion' => $descripcionFormateada,
                'solicitante' => $solicitante,
                'cargo' => 'Cliente / Solicitante',
                'prioridad' => $prioridad,
                'estatus' => 'abierto',
                'fecha_solicitud' => now('America/Mexico_City')->format('Y-m-d H:i:s'),
            ]);
            $tkt->save();

            return $tkt;
        });

        // Crear notificación interna para administradores y coordinadores de la empresa
        try {
            $usuariosNotificar = Usuario::where('dependencia_id', $depId)
                ->whereIn('rol', ['admin', 'administrador', 'supervisor', 'coordinador'])
                ->get();

            foreach ($usuariosNotificar as $u) {
                Notificacion::create([
                    'dependencia_id' => $depId,
                    'usuario_id' => $u->id,
                    'tipo' => 'ticket_nuevo_cliente',
                    'titulo' => "Nuevo Ticket de Cliente: {$ticket->folio}",
                    'asunto' => "Nuevo reporte en {$lugar} levantado por {$solicitante}",
                    'cuerpo' => "El cliente en {$lugar} reportó: '{$asuntoBase}'. Folio: {$ticket->folio}",
                    'referencia_tipo' => 'ticket',
                    'referencia_id' => $ticket->id,
                    'estatus' => 'no_leida',
                ]);
            }
        } catch (\Throwable $t) {
            Log::warning('No se pudo crear notificación interna de ticket público: '.$t->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => '¡Solicitud recibida con éxito!',
            'data' => [
                'folio' => $ticket->folio,
                'empresa' => $empresa->nombre,
                'lugar' => $lugar,
                'solicitante' => $solicitante,
                'fecha' => now('America/Mexico_City')->format('Y-m-d H:i'),
            ],
        ], 201);
    }
}

