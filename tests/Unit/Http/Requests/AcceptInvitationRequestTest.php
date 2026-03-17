<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AcceptInvitationRequest::class)]
class AcceptInvitationRequestTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    #[Test]
    public function name_is_required(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['name' => ''])
            ->assertFails(['name' => 'required']);
    }

    #[Test]
    public function username_is_required(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['username' => ''])
            ->assertFails(['username' => 'required']);
    }

    #[Test]
    public function username_must_not_exceed_20_characters(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['username' => str_repeat('a', 21)])
            ->assertFails(['username' => 'max']);
    }

    #[Test]
    public function username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'takenuser']);

        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['username' => 'takenuser'])
            ->assertFails(['username' => 'unique']);
    }

    #[Test]
    public function password_is_required(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['password' => ''])
            ->assertFails(['password' => 'required']);
    }

    #[Test]
    public function password_must_be_confirmed(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['password' => 'Secret123!', 'password_confirmation' => 'Different123!'])
            ->assertFails(['password' => 'confirmed']);
    }
}
