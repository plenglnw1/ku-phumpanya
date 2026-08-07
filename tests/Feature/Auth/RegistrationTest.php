<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_redirects_when_password_auth_disabled(): void
    {
        config()->set('auth_flow.password_register_enabled', false);

        $response = $this->get('/register');

        $response->assertRedirect(route('welcome', absolute: false));
    }

    public function test_registration_screen_can_be_rendered_when_password_auth_enabled(): void
    {
        config()->set('auth_flow.password_register_enabled', true);

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_when_password_auth_enabled(): void
    {
        config()->set('auth_flow.password_register_enabled', true);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect($this->frontendRedirect('/learn/'));
    }

    public function test_public_register_is_blocked_when_password_auth_disabled(): void
    {
        config()->set('auth_flow.password_register_enabled', false);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('welcome', absolute: false));
        $this->assertGuest();
    }
}
