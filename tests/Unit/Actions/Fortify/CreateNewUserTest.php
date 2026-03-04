<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Fortify;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Services\TenantDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreateNewUser::class)]
final class CreateNewUserTest extends TestCase
{
    use RefreshDatabase;

    public CreateNewUser $action;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantDb = app(TenantDatabaseService::class);
        $this->action = new CreateNewUser($tenantDb);
    }

    #[Test]
    public function creates_new_user_with_valid_input(): void
    {
        $input = [
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        $user = $this->action->create($input);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('johndoe', $user->username);
        $this->assertSame('john@example.com', $user->email);
        $this->assertNotNull($user->password);
    }

    #[Test]
    public function throws_validation_error_when_name_is_missing(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_name_exceeds_max_length(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => str_repeat('a', 256),
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_username_is_missing(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_username_exceeds_max_length(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'username' => str_repeat('a', 21),
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_username_is_not_unique(): void
    {
        User::factory()->create([
            'username' => 'johndoe',
            'email' => 'existing@example.com',
        ]);

        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'different@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_email_is_missing(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_email_is_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'invalid-email',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_email_exceeds_max_length(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => str_repeat('a', 250).'@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_email_is_not_unique(): void
    {
        User::factory()->create([
            'username' => 'existinguser',
            'email' => 'john@example.com',
        ]);

        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'username' => 'differentuser',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_password_is_missing(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_password_is_too_short(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_password_confirmation_does_not_match(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'DifferentPass123!',
        ]);
    }
}
