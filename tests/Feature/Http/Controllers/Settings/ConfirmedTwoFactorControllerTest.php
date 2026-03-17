<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Settings;

use App\Http\Controllers\Settings\ConfirmedTwoFactorController;
use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

#[CoversClass(ConfirmedTwoFactorController::class)]
class ConfirmedTwoFactorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two factor authentication is not enabled.');
        }
    }

    #[Test]
    public function edit_requires_authentication(): void
    {
        $response = $this->get(route('settings.confirmed-two-factor.edit'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function update_requires_authentication(): void
    {
        $response = $this->patch(route('settings.confirmed-two-factor.update'), [
            'code' => '123456',
        ]);

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function two_factor_authentication_can_be_confirmed(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        $secret = decrypt($user->two_factor_secret);
        $google2fa = app(Google2FA::class);
        $validCode = $google2fa->getCurrentOtp($secret);

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

        $this->actingAs($user)
            ->put(route('settings.two-factor.update'));

        $user->refresh();

        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->put(route('settings.confirmed-two-factor.update'), [
                'code' => '000000',
            ])
            ->assertInvalid(['code']);

        $user->refresh();

        $this->assertNull($user->two_factor_confirmed_at);
    }
}
