<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication;

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
    public function unknown_accounts_receive_a_generic_json_response(): void
    {
        Notification::fake();

        $this->postJson(route('password.email'), [
            'email' => 'missing@example.com',
        ])->assertOk()->assertExactJson([
            'message' => 'If an account exists for that email address, you will receive a password reset link.',
        ]);

        Notification::assertNothingSent();

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    #[Test]
    public function known_accounts_receive_the_same_json_response_during_the_email_cooldown(): void
    {
        $user = User::factory()->create();

        Notification::fake();

        $this->postJson(route('password.email'), [
            'email' => $user->email,
        ])->assertOk()->assertExactJson([
            'message' => 'If an account exists for that email address, you will receive a password reset link.',
        ]);

        $this->postJson(route('password.email'), [
            'email' => $user->email,
        ])->assertOk()->assertExactJson([
            'message' => 'If an account exists for that email address, you will receive a password reset link.',
        ]);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);

        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    #[Test]
    public function browser_requests_receive_the_same_response_for_known_unknown_and_cooldown_addresses(): void
    {
        $user = User::factory()->create();

        Notification::fake();

        foreach ([$user->email, $user->email, 'missing@example.com'] as $email) {
            $this->from(route('password.request'))->post(route('password.email'), [
                'email' => $email,
            ])->assertRedirect(route('password.request'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas(
                    'status',
                    'If an account exists for that email address, you will receive a password reset link.',
                );
        }

        Notification::assertSentToTimes($user, ResetPassword::class, 1);

        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    #[Test]
    public function invalid_email_addresses_still_receive_validation_errors(): void
    {
        Notification::fake();

        $this->postJson(route('password.email'), [
            'email' => 'not-an-email',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        Notification::assertNothingSent();

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    #[Test]
    public function recovery_requests_are_limited_per_ip_across_different_addresses(): void
    {
        $user = User::factory()->create();

        Notification::fake();

        $this->freezeTime();

        foreach (range(1, 5) as $number) {
            $this->postJson(route('password.email'), [
                'email' => "missing{$number}@example.com",
            ])->assertOk();
        }

        $this->postJson(route('password.email'), [
            'email' => $user->email,
        ])->assertTooManyRequests()->assertHeader('Retry-After');

        Notification::assertNothingSent();

        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->postJson(route('password.email'), [
                'email' => 'another-missing@example.com',
            ])->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $this->travel(61)->seconds();

        $this->postJson(route('password.email'), [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    #[Test]
    public function reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertViewIs('authentication::auth.forgot-password')
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
        ])->assertBadRequest();

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

        $this->get('http://nested.tenant.example.com/forgot-password')->assertBadRequest();
    }

    #[Test]
    public function untrusted_hosts_are_rejected_in_single_database_mode(): void
    {
        Config::set([
            'app.domain' => 'example.com',
            'app.single_db_per_app' => true,
        ]);

        $this->get('http://attacker.example.net/forgot-password')->assertBadRequest();
    }
}
