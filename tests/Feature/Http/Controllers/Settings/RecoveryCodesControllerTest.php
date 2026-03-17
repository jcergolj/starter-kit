<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Settings;

use App\Http\Controllers\Settings\RecoveryCodesController;
use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(RecoveryCodesController::class)]
class RecoveryCodesControllerTest extends TestCase
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
        $response = $this->get(route('settings.recovery-codes.edit'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function update_requires_authentication(): void
    {
        $response = $this->patch(route('settings.recovery-codes.update'));

        $response->assertRedirect(route('login'));
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
}
