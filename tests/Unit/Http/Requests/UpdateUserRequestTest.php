<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UpdateUserRequest::class)]
final class UpdateUserRequestTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    #[Test]
    public function name_is_required(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['name' => ''])
            ->assertFails(['name' => 'required']);
    }

    #[Test]
    public function name_must_be_string(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['name' => 123])
            ->assertFails(['name' => 'string']);
    }

    #[Test]
    public function name_must_not_exceed_255_characters(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['name' => str_repeat('a', 256)])
            ->assertFails(['name' => 'max']);
    }

    #[Test]
    public function username_is_required(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['username' => ''])
            ->assertFails(['username' => 'required']);
    }

    #[Test]
    public function username_must_be_string(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['username' => 123])
            ->assertFails(['username' => 'string']);
    }

    #[Test]
    public function username_must_not_exceed_20_characters(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['username' => str_repeat('a', 21)])
            ->assertFails(['username' => 'max']);
    }

    #[Test]
    public function username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['username' => 'taken'])
            ->assertFails(['username' => 'unique']);
    }

    #[Test]
    public function email_is_required(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['email' => ''])
            ->assertFails(['email' => 'required']);
    }

    #[Test]
    public function email_must_be_valid(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['email' => 'not-an-email'])
            ->assertFails(['email' => 'email']);
    }

    #[Test]
    public function email_must_not_exceed_255_characters(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['email' => str_repeat('a', 247).'@test.com'])
            ->assertFails(['email' => 'max']);
    }

    #[Test]
    public function email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->createFormRequest(UpdateUserRequest::class)
            ->validate(['email' => 'taken@example.com'])
            ->assertFails(['email' => 'unique']);
    }

    #[Test]
    public function valid_data_passes(): void
    {
        $this->createFormRequest(UpdateUserRequest::class)
            ->validate([
                'name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
            ])
            ->assertPasses();
    }
}
