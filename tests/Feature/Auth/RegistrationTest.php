<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    #[Test]
    public function new_users_can_register(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertValid();

        $response->assertRedirect('http://testuser.'.config('app.domain').'/login?status=verify-email');

        $this->assertAuthenticated();
    }
}
