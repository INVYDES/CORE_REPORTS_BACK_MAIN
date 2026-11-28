<?php

namespace App\Rules;

use App\Models\Usuario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El ID de empleado se usa para iniciar sesión y se compara sin distinguir
 * mayúsculas, así que debe ser único entre todas las empresas.
 */
class NumeroEmpleadoUnico implements ValidationRule
{
    public function __construct(private ?int $ignorarId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $numero = trim((string) $value);

        if ($numero === '') {
            return;
        }

        // Incluye usuarios dados de baja: el índice único también los abarca.
        $existe = Usuario::withTrashed()
            ->whereRaw('LOWER(numero_empleado) = ?', [mb_strtolower($numero)])
            ->when($this->ignorarId !== null, fn ($query) => $query->whereKeyNot($this->ignorarId))
            ->exists();

        if ($existe) {
            $fail('Ese ID de empleado ya está en uso. Elige otro.');
        }
    }
}
