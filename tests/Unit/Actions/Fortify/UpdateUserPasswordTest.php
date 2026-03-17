<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Fortify;

use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UpdateUserPassword::class)]
class UpdateUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    public UpdateUserPassword $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateUserPassword;
    }

    #[Test]
    public function updates_password_with_valid_input(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user);

        $this->action->update($user, [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    #[Test]
    public function throws_validation_error_when_current_password_is_missing(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        $this->action->update($user, [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_current_password_is_incorrect(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user);

        try {
            $this->action->update($user, [
                'current_password' => 'WrongPassword123!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('current_password', $e->errors());
            $this->assertStringContainsString('current password', strtolower((string) $e->errors()['current_password'][0]));
        }
    }

    #[Test]
    public function throws_validation_error_when_password_is_missing(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        $this->action->update($user, [
            'current_password' => 'OldPassword123!',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_password_is_too_short(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        $this->action->update($user, [
            'current_password' => 'OldPassword123!',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_password_confirmation_does_not_match(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        $this->action->update($user, [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);
    }
}
