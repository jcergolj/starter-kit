<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function two_factor_authentication_settings_page_can_be_accessed(): void
    {
        $user = User::factory()->create()->fresh();

        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->get(route('settings.two-factor.edit'))
            ->assertOk();
    }

    #[Test]
    public function two_factor_authentication_can_be_enabled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->put(route('settings.two-factor.update'));

        $user->refresh();

        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    #[Test]
    public function two_factor_authentication_can_be_confirmed(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        // Get the secret and generate a valid code
        $secret = decrypt($user->two_factor_secret);
        $google2fa = app(Google2FA::class);
        $validCode = $google2fa->getCurrentOtp($secret);

        // Confirm 2FA with valid code
        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->put(route('settings.confirmed-two-factor.update'), [
                'code' => $validCode,
            ]);

        $user->refresh();

        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertNotNull($user->two_factor_recovery_codes);
    }

    #[Test]
    public function cannot_confirm_two_factor_with_invalid_code(): void
    {
        $user = User::factory()->create();

        // Enable 2FA
        $this->actingAs($user)
            ->put(route('settings.two-factor.update'));

        $user->refresh();

        // Confirm 2FA with invalid code
        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->put(route('settings.confirmed-two-factor.update'), [
                'code' => '000000',
            ])
            ->assertInvalid(['code']);

        $user->refresh();

        $this->assertNull($user->two_factor_confirmed_at);
    }

    #[Test]
    public function can_view_recovery_codes(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->get(route('settings.recovery-codes.edit'))
            ->assertOk();
    }

    #[Test]
    public function can_regenerate_recovery_codes(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        $originalRecoveryCodes = $user->two_factor_recovery_codes;

        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->put(route('settings.recovery-codes.update'));

        $user->refresh();

        $this->assertNotEquals($originalRecoveryCodes, $user->two_factor_recovery_codes);
    }

    #[Test]
    public function can_disable_two_factor_authentication(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertNotNull($user->two_factor_recovery_codes);

        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->delete(route('settings.two-factor.destroy'));

        $user->refresh();

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNull($user->two_factor_recovery_codes);
    }
}
