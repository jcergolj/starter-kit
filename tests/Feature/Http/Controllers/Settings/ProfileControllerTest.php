<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Settings;

use App\Http\Controllers\Settings\ProfileController;
use App\Models\User;
use HotwiredLaravel\Hotreload\Http\Middleware\HotreloadMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProfileController::class)]
class ProfileControllerTest extends TestCase
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
    public function delete_page_is_displayed(): void
    {
        $this->withoutMiddleware(HotreloadMiddleware::class);

        $this->actingAs(User::factory()->create());

        $this->get(route('settings.profile.delete'))
            ->assertOk()
            ->assertViewIs('settings.profile.delete')
            ->assertViewHasForm('id="delete-profile-form"', 'POST', route('settings.profile.destroy'))
            ->assertFormHasCSRF()
            ->assertFormHasPasswordInput('password')
            ->assertFormHasSubmitButton();
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

    #[Test]
    public function profile_page_is_displayed(): void
    {
        $this->withoutMiddleware(HotreloadMiddleware::class);

        $this->actingAs(User::factory()->create());

        $this->get(route('settings.profile.edit'))
            ->assertOk()
            ->assertViewIs('settings.profile.edit')
            ->assertViewHasForm('id="edit-profile-form"', 'PUT', route('settings.profile.update'))
            ->assertFormHasCSRF()
            ->assertFormHasTextInput('name')
            ->assertFormHasEmailInput('email')
            ->assertFormHasSubmitButton();
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
