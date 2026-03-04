<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Settings;

use App\Http\Controllers\Settings\PasswordController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PasswordController::class)]
final class PasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function edit_requires_authentication(): void
    {
        $response = $this->get(route('settings.password.edit'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function update_updates_user_password(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.password.edit'))
            ->patch(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    #[Test]
    public function update_requires_authentication(): void
    {
        $response = $this->patch(route('settings.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function update_validates_current_password(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.password.edit'))
            ->patch(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect(route('settings.password.edit'))
            ->assertSessionHasErrors(['current_password']);
    }

    #[Test]
    public function update_validates_password_confirmation(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.password.edit'))
            ->patch(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertRedirect(route('settings.password.edit'))
            ->assertSessionHasErrors(['password']);
    }

    #[Test]
    public function update_validates_password_strength(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.password.edit'))
            ->patch(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertRedirect(route('settings.password.edit'))
            ->assertSessionHasErrors(['password']);
    }
}
