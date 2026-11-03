<?php

namespace App\Support;

use RuntimeException;

/**
 * Escritor ZIP incremental con metodo "stored" (sin compresion).
 *
 * Permite generar archivos ZIP de gran tamano sin cargar todo el contenido en
 * memoria y sin depender de ext-zip. Usa data descriptors para no conocer de
 * antemano el CRC/tamano de cada entrada.
 */
class ZipStreamWriter
{
    /** @var array<int, array{name: string, crc: int, size: int, offset: int}> */
    private array $entries = [];

    private int $offset = 0;

    /** @param resource $handle */
    public function __construct(private $handle)
    {
        if (! is_resource($handle)) {
            throw new RuntimeException('Se requiere un recurso de escritura valido.');
        }
    }

    public function add(string $name, string $contents): void
    {
        $this->addStream($name, static function (callable $write) use ($contents): void {
            $write($contents);
        });
    }

    /**
     * @param  callable(callable(string): void): void  $writer
     */
    public function addStream(string $name, callable $writer): void
    {
        $offset = $this->offset;

        $this->write(pack(
            'VvvvvvVVVvv',
            0x04034B50,
            20,
            0x0808,
            0,
            0,
            0,
            0,
            0,
            0,
            strlen($name),
            0
        ).$name);

        $hash = hash_init('crc32b');
        $size = 0;

        $writer(function (string $chunk) use ($hash, &$size): void {
            if ($chunk === '') {
                return;
            }

            $size += strlen($chunk);
            hash_update($hash, $chunk);
            $this->write($chunk);
        });

        $crc = (int) hexdec(hash_final($hash));

        $this->write(pack('VVVV', 0x08074B50, $crc, $size, $size));

        $this->entries[] = [
            'name' => $name,
            'crc' => $crc,
            'size' => $size,
            'offset' => $offset,
        ];
    }

    public function finish(): void
    {
        $central = '';

        foreach ($this->entries as $entry) {
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                20,
                20,
                0x0808,
                0,
                0,
                0,
                $entry['crc'],
                $entry['size'],
                $entry['size'],
                strlen($entry['name']),
                0,
                0,
                0,
                0,
                0,
                $entry['offset']
            ).$entry['name'];
        }

        $this->write($central);
        $this->write(pack(
            'VvvvvVVv',
            0x06054B50,
            0,
            0,
            count($this->entries),
            count($this->entries),
            strlen($central),
            $this->offset - strlen($central),
            0
        ));
    }

    private function write(string $data): void
    {
        $length = strlen($data);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($this->handle, substr($data, $written));

            if ($result === false || $result === 0) {
                throw new RuntimeException('No se pudo escribir el archivo ZIP.');
            }

            $written += $result;
        }

        $this->offset += $length;
    }
}
