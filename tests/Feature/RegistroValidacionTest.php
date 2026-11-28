<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistroValidacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_valida_campos_obligatorios_en_espanol(): void
    {
        $this->postJson('/api/v1/public/register-compania', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.companyName.0', 'El campo nombre de empresa es obligatorio.')
            ->assertJsonPath('errors.email.0', 'El campo correo es obligatorio.')
            ->assertJsonPath('errors.password.0', 'El campo contraseña es obligatorio.')
            ->assertJsonPath('errors.firstName.0', 'El campo nombre(s) es obligatorio.')
            ->assertJsonPath('errors.lastNamePaternal.0', 'El campo apellido paterno es obligatorio.');
    }

    public function test_registro_valida_password_id_de_empleado_y_plan_en_espanol(): void
    {
        $this->postJson('/api/v1/public/register-compania', [
            'companyName' => 'Acme Validación',
            'email' => 'validacion@acme.com',
            'password' => 'corta',
            'firstName' => 'Ana',
            'lastNamePaternal' => 'Pérez',
            'numeroEmpleado' => 'con espacios',
            'licenciaId' => 'abc',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'La contraseña debe tener al menos 8 caracteres.')
            ->assertJsonPath(
                'errors.numeroEmpleado.0',
                'El ID de empleado debe empezar con letra o número, incluir al menos un número y solo usar letras, números, puntos, guiones o guiones bajos.'
            )
            ->assertJsonPath('errors.licenciaId.0', 'El campo plan debe ser un número entero.');
    }

    public function test_registro_avisa_en_espanol_si_el_correo_ya_existe(): void
    {
        $dependencia = Dependencia::create([
            'nombre' => 'Empresa Existente',
            'tipo_licencia' => 'trial',
            'limite_usuarios' => 2,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);

        Usuario::create([
            'dependencia_id' => $dependencia->id,
            'rol' => 1,
            'nombre' => 'Admin',
            'apellidos' => 'Existente',
            'email' => 'ocupado@empresa.com',
            'password' => 'password123',
            'estado' => true,
        ]);

        $this->postJson('/api/v1/public/register-compania', [
            'companyName' => 'Empresa Nueva',
            'email' => 'ocupado@empresa.com',
            'password' => 'password123',
            'firstName' => 'Ana',
            'lastNamePaternal' => 'Pérez',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El campo correo ya está registrado.');
    }
}
