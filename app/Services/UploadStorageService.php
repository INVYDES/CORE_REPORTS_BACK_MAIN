<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Persistencia de los archivos que suben los usuarios: logotipos de empresa,
 * fotografias y documentos de reportes.
 *
 * El disco destino se resuelve con `filesystems.uploads`: en local es el disco
 * `public` y en produccion el bucket de IONOS Object Storage (driver s3). Los
 * controladores guardan la ruta relativa devuelta y exponen la URL publica con
 * `url()`, de modo que cambiar de disco no requiere tocar la base de datos.
 */
class UploadStorageService
{
    /** Extensiones consideradas imagenes visibles en linea. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Tamano maximo de una imagen que se incrusta en un PDF (8 MB). */
    public const MAX_INLINE_IMAGE_BYTES = 8 * 1024 * 1024;

    /**
     * Nombre del disco configurado para los archivos subidos.
     */
    public function diskName(): string
    {
        return (string) config('filesystems.uploads', 'public');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * Guarda contenido binario y devuelve la ruta relativa almacenada.
     */
    public function storeContents(string $contents, string $folder, string $extension, ?string $visibility = null): string
    {
        $path = $this->buildPath($folder, $extension);

        $options = $visibility ? ['visibility' => $visibility] : [];
        $this->disk()->put($path, $contents, $options);

        return $path;
    }

    /**
     * Guarda un archivo subido por multipart y devuelve la ruta almacenada.
     */
    public function storeFile(UploadedFile $file, string $folder, ?string $visibility = null): string
    {
        return $this->storeContents(
            (string) $file->get(),
            $folder,
            $this->extensionFor($file),
            $visibility
        );
    }

    /**
     * MIME real del archivo (detectado por fileinfo, no por el cliente).
     */
    public function mimeType(UploadedFile $file): string
    {
        return $file->getMimeType() ?: 'application/octet-stream';
    }

    /** Indica si el archivo es una imagen que puede mostrarse inline. */
    public function isImage(UploadedFile $file): bool
    {
        return $this->isImageType($this->mimeType($file), $this->extensionFor($file));
    }

    /** Indica si un MIME/ruta corresponde a una imagen visible inline. */
    public function isImageType(?string $mime, ?string $path = null): bool
    {
        if ($mime !== null && str_starts_with($mime, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return in_array($extension, self::IMAGE_EXTENSIONS, true);
    }

    /**
     * Visibilidad con la que se guarda un archivo: las imagenes siempre son
     * publicas (se muestran inline); los documentos respetan la visibilidad
     * del disco, de modo que en IONOS quedan privados y se sirven por URL
     * firmada.
     */
    public function visibilityFor(bool $isImage): string
    {
        $default = (string) (config('filesystems.disks.'.$this->diskName().'.visibility') ?? 'public');

        return $isImage ? 'public' : ($default === '' ? 'private' : $default);
    }

    /**
     * Extension segura derivada del MIME real del archivo.
     */
    public function extensionFor(UploadedFile $file): string
    {
        return match ($this->mimeType($file)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'text/csv', 'text/plain' => strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'txt',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => strtolower($file->getClientOriginalExtension() ?: 'bin'),
        };
    }

    /**
     * Decodifica un data-URI base64 de imagen.
     *
     * @return array{contents: string, extension: string, mime: string}|null
     */
    public function decodeImageDataUri(string $value): ?array
    {
        if (! preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,/', $value, $matches)) {
            return null;
        }

        // Solo imágenes raster: no se aceptan SVG ni tipos exóticos por
        // base64, que podrían guardarse y servirse inline desde el bucket.
        $type = strtolower($matches[1]);
        $type = $type === 'jpg' ? 'jpeg' : $type;

        if (! in_array($type, ['jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        $contents = base64_decode(substr($value, strpos($value, ',') + 1), true);

        if ($contents === false || $contents === '') {
            return null;
        }

        return [
            'contents' => $contents,
            'extension' => $type === 'jpeg' ? 'jpg' : $type,
            'mime' => 'image/'.$type,
        ];
    }

    /**
     * URL publica de una ruta almacenada. Las URLs ya absolutas se devuelven tal cual.
     */
    public function url(?string $path): ?string
    {
        return $this->urlForDisk($this->diskName(), $path);
    }

    /**
     * URL publica de una ruta en un disco concreto (util para migraciones).
     */
    public function urlForDisk(string $disk, ?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $config = (array) config('filesystems.disks.'.$disk, []);

        // Sin dominio publico configurado se resuelve contra el endpoint del
        // bucket. IONOS usa virtual-host-style por defecto
        // (https://bucket.s3.region.ionoscloud.com/key) y path-style si se
        // habilita IONOS_S3_USE_PATH_STYLE_ENDPOINT.
        if ($disk !== 'public' && empty($config['url']) && ! empty($config['endpoint'])) {
            $bucket = trim((string) ($config['bucket'] ?? ''), '/');
            $endpoint = rtrim((string) $config['endpoint'], '/');
            $key = ltrim($path, '/');

            if (! empty($config['use_path_style_endpoint'])) {
                return "{$endpoint}/{$bucket}/{$key}";
            }

            $host = parse_url($endpoint, PHP_URL_HOST) ?: '';
            $scheme = parse_url($endpoint, PHP_URL_SCHEME) ?: 'https';

            if ($host !== '' && $bucket !== '') {
                return "{$scheme}://{$bucket}.{$host}/{$key}";
            }

            return "{$endpoint}/{$bucket}/{$key}";
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * Convierte una referencia almacenada (ruta o URL) en un data-URI con la
     * imagen leida del disco.
     *
     * Se usa para incrustar logos y evidencias en los PDF: nunca se descarga
     * un recurso remoto, de modo que un usuario no puede hacer que el servidor
     * pida URLs internas (169.254.169.254, localhost, redes privadas) al
     * generar el documento. Cualquier referencia que no resuelva a un archivo
     * del disco propio devuelve null y el PDF se genera sin esa imagen.
     */
    public function inlineImage(?string $path, ?string $disk = null): ?string
    {
        $path = trim((string) $path);

        if ($path === '' || Str::startsWith($path, 'data:')) {
            return null;
        }

        // Una URL absoluta solo vale si pertenece a nuestro disco (se deduce
        // su clave); si no, se descarta sin hacer ninguna peticion HTTP.
        if (Str::startsWith($path, ['http://', 'https://'])) {
            $path = (string) $this->pathFromUrl($path, $disk);
        }

        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (Str::startsWith($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $disk ??= $this->diskName();

        try {
            $filesystem = Storage::disk($disk);

            if (! $filesystem->exists($path) || $filesystem->size($path) > self::MAX_INLINE_IMAGE_BYTES) {
                return null;
            }

            $contents = $filesystem->get($path);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        // Solo imagenes raster: el MIME se detecta del contenido, no de la
        // extension ni de lo que declare el cliente.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /**
     * Deduce la clave/ruta interna a partir de una URL almacenada.
     *
     * Devuelve null cuando la URL no pertenece al disco indicado (por ejemplo
     * un enlace externo que debe conservarse tal cual).
     */
    public function pathFromUrl(?string $url, ?string $disk = null): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $path = ltrim($url, '/');

            return str_contains($path, '..') ? null : $path;
        }

        $disk ??= $this->diskName();
        $config = (array) config('filesystems.disks.'.$disk, []);
        $candidates = [];

        $public = rtrim((string) ($config['url'] ?? ''), '/');
        if ($public !== '') {
            $candidates[] = $public;
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl !== '') {
            $candidates[] = $appUrl.'/storage';
        }

        $endpoint = rtrim((string) ($config['endpoint'] ?? ''), '/');
        $bucket = trim((string) ($config['bucket'] ?? ''), '/');

        if ($endpoint !== '' && $bucket !== '') {
            $candidates[] = rtrim($endpoint, '/').'/'.$bucket;

            $host = parse_url($endpoint, PHP_URL_HOST) ?: '';
            $scheme = parse_url($endpoint, PHP_URL_SCHEME) ?: 'https';

            if ($host !== '') {
                $candidates[] = "{$scheme}://{$bucket}.{$host}";
            }
        }

        foreach ($candidates as $base) {
            if (Str::startsWith($url, $base.'/')) {
                return ltrim(substr($url, strlen($base)), '/');
            }
        }

        $path = ltrim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $knownHosts = array_filter([
            strtolower((string) (parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: '')),
            strtolower((string) (parse_url($public, PHP_URL_HOST) ?: '')),
            strtolower((string) (parse_url($endpoint, PHP_URL_HOST) ?: '')),
        ]);

        $virtualHost = $bucket !== '' && $endpoint !== ''
            ? strtolower($bucket.'.'.(parse_url($endpoint, PHP_URL_HOST) ?: ''))
            : '';

        if ($path !== ''
            && (in_array($host, $knownHosts, true) || ($virtualHost !== '' && $host === $virtualHost))
            && Str::startsWith($path, ['evidencias/', 'logos/'])) {
            return $path;
        }

        if (Str::startsWith($path, 'storage/evidencias/') || Str::startsWith($path, 'storage/logos/')) {
            return substr($path, strlen('storage/'));
        }

        return null;
    }

    private function buildPath(string $folder, string $extension): string
    {
        return trim($folder, '/').'/'.Str::uuid()->toString().'.'.ltrim($extension, '.');
    }
}
