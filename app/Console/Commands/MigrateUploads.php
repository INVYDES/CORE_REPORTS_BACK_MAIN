<?php

namespace App\Console\Commands;

use App\Models\Dependencia;
use App\Models\ReporteEvidencia;
use App\Services\UploadStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateUploads extends Command
{
    protected $signature = 'uploads:migrate
        {--from=public : Disco de origen}
        {--to=ionos : Disco de destino}
        {--delete : Borrar el archivo del disco de origen tras copiarlo}
        {--dry-run : Mostrar lo que se haria sin escribir nada}
        {--chunk=200 : Registros por lote}';

    protected $description = 'Copia evidencias y logotipos existentes a otro disco (por ejemplo IONOS) y actualiza la base de datos.';

    public function handle(UploadStorageService $uploads): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $dry = (bool) $this->option('dry-run');
        $delete = (bool) $this->option('delete');
        $chunk = max(1, (int) $this->option('chunk'));

        if ($from === $to) {
            $this->error('Los discos de origen y destino deben ser distintos.');

            return self::FAILURE;
        }

        foreach ([$from, $to] as $disk) {
            if (! is_array(config('filesystems.disks.'.$disk))) {
                $this->error("El disco [{$disk}] no existe en config/filesystems.disks.");

                return self::FAILURE;
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."Migrando archivos de [{$from}] a [{$to}]");

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        ReporteEvidencia::withoutGlobalScopes()
            ->orderBy('id')
            ->chunkById($chunk, function ($evidencias) use ($uploads, $from, $to, $dry, &$copied, &$skipped, &$failed): void {
                foreach ($evidencias as $evidencia) {
                    $raw = (string) $evidencia->getRawOriginal('url');
                    $path = $evidencia->ruta ?: $uploads->pathFromUrl($raw, $from);

                    if ($path === null || $path === '') {
                        $skipped++;

                        continue;
                    }

                    $isImage = $evidencia->isImageEvidence();
                    $visibility = $uploads->visibilityFor($isImage);

                    try {
                        if (! $this->copy($from, $to, $path, $visibility, $dry)) {
                            $skipped++;

                            continue;
                        }

                        if (! $dry) {
                            DB::table('reporte_evidencias')
                                ->where('id', $evidencia->id)
                                ->update([
                                    'ruta' => $path,
                                    'disco' => $to,
                                    'url' => $uploads->urlForDisk($to, $path),
                                ]);
                        }

                        $copied++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->warn("Evidencia #{$evidencia->id}: ".$e->getMessage());
                    }
                }
            });

        Dependencia::withTrashed()
            ->where(fn ($q) => $q->whereNotNull('logo')->orWhereNotNull('logo_ruta'))
            ->orderBy('id')
            ->chunkById($chunk, function ($empresas) use ($uploads, $from, $to, $dry, &$copied, &$skipped, &$failed): void {
                foreach ($empresas as $empresa) {
                    $path = $empresa->logo_ruta
                        ?: $uploads->pathFromUrl((string) $empresa->logo, $from);

                    if ($path === null || $path === '') {
                        $skipped++;

                        continue;
                    }

                    try {
                        if (! $this->copy($from, $to, $path, $uploads->visibilityFor(true), $dry)) {
                            $skipped++;

                            continue;
                        }

                        if (! $dry) {
                            DB::table('dependencias')
                                ->where('id', $empresa->id)
                                ->update([
                                    'logo_ruta' => $path,
                                    'logo_disco' => $to,
                                    'logo' => $uploads->urlForDisk($to, $path),
                                ]);
                        }

                        $copied++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->warn("Logo de dependencia #{$empresa->id}: ".$e->getMessage());
                    }
                }
            });

        $this->newLine();
        $this->table(
            ['Copiados', 'Omitidos', 'Errores', 'Modo'],
            [[$copied, $skipped, $failed, $dry ? 'dry-run' : 'real']]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function copy(string $from, string $to, string $path, string $visibility, bool $dry): bool
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        if (! $source->exists($path)) {
            if ($target->exists($path)) {
                return true;
            }

            $this->warn("No existe [{$path}] en el disco [{$from}].");

            return false;
        }

        if ($dry) {
            $this->line("[dry-run] {$from}:{$path} -> {$to}:{$path} ({$visibility})");

            return true;
        }

        if (! $target->exists($path)) {
            $target->put($path, $source->get($path), ['visibility' => $visibility]);
        }

        try {
            $target->setVisibility($path, $visibility);
        } catch (Throwable) {
            // Algunos backends (p. ej. R2) no soportan ACLs por objeto.
        }

        if ($this->option('delete')) {
            $source->delete($path);
        }

        return true;
    }
}
