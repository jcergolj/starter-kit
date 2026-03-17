<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use HotwiredLaravel\Hotreload\Http\Middleware\HotreloadMiddleware;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reset_password_screen_can_be_rendered(): void
    {
        $this->withoutMiddleware(HotreloadMiddleware::class);

        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), [
            'email' => $user->email,
        ])->assertValid();

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $this->get('/reset-password/'.$notification->token)
                ->assertOk()
                ->assertViewIs('auth.reset-password')
                ->assertViewHasForm('id="reset-password-form"', 'POST', route('password.update'))
                ->assertFormHasCSRF()
                ->assertFormHasHiddenInput('token')
                ->assertFormHasEmailInput('email')
                ->assertFormHasPasswordInput('password')
                ->assertFormHasPasswordInput('password_confirmation')
                ->assertFormHasSubmitButton();

            return true;
        });
    }

    #[Test]
    public function password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), [
            'email' => $user->email,
        ])->assertValid();

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post(route('password.update', ['token' => $notification->token]), [
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertValid()->assertRedirect(route('login'));

            return true;
        });
    }
}
