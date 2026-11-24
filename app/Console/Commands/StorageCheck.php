<?php

namespace App\Console\Commands;

use App\Services\UploadStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StorageCheck extends Command
{
    protected $signature = 'storage:check
        {disk=ionos : Disco de filesystems a comprobar}
        {--path=healthchecks : Carpeta donde escribir el archivo temporal}
        {--public : Comprobar que la URL publica responde por HTTP}
        {--signed : Comprobar que se genera una URL firmada (S3)}';

    protected $description = 'Verifica credenciales y lectura/escritura de un disco (por ejemplo IONOS S3).';

    public function handle(UploadStorageService $uploads): int
    {
        $disk = (string) $this->argument('disk');
        $config = config('filesystems.disks.'.$disk);

        if (! is_array($config)) {
            $this->error("El disco [{$disk}] no existe en config/filesystems.php.");

            return self::FAILURE;
        }

        if (($config['driver'] ?? null) === 's3') {
            $missing = collect(['key', 'secret', 'bucket', 'region'])
                ->filter(fn (string $key) => empty($config[$key]))
                ->all();

            if ($missing !== []) {
                $this->error('Faltan credenciales/configuracion: '.implode(', ', $missing));
                $this->line('Revisa las variables IONOS_S3_* en .env.');

                return self::FAILURE;
            }
        }

        $path = trim((string) $this->option('path'), '/').'/health-'.Str::uuid().'.txt';
        $contents = 'ok '.now()->toIso8601String();
        $fs = Storage::disk($disk);

        try {
            $fs->put($path, $contents, ['visibility' => 'private']);

            if ($fs->get($path) !== $contents) {
                $this->error('La lectura del archivo temporal no coincide con lo escrito.');

                return self::FAILURE;
            }

            $this->info("Escritura/lectura OK en disco [{$disk}] ({$path}).");

            if ($this->option('public')) {
                $url = $uploads->urlForDisk($disk, $path);
                $this->line('URL publica: '.$url);

                if (! $this->httpOk((string) $url)) {
                    $this->warn('La URL publica no respondio 200. Revisa la politica del bucket o IONOS_S3_PUBLIC_URL.');

                    return self::FAILURE;
                }

                $this->info('Acceso publico OK.');
            }

            if ($this->option('signed')) {
                if (($config['driver'] ?? null) !== 's3') {
                    $this->warn('Las URLs firmadas solo aplican a discos S3.');

                    return self::FAILURE;
                }

                $signed = $fs->temporaryUrl($path, now()->addMinutes(5));
                $this->line('URL firmada: '.strtok($signed, '?'));

                if (! $this->httpOk($signed)) {
                    $this->error('La URL firmada no respondio 200.');

                    return self::FAILURE;
                }

                $this->info('Acceso firmado OK.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Fallo al conectar con el disco ['.$disk.']: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            try {
                $fs->delete($path);
            } catch (Throwable) {
                // El diagnostico ya reporto el error original.
            }
        }
    }

    private function httpOk(string $url): bool
    {
        try {
            return Http::timeout(15)->get($url)->successful();
        } catch (Throwable $e) {
            $this->warn('Error HTTP: '.$e->getMessage());

            return false;
        }
    }
}
