<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Http\Controllers\InvitationController;
use App\Http\Requests\SendInvitationRequest;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(InvitationController::class)]
final class InvitationControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    protected function setUp(): void
    {
        parent::setUp();

        InAppNotification::fake();
    }

    #[Test]
    public function create_has_auth_verified_and_admin_middleware(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('invitations.create'));

        $response->assertMiddlewareIsApplied('auth');
        $response->assertMiddlewareIsApplied('verified');
        $response->assertMiddlewareIsApplied('admin');
    }

    #[Test]
    public function store_has_form_request(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('invitations.store'));

        $this->assertContainsFormRequest(SendInvitationRequest::class);
    }

    #[Test]
    public function admin_can_view_invite_form(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('invitations.create'));

        $response->assertOk();
    }

    #[Test]
    public function non_admin_gets_403(): void
    {
        $nonAdmin = User::factory()->create();

        $response = $this->actingAs($nonAdmin)->get(route('invitations.create'));

        $response->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('invitations.create'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_can_send_invite_email(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => 'invite@example.com',
        ]);

        $response->assertRedirect(route('invitations.create'));

        InAppNotification::assertSuccess(__('Invitation sent successfully.'));

        $this->assertDatabaseHas('invitations', ['email' => 'invite@example.com']);
        Mail::assertSent(InvitationMail::class, fn ($mail) => $mail->hasTo('invite@example.com'));
    }

    #[Test]
    public function duplicate_pending_email_fails_validation(): void
    {
        $admin = User::factory()->admin()->create();
        Invitation::factory()->create(['email' => 'pending@example.com']);

        $response = $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => 'pending@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function admin_can_revoke_pending_invitation(): void
    {
        $admin = User::factory()->admin()->create();
        $invitation = Invitation::factory()->create();

        $response = $this->actingAs($admin)->delete(route('invitations.destroy', $invitation));

        $response->assertRedirect(route('invitations.create'));

        InAppNotification::assertSuccess(__('Invitation revoked.'));
    }

    #[Test]
    public function revoked_invitation_is_deleted_from_db(): void
    {
        $admin = User::factory()->admin()->create();
        $invitation = Invitation::factory()->create();

        $this->actingAs($admin)->delete(route('invitations.destroy', $invitation));

        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
    }
}
