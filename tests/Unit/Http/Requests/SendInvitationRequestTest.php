<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\SendInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SendInvitationRequest::class)]
class SendInvitationRequestTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    #[Test]
    public function email_is_required(): void
    {
        $this->createFormRequest(SendInvitationRequest::class)
            ->validate(['email' => ''])
            ->assertFails(['email' => 'required']);
    }

    #[Test]
    public function email_must_be_valid(): void
    {
        $this->createFormRequest(SendInvitationRequest::class)
            ->validate(['email' => 'not-an-email'])
            ->assertFails(['email' => 'email']);
    }

    #[Test]
    public function email_must_not_exceed_255_characters(): void
    {
        $this->createFormRequest(SendInvitationRequest::class)
            ->validate(['email' => str_repeat('a', 247).'@test.com'])
            ->assertFails(['email' => 'max']);
    }

    #[Test]
    public function email_must_be_unique_among_pending_invitations(): void
    {
        Invitation::factory()->create(['email' => 'taken@example.com']);

        $this->createFormRequest(SendInvitationRequest::class)
            ->validate(['email' => 'taken@example.com'])
            ->assertFails(['email' => 'unique']);
    }

    #[Test]
    public function accepted_invitation_email_can_be_reinvited(): void
    {
        Invitation::factory()->accepted()->create(['email' => 'accepted@example.com']);

        $this->createFormRequest(SendInvitationRequest::class)
            ->validate(['email' => 'accepted@example.com'])
            ->assertPasses();
    }

    #[Test]
    public function email_must_be_unique_among_existing_users(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->createFormRequest(SendInvitationRequest::class)
            ->validate(['email' => 'user@example.com'])
            ->assertFails(['email' => 'unique']);
    }

    #[Test]
    public function email_passes_validation_with_valid_unused_address(): void
    {
        $this->createFormRequest(SendInvitationRequest::class)
            ->validate(['email' => 'new@example.com'])
            ->assertPasses();
    }
}
