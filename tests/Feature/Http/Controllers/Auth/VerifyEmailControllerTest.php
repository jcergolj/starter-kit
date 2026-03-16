<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use HotwiredLaravel\Hotreload\Http\Middleware\HotreloadMiddleware;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VerifyEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function email_verification_screen_can_be_rendered(): void
    {
        $this->withoutMiddleware(HotreloadMiddleware::class);

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertOk()
            ->assertViewIs('auth.verify-email')
            ->assertViewHasForm('id="resend-verification-form"', 'POST', route('verification.send'))
            ->assertFormHasCSRF()
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $response->assertRedirect(route('dashboard', ['verified' => 1], absolute: false));
    }

    #[Test]
    public function email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
