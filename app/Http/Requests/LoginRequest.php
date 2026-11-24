<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'max:150',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $identifier = trim((string) $value);

                    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
                    $isEmployeeId = preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,49}$/', $identifier) === 1
                        && preg_match('/\d/', $identifier) === 1;

                    if (! $isEmail && ! $isEmployeeId) {
                        $fail('Ingresa un correo electrónico válido o un ID de empleado válido.');
                    }
                },
            ],
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El correo electrónico o ID de empleado es obligatorio',
            'password.required' => 'La contraseña es obligatoria',
        ];
    }
}
