<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reset_password_link_screen_can_be_rendered(): void
    {
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

    #[Test]
    public function reset_password_request_rejects_an_untrusted_host(): void
    {
        Config::set('app.domain', 'example.com');
        Notification::fake();
        $user = User::factory()->create();

        $this->post('http://attacker.example.net'.route('password.request', [], false), [
            'email' => $user->email,
        ])->assertStatus(400);

        Notification::assertNothingSent();
    }

    #[Test]
    public function reset_password_request_accepts_the_configured_main_host(): void
    {
        Config::set('app.domain', 'example.com');
        Config::set('app.url', 'http://example.com');
        Notification::fake();
        $user = User::factory()->create();

        $this->post('http://example.com'.route('password.request', [], false), [
            'email' => $user->email,
        ])->assertValid()->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);

        $notification = Notification::sent($user, ResetPassword::class)->first();

        $this->assertSame('example.com', parse_url($notification->toMail($user)->actionUrl, PHP_URL_HOST));
    }

    #[Test]
    public function nested_tenant_hosts_are_rejected(): void
    {
        Config::set('app.domain', 'example.com');

        $this->get('http://nested.tenant.example.com/forgot-password')
            ->assertStatus(400);
    }

    #[Test]
    public function untrusted_hosts_are_rejected_in_single_database_mode(): void
    {
        Config::set([
            'app.domain' => 'example.com',
            'app.single_db_per_app' => true,
        ]);

        $this->get('http://attacker.example.net/forgot-password')
            ->assertStatus(400);
    }
}
