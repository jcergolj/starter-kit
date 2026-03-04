<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Settings;

use App\Http\Controllers\Settings\ConfirmedTwoFactorController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ConfirmedTwoFactorController::class)]
final class ConfirmedTwoFactorControllerTest extends TestCase
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
}
