<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Controllers\AcceptInvitationController;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use HotwiredLaravel\Hotreload\Http\Middleware\HotreloadMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AcceptInvitationController::class)]
class AcceptInvitationControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    #[Test]
    public function store_has_form_request(): void
    {
        $invitation = Invitation::factory()->create();
        $this->post(route('accept.invitations.store', $invitation->token));

        $this->assertContainsFormRequest(AcceptInvitationRequest::class);
    }

    #[Test]
    public function show_renders_accept_form_for_valid_token(): void
    {
        $this->withoutMiddleware(HotreloadMiddleware::class);

        $invitation = Invitation::factory()->create();

        $response = $this->get(route('invitations.accept', $invitation->token));

        $response->assertOk()
            ->assertViewIs('invitations.accept')
            ->assertViewHasForm('id="accept-invitation-form"', 'POST', route('accept.invitations.store', $invitation->token))
            ->assertFormHasCSRF()
            ->assertFormHasEmailInput('email')
            ->assertFormHasTextInput('name')
            ->assertFormHasTextInput('username')
            ->assertFormHasPasswordInput('password')
            ->assertFormHasPasswordInput('password_confirmation')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function show_returns_404_for_unknown_token(): void
    {
        $response = $this->get(route('invitations.accept', 'unknowntoken'));

        $response->assertNotFound();
    }

    #[Test]
    public function show_redirects_when_expired(): void
    {
        $invitation = Invitation::factory()->expired()->create();

        $response = $this->get(route('invitations.accept', $invitation->token));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', __('This invitation is no longer valid.'));
    }

    #[Test]
    public function show_redirects_when_already_accepted(): void
    {
        $invitation = Invitation::factory()->accepted()->create();

        $response = $this->get(route('invitations.accept', $invitation->token));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', __('This invitation is no longer valid.'));
    }

    #[Test]
    public function store_creates_user_with_correct_fields(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'invited@example.com']);

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'email' => 'invited@example.com',
        ]);
    }

    #[Test]
    public function store_creates_admin_user_when_invitation_is_admin(): void
    {
        $invitation = Invitation::factory()->create(['role' => RoleEnum::Admin]);

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Admin User',
            'username' => 'adminuser',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', $invitation->email)->first();
        $this->assertSame(RoleEnum::Admin, $user->role);
    }

    #[Test]
    public function store_creates_regular_user_when_invitation_is_not_admin(): void
    {
        $invitation = Invitation::factory()->create(['role' => RoleEnum::User]);

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Regular User',
            'username' => 'regularuser',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', $invitation->email)->first();
        $this->assertSame(RoleEnum::User, $user->role);
    }

    #[Test]
    public function store_sets_email_verified_at(): void
    {
        $invitation = Invitation::factory()->create();

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', $invitation->email)->first();
        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function store_marks_invitation_accepted(): void
    {
        $invitation = Invitation::factory()->create();

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    #[Test]
    public function store_email_comes_from_db_not_request(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'real@example.com']);

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'real@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'spoofed@example.com']);
    }

    #[Test]
    public function store_redirects_to_login_with_accepted_status(): void
    {
        $invitation = Invitation::factory()->create();

        $response = $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', __('Invitation accepted. You can now log in.'));
    }

    #[Test]
    public function store_fails_on_expired_token(): void
    {
        $invitation = Invitation::factory()->expired()->create();

        $response = $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', __('This invitation is no longer valid.'));
        $this->assertDatabaseEmpty('users');
    }

    #[Test]
    public function store_fails_when_already_accepted(): void
    {
        $invitation = Invitation::factory()->accepted()->create();

        $response = $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', __('This invitation is no longer valid.'));
        $this->assertDatabaseEmpty('users');
    }
}
