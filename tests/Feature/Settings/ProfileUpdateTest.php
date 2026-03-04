<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('settings.profile.edit'))->assertOk();
    }

    #[Test]
    public function profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->put(route('settings.profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assertValid();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    #[Test]
    public function email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->put(route('settings.profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ])->assertValid();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }
}
