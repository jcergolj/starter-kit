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

final class PasswordResetLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reset_password_link_screen_can_be_rendered(): void
    {
        $this->withoutMiddleware(HotreloadMiddleware::class);

        $this->get('/forgot-password')
            ->assertOk()
            ->assertViewIs('auth.forgot-password')
            ->assertViewHasForm('id="forgot-password-form"', 'POST', route('password.email'))
            ->assertFormHasCSRF()
            ->assertFormHasEmailInput('email')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), [
            'email' => $user->email,
        ])->assertValid();

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
