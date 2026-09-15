<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Controllers\UserController;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UserController::class)]
class UserControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    protected function setUp(): void
    {
        parent::setUp();

        InAppNotification::fake();
        Notification::fake();
    }

    #[Test]
    public function index_has_auth_verified_and_admin_middleware(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertMiddlewareIsApplied('auth');

        $response->assertMiddlewareIsApplied('verified');

        $response->assertMiddlewareIsApplied('admin');
    }

    #[Test]
    public function edit_has_auth_verified_and_admin_middleware(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('users.edit', $user));

        $response->assertMiddlewareIsApplied('auth');

        $response->assertMiddlewareIsApplied('verified');

        $response->assertMiddlewareIsApplied('admin');
    }

    #[Test]
    public function update_has_auth_verified_and_admin_middleware(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Updated',
            'username' => 'updated',
            'email' => 'updated@example.com',
        ]);

        $response->assertMiddlewareIsApplied('auth');

        $response->assertMiddlewareIsApplied('verified');

        $response->assertMiddlewareIsApplied('admin');
    }

    #[Test]
    public function update_has_form_request(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('users.update', $user));

        $this->assertContainsFormRequest(UpdateUserRequest::class);
    }

    #[Test]
    public function destroy_has_auth_verified_and_admin_middleware(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

        $response->assertMiddlewareIsApplied('auth');

        $response->assertMiddlewareIsApplied('verified');

        $response->assertMiddlewareIsApplied('admin');
    }

    #[Test]
    public function admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk();

        $response->assertSeeText($user->username);
    }

    #[Test]
    public function admin_is_excluded_from_user_list(): void
    {
        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk();

        $response->assertSeeText($otherUser->username);

        $this->assertDatabaseCount('users', 2);

        $this->assertCount(1, User::where('role', RoleEnum::User)->get());
    }

    #[Test]
    public function admin_user_list_is_paginated_in_stable_order(): void
    {
        $admin = User::factory()->admin()->create();
        $users = User::factory()->count(16)->create();

        $firstPage = $this->actingAs($admin)->get(route('users.index'));
        $secondPage = $this->actingAs($admin)->get(route('users.index', ['page' => 2]));

        $firstPage->assertOk()
            ->assertSeeText($users[0]->username)
            ->assertDontSeeText($users[15]->username)
            ->assertSee('page=2');

        $secondPage->assertOk()
            ->assertDontSeeText($users[0]->username)
            ->assertSeeText($users[15]->username);
    }

    #[Test]
    public function empty_user_list_renders_without_pagination_links(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk()
            ->assertSeeText(__('No users found.'))
            ->assertDontSee('page=');
    }

    #[Test]
    public function admin_can_view_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('users.edit', $user));

        $response->assertOk()
            ->assertViewIs('users.edit')
            ->assertViewHasForm('id="edit-user-form"', 'PUT', route('users.update', $user))
            ->assertFormHasCSRF()
            ->assertFormHasTextInput('name')
            ->assertFormHasTextInput('username')
            ->assertFormHasEmailInput('email')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function admin_can_update_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Updated Name',
            'username' => 'updateduser',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect(route('users.index'));

        InAppNotification::assertSuccess(__('User updated.'));

        $updatedUser = User::find($user->id);
        $this->assertSame('Updated Name', $updatedUser->name);

        $this->assertSame('updateduser', $updatedUser->username);

        $this->assertSame('updated@example.com', $updatedUser->email);
    }

    #[Test]
    public function admin_user_email_is_normalized(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => '  Updated@Example.COM ',
        ]);

        $this->assertSame('updated@example.com', $user->fresh()->email);
    }

    #[Test]
    public function admin_email_change_resets_verification_and_sends_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh()->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function admin_unchanged_email_preserves_verification_and_sends_no_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Updated Name',
            'username' => $user->username,
            'email' => $user->email,
        ]);

        $this->assertNotNull($user->fresh()->email_verified_at);

        Notification::assertNothingSent();
    }

    #[Test]
    public function admin_can_update_user_with_same_username_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Updated Name',
            'username' => $user->username,
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('users.index'));

        InAppNotification::assertSuccess(__('User updated.'));
    }

    #[Test]
    public function admin_can_delete_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

        $response->assertRedirect(route('users.index'));

        InAppNotification::assertSuccess(__('User deleted.'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function admin_cannot_edit_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('users.edit', $admin));

        $response->assertForbidden();
    }

    #[Test]
    public function admin_cannot_update_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => 'New Name',
            'username' => 'newname',
            'email' => 'new@example.com',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function admin_cannot_update_another_administrator(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $otherAdmin), [
            'name' => 'New Name',
            'username' => 'newname',
            'email' => 'new@example.com',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

        $response->assertForbidden();
    }

    #[Test]
    public function superadmin_can_update_a_regular_user(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($superadmin)->put(route('users.update', $user), [
            'name' => 'Updated Name',
            'username' => 'updateduser',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertSame('Updated Name', $user->fresh()->name);
    }

    #[Test]
    public function superadmin_cannot_update_an_administrator(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($superadmin)->put(route('users.update', $admin), [
            'name' => 'New Name',
            'username' => 'newname',
            'email' => 'new@example.com',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function non_admin_gets_403(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('users.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertRedirect(route('login'));
    }
}
