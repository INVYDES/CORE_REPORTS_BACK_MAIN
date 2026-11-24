<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(): Usuario
    {
        $dep = Dependencia::create([
            'nombre' => 'Dep Test',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(30),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'activa' => true,
        ]);

        return Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => 1,
            'nombre' => 'Admin',
            'apellidos' => 'Test',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'estado' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_login_validation_fails_for_empty_request(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_validation_fails_for_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'invalid-email-format',
            'password' => 'somepassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_permite_sesiones_simultaneas_en_varios_dispositivos(): void
    {
        $user = $this->crearUsuario();

        $primera = $this->postJson('/api/v1/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ])->assertOk();

        $segunda = $this->postJson('/api/v1/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertNotSame($primera->json('token'), $segunda->json('token'));

        // El token del "primer dispositivo" sigue siendo válido tras el segundo login.
        $this->withToken($primera->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('email', 'admin@test.com');

        $this->withToken($segunda->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('email', 'admin@test.com');

        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_logout_solo_revoca_el_token_actual(): void
    {
        $user = $this->crearUsuario();

        $primera = $this->postJson('/api/v1/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ])->assertOk()->json('token');

        $segunda = $this->postJson('/api/v1/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ])->assertOk()->json('token');

        $this->withToken($primera)->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->withToken($segunda)->getJson('/api/v1/me')->assertOk();
    }
}
