<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Settings;

use App\Http\Controllers\Settings\ProfileController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProfileController::class)]
final class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function edit_requires_authentication(): void
    {
        $response = $this->get(route('settings.profile.edit'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function update_updates_user_profile(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

        $response->assertRedirect();

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@example.com', $user->email);
    }

    #[Test]
    public function update_resets_email_verification_when_email_changes(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'name' => 'Test Name',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => 'Test Name',
                'email' => 'new@example.com',
            ]);

        $response->assertRedirect();

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    #[Test]
    public function update_requires_authentication(): void
    {
        $response = $this->patch(route('settings.profile.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function delete_requires_authentication(): void
    {
        $response = $this->get(route('settings.profile.delete'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function destroy_deletes_user_account(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.delete'))
            ->post(route('settings.profile.destroy'), [
                'password' => 'password',
            ]);

        $response->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertGuest();
    }

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $response = $this->post(route('settings.profile.destroy'), [
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function destroy_validates_password(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.delete'))
            ->post(route('settings.profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect(route('settings.profile.delete'))
            ->assertSessionHasErrors(['password']);
    }
}
