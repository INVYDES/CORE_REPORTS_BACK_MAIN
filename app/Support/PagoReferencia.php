<?php

namespace App\Support;

/**
 * Referencia externa de Mercado Pago: "CS-{licenciaId}-{dependenciaId}".
 */
final class PagoReferencia
{
    /**
     * @return array{licencia: int, dependencia: int}|null
     */
    public static function parsear(?string $referencia): ?array
    {
        $referencia = (string) $referencia;

        if (! str_starts_with($referencia, 'CS-')) {
            return null;
        }

        $partes = explode('-', $referencia);

        if (count($partes) !== 3 || ! ctype_digit($partes[1]) || ! ctype_digit($partes[2])) {
            return null;
        }

        return [
            'licencia' => (int) $partes[1],
            'dependencia' => (int) $partes[2],
        ];
    }

    /**
     * Indica si el pago fue generado para la dependencia indicada.
     */
    public static function perteneceA(?string $referencia, int $dependenciaId): bool
    {
        $datos = self::parsear($referencia);

        return $datos !== null && $datos['dependencia'] === $dependenciaId;
    }
}
