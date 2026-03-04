<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProfileDeleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post(route('settings.profile.destroy'), [
            'password' => 'password',
        ])->assertValid()->assertRedirect('/');

        $this->assertGuest();
        $this->assertModelMissing($user);
    }

    #[Test]
    public function correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post(route('settings.profile.destroy'), [
            'password' => 'wrong-password',
        ])->assertInvalid(['password']);

        $this->assertModelExists($user);
    }
}
