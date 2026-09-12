<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthTest extends TestCase
{
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
}
