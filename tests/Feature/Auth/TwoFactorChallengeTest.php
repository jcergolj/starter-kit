<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Laravel\Fortify\Fortify;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_with_two_factor_authentication_enabled_is_redirected_to_challenge_on_login(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/two-factor-challenge');
        $this->assertEquals($user->id, Session::get('login.id'));
    }

    #[Test]
    public function can_login_with_two_factor_code(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        // Set up session as if user just entered credentials
        Session::put([
            'login.id' => $user->id,
            'login.remember' => false,
        ]);

        // Generate valid 2FA code
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        $google2fa = app(Google2FA::class);
        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->post('/two-factor-challenge', [
            'code' => $validCode,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function two_fa_is_rate_limited(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        // Set up session as if user just entered credentials
        Session::put([
            'login.id' => $user->id,
            'login.remember' => false,
        ]);

        foreach (range(1, 5) as $_) {
            $this->post('/two-factor-challenge', [
                'code' => '123123',
            ])->assertRedirect(route('two-factor.login'))->assertInvalid('code');
        }

        $this->post('/two-factor-challenge', [
            'code' => '123123',
        ])->assertTooManyRequests();
    }

    #[Test]
    public function can_login_with_recovery_codes(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        // Set up session as if user just entered credentials
        Session::put([
            'login.id' => $user->id,
            'login.remember' => false,
        ]);

        $recoveryCode = json_decode((string) decrypt($user->two_factor_recovery_codes), true)[0];

        $response = $this->post('/two-factor-challenge', [
            'recovery_code' => $recoveryCode,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->assertNotContains($recoveryCode, json_decode((string) decrypt($user->fresh()->two_factor_recovery_codes), true));
    }
}
