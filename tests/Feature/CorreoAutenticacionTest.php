<?php

namespace Tests\Feature;

use App\Mail\BienvenidaMail;
use App\Mail\RecuperarPasswordMail;
use App\Mail\VerificacionCuentaMail;
use App\Models\Dependencia;
use App\Models\Usuario;
use App\Services\CorreoAutomatizadoService;
use Database\Seeders\LicenciaCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CorreoAutenticacionTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(): Dependencia
    {
        return Dependencia::create([
            'nombre' => 'Correo Test',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => null,
            'limite_usuarios' => 5,
            'limite_reportes_mensuales' => 100,
            'correo_contacto' => 'admin@correo-test.com',
            'activa' => true,
        ]);
    }

    private function usuario(Dependencia $dependencia, bool $verificado = false): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dependencia->id,
            'rol' => 1,
            'nombre' => 'Admin',
            'apellidos' => 'Correo',
            'email' => 'admin@correo-test.com',
            'password' => 'password123',
            'estado' => true,
            'email_verified_at' => $verificado ? now() : null,
        ]);
    }

    private function tokenDeEnlace(string $enlace): string
    {
        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $query);

        return (string) ($query['token'] ?? '');
    }

    public function test_login_de_cuenta_no_verificada_envia_codigo_y_enlace_sin_emitir_token(): void
    {
        Mail::fake();
        $usuario = $this->usuario($this->dependencia());

        $response = $this->postJson('/api/v1/login', [
            'email' => $usuario->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('requiere_verificacion', true)
            ->assertJsonPath('correo_enviado', true)
            ->assertJsonMissing(['token']);

        $this->assertNull($usuario->fresh()->email_verified_at);

        Mail::assertSent(VerificacionCuentaMail::class, function (VerificacionCuentaMail $mail) use ($usuario): bool {
            return $mail->hasTo($usuario->email)
                && preg_match('/^\d{6}$/', $mail->codigo) === 1
                && str_contains($mail->enlace, '/verificar-correo?token=');
        });
    }

    public function test_codigo_de_seis_digitos_verifica_la_cuenta_y_emite_token(): void
    {
        Mail::fake();
        $usuario = $this->usuario($this->dependencia());
        $codigo = null;

        $this->postJson('/api/v1/login', [
            'email' => $usuario->email,
            'password' => 'password123',
        ])->assertOk();

        Mail::assertSent(VerificacionCuentaMail::class, function (VerificacionCuentaMail $mail) use (&$codigo): bool {
            $codigo = $mail->codigo;

            return true;
        });

        $this->postJson('/api/v1/auth/verificar-codigo', [
            'email' => $usuario->email,
            'codigo' => $codigo,
        ])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $this->assertNotNull($usuario->fresh()->email_verified_at);
    }

    public function test_codigo_no_emite_sesion_para_cuenta_ya_verificada_sin_codigo_valido(): void
    {
        Mail::fake();
        $usuario = $this->usuario($this->dependencia(), true);

        $this->postJson('/api/v1/auth/verificar-codigo', [
            'email' => $usuario->email,
            'codigo' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonMissingPath('token');
    }

    public function test_codigo_no_emite_sesion_para_cuenta_desactivada(): void
    {
        Mail::fake();
        $usuario = $this->usuario($this->dependencia());
        $usuario->update(['estado' => false]);

        $this->postJson('/api/v1/auth/verificar-codigo', [
            'email' => $usuario->email,
            'codigo' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonMissingPath('token');

        $this->assertNull($usuario->fresh()->email_verified_at);
    }

    public function test_registro_con_verificacion_desactivada_no_envia_correo_de_verificacion(): void
    {
        Mail::fake();
        config(['correo.verificacion_obligatoria' => false]);
        $this->seed(LicenciaCatalogoSeeder::class);

        $plan = $this->getJson('/api/v1/licencias/disponibles')->json('data.0');

        $response = $this->postJson('/api/v1/public/register-compania', [
            'companyName' => 'Empresa Sin Verificacion',
            'email' => 'sinverificacion@empresa.com',
            'password' => 'password123',
            'firstName' => 'Luis',
            'lastNamePaternal' => 'Prueba',
            'licenciaToken' => $plan['token'],
        ])
            ->assertStatus(201)
            ->assertJsonMissingPath('requiere_verificacion');

        $this->assertNotEmpty($response->json('token'));
        Mail::assertNotSent(VerificacionCuentaMail::class);
        Mail::assertSent(BienvenidaMail::class);
    }

    public function test_transporte_log_no_marca_correos_como_enviados_y_permite_reenvio_real(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $usuario = $this->usuario($this->dependencia());
        $servicio = app(CorreoAutomatizadoService::class);

        $primero = $servicio->enviarBienvenida($usuario);

        $this->assertNotNull($primero);
        $this->assertSame('fallido', $primero->estado);
        $this->assertStringContainsString('modo log', (string) $primero->error);

        config(['mail.default' => 'array']);
        $segundo = $servicio->enviarBienvenida($usuario);

        $this->assertSame('enviado', $segundo->fresh()->estado);
        Mail::assertSent(BienvenidaMail::class);
    }

    public function test_enlace_magico_verifica_la_cuenta(): void
    {
        Mail::fake();
        $usuario = $this->usuario($this->dependencia());
        $enlace = null;

        $this->postJson('/api/v1/login', [
            'email' => $usuario->email,
            'password' => 'password123',
        ])->assertOk();

        Mail::assertSent(VerificacionCuentaMail::class, function (VerificacionCuentaMail $mail) use (&$enlace): bool {
            $enlace = $mail->enlace;

            return true;
        });

        $this->postJson('/api/v1/auth/verificar-enlace', [
            'token' => $this->tokenDeEnlace((string) $enlace),
        ])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $this->assertNotNull($usuario->fresh()->email_verified_at);
    }

    public function test_recuperacion_de_password_envia_enlace_y_permite_cambiarla(): void
    {
        Mail::fake();
        $usuario = $this->usuario($this->dependencia(), true);
        $enlace = null;

        $this->postJson('/api/v1/auth/olvide-password', [
            'email' => $usuario->email,
        ])->assertOk();

        Mail::assertSent(RecuperarPasswordMail::class, function (RecuperarPasswordMail $mail) use (&$enlace): bool {
            $enlace = $mail->enlace;

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $usuario->email,
            'token' => $this->tokenDeEnlace((string) $enlace),
            'password' => 'NuevaPassword123',
            'password_confirmation' => 'NuevaPassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NuevaPassword123', $usuario->fresh()->password));
    }

    public function test_catalogo_publico_entrega_tokens_y_no_ids_de_planes(): void
    {
        Mail::fake();
        $this->seed(LicenciaCatalogoSeeder::class);

        $response = $this->getJson('/api/v1/licencias/disponibles')->assertOk();
        $plan = $response->json('data.0');

        $this->assertArrayNotHasKey('id', $plan);
        $this->assertNotEmpty($plan['token']);

        $this->postJson('/api/v1/public/register-compania', [
            'companyName' => 'Empresa Token',
            'email' => 'token@empresa.com',
            'password' => 'password123',
            'firstName' => 'Ana',
            'lastNamePaternal' => 'Token',
            'licenciaToken' => $plan['token'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('licencia.codigo', $plan['codigo'])
            ->assertJsonPath('requiere_verificacion', true);

        $this->assertDatabaseHas('dependencias', [
            'nombre' => 'Empresa Token',
            'tipo_licencia' => $plan['tipo'] === 'trial' ? 'trial' : $plan['tipo'],
        ]);
    }
}
