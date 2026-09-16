<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Features\Settings\Controllers\TwoFactorController;
use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TwoFactorController::class)]
class TwoFactorControllerTest extends TestCase
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
        $response = $this->get(route('settings.two-factor.edit'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function update_requires_authentication(): void
    {
        $response = $this->patch(route('settings.two-factor.update'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $response = $this->delete(route('settings.two-factor.destroy'));

        $response->assertRedirect(route('login'));
    }

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
    public function pending_two_factor_setup_shows_only_the_continue_control(): void
    {
        $user = User::factory()->create()->fresh();

        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->put(route('settings.two-factor.update'));

        $this->get(route('settings.two-factor.edit'))
            ->assertOk()
            ->assertSee('Setup pending')
            ->assertSee('Continue setup')
            ->assertDontSee('Enable 2FA');
    }

    #[Test]
    public function two_factor_authentication_can_be_enabled(): void
    {
        $user = User::factory()->create()->fresh();

        $this->actingAs($user)
            ->withoutMiddleware(RequirePassword::class)
            ->put(route('settings.two-factor.update'));

        $user->refresh();

        $this->assertNotNull($user->two_factor_secret);

        $this->assertNull($user->two_factor_confirmed_at);
    }

    #[Test]
    public function enabling_two_factor_authentication_requires_password_confirmation(): void
    {
        $user = User::factory()->create()->fresh();

        $this->actingAs($user)
            ->put(route('settings.two-factor.update'))
            ->assertRedirect(route('password.confirm'));

        $this->assertNull($user->fresh()->two_factor_secret);
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

    #[Test]
    public function disabling_two_factor_authentication_requires_password_confirmation(): void
    {
        $user = User::factory()->withTwoFactorAuthenticationEnabled()->create();

        $this->actingAs($user)
            ->delete(route('settings.two-factor.destroy'))
            ->assertRedirect(route('password.confirm'));

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }
}
