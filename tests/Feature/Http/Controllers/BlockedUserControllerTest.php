<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Http\Controllers\BlockedUserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BlockedUserController::class)]
class BlockedUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        InAppNotification::fake();
    }

    #[Test]
    public function store_has_auth_verified_and_admin_middleware(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('blocked-users.store', $user));

        $response->assertMiddlewareIsApplied('auth');
        $response->assertMiddlewareIsApplied('verified');
        $response->assertMiddlewareIsApplied('admin');
    }

    #[Test]
    public function destroy_has_auth_verified_and_admin_middleware(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->blocked()->create();

        $response = $this->actingAs($admin)->delete(route('blocked-users.destroy', $user));

        $response->assertMiddlewareIsApplied('auth');
        $response->assertMiddlewareIsApplied('verified');
        $response->assertMiddlewareIsApplied('admin');
    }

    #[Test]
    public function admin_can_block_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('blocked-users.store', $user));

        $response->assertRedirect(route('users.index'));
        InAppNotification::assertSuccess(__('User blocked.'));
        $this->assertNotNull($user->fresh()->blocked_at);
    }

    #[Test]
    public function admin_can_unblock_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->blocked()->create();

        $response = $this->actingAs($admin)->delete(route('blocked-users.destroy', $user));

        $response->assertRedirect(route('users.index'));
        InAppNotification::assertSuccess(__('User unblocked.'));
        $this->assertNull($user->fresh()->blocked_at);
    }

    #[Test]
    public function admin_cannot_block_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('blocked-users.store', $admin));

        $response->assertForbidden();
    }

    #[Test]
    public function admin_cannot_unblock_themselves(): void
    {
        $admin = User::factory()->admin()->blocked()->create();

        $response = $this->actingAs($admin)->delete(route('blocked-users.destroy', $admin));

        $response->assertForbidden();
    }

    #[Test]
    public function non_admin_gets_403(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->post(route('blocked-users.store', $other));

        $response->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('blocked-users.store', $user));

        $response->assertRedirect(route('login'));
    }
}
