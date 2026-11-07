<?php

declare(strict_types=1);

namespace Tests\Unit\Features\Settings\Requests;

use App\Features\Settings\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[CoversClass(UpdateProfileRequest::class)]
class UpdateProfileRequestTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($this->user);
    }

    #[Test]
    #[TestWith([null, 'required'])]
    #[TestWith(['', 'required'])]
    #[TestWith(['wrong-password', 'currentpassword'])]
    #[TestWith([['password'], 'string'])]
    public function changing_email_rejects_an_invalid_password(mixed $password, string $rule): void
    {
        $this->createFormRequest(UpdateProfileRequest::class)
            ->by($this->user)
            ->validate([
                'name' => 'Test User',
                'email' => 'new@example.com',
                'current_password' => $password,
            ])
            ->assertFails(['current_password' => $rule]);
    }

    #[Test]
    public function changing_email_accepts_the_current_password(): void
    {
        $this->createFormRequest(UpdateProfileRequest::class)
            ->by($this->user)
            ->validate([
                'name' => 'Test User',
                'email' => 'new@example.com',
                'current_password' => 'password',
            ])
            ->assertPasses();
    }

    #[Test]
    public function keeping_the_same_email_does_not_require_a_password(): void
    {
        $this->createFormRequest(UpdateProfileRequest::class)
            ->by($this->user)
            ->validate([
                'name' => 'Test User',
                'email' => '  Old@Example.COM ',
            ])
            ->assertPasses();
    }
}
