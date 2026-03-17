<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Invitation::class)]
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_for_generates_token_and_sets_expiry(): void
    {
        $invitation = Invitation::createFor('test@example.com');

        $this->assertSame('test@example.com', $invitation->email);
        $this->assertNotEmpty($invitation->token);
        $this->assertSame(64, strlen($invitation->token));
        $this->assertNull($invitation->accepted_at);
        $this->assertTrue($invitation->expires_at->isFuture());
    }

    #[Test]
    public function is_pending_returns_true_for_valid_pending_invitation(): void
    {
        $invitation = Invitation::factory()->create();

        $this->assertTrue($invitation->isPending());
    }

    #[Test]
    public function is_pending_returns_false_when_accepted(): void
    {
        $invitation = Invitation::factory()->accepted()->create();

        $this->assertFalse($invitation->isPending());
    }

    #[Test]
    public function is_pending_returns_false_when_expired(): void
    {
        $invitation = Invitation::factory()->expired()->create();

        $this->assertFalse($invitation->isPending());
    }

    #[Test]
    public function accept_sets_accepted_at(): void
    {
        $invitation = Invitation::factory()->create();

        $invitation->accept();

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    #[Test]
    public function pending_scope_excludes_accepted(): void
    {
        Invitation::factory()->accepted()->create();

        $this->assertSame(0, Invitation::pending()->count());
    }

    #[Test]
    public function pending_scope_excludes_expired(): void
    {
        Invitation::factory()->expired()->create();

        $this->assertSame(0, Invitation::pending()->count());
    }

    #[Test]
    public function pending_scope_includes_valid_pending(): void
    {
        Invitation::factory()->create();

        $this->assertSame(1, Invitation::pending()->count());
    }
}
