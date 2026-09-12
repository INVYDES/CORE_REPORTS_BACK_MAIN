<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_up_returns_200(): void
    {
        $this->get('/up')->assertStatus(200);
    }

    public function test_api_login_validation(): void
    {
        $this->postJson('/api/login', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_public_register_compania_validation(): void
    {
        $this->postJson('/api/public/register-compania', [])->assertStatus(422);
    }
}
