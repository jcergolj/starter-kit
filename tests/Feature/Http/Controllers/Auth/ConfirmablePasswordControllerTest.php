<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConfirmablePasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function confirm_password_screen_can_be_rendered(): void
    {
        $this->withoutMiddleware(\HotwiredLaravel\Hotreload\Http\Middleware\HotreloadMiddleware::class);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/user/confirm-password')
            ->assertOk()
            ->assertViewIs('auth.confirm-password')
            ->assertViewHasForm('id="confirm-password-form"', 'POST', route('password.confirm.store'))
            ->assertFormHasCSRF()
            ->assertFormHasPasswordInput('password')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function password_can_be_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post(route('password.confirm.store'), [
            'password' => 'password',
        ])->assertValid()->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function password_is_not_confirmed_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post(route('password.confirm.store'), [
            'password' => 'wrong-password',
        ])->assertInvalid(['password']);
    }
}
