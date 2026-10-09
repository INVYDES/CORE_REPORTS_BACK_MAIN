<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Persistencia de los archivos que suben los usuarios: logotipos de empresa y
 * evidencias fotograficas de reportes.
 *
 * El disco destino se resuelve con `filesystems.uploads`: en local es el disco
 * `public` y en produccion el bucket de Cloudflare R2 (driver s3). Los
 * controladores guardan la ruta relativa devuelta y exponen la URL publica con
 * `url()`, de modo que cambiar de disco no requiere tocar la base de datos.
 */
class UploadStorageService
{
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
    public function storeContents(string $contents, string $folder, string $extension): string
    {
        $path = $this->buildPath($folder, $extension);

        $this->disk()->put($path, $contents);

        return $path;
    }

    /**
     * Guarda un archivo subido por multipart y devuelve la ruta almacenada.
     */
    public function storeFile(UploadedFile $file, string $folder): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';

        return $this->storeContents((string) $file->get(), $folder, $extension);
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

        $contents = base64_decode(substr($value, strpos($value, ',') + 1), true);

        if ($contents === false) {
            return null;
        }

        $type = strtolower($matches[1]);

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
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $config = (array) config('filesystems.disks.'.$this->diskName(), []);

        // Sin dominio publico configurado (R2 sin r2.dev ni dominio propio) se
        // resuelve contra el endpoint path-style del bucket.
        if ($this->diskName() !== 'public' && empty($config['url']) && ! empty($config['endpoint'])) {
            $bucket = trim((string) ($config['bucket'] ?? ''), '/');

            return rtrim((string) $config['endpoint'], '/')."/{$bucket}/".ltrim($path, '/');
        }

        return $this->disk()->url($path);
    }

    private function buildPath(string $folder, string $extension): string
    {
        return trim($folder, '/').'/'.Str::uuid()->toString().'.'.ltrim($extension, '.');
    }
}
