<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use HotwiredLaravel\Hotreload\Http\Middleware\HotreloadMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuthenticatedSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_screen_can_be_rendered(): void
    {
        $this->withoutMiddleware(HotreloadMiddleware::class);

        $this->get('/login')
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertViewHasForm('id="login-form"', 'POST', route('login.store'))
            ->assertFormHasCSRF()
            ->assertFormHasEmailInput('email')
            ->assertFormHasPasswordInput('password')
            ->assertFormHasCheckboxInput('remember')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertValid()->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    #[Test]
    public function users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    #[Test]
    public function users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
