<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Fortify;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ResetUserPassword::class)]
final class ResetUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    public ResetUserPassword $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new ResetUserPassword;
    }

    #[Test]
    public function resets_password_with_valid_input(): void
    {
        $user = User::factory()->create([
            'username' => 'johndoe',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->action->reset($user, [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    #[Test]
    public function throws_validation_error_when_password_is_missing(): void
    {
        $user = User::factory()->create([
            'username' => 'johndoe',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->expectException(ValidationException::class);

        $this->action->reset($user, []);
    }

    #[Test]
    public function throws_validation_error_when_password_is_too_short(): void
    {
        $user = User::factory()->create([
            'username' => 'johndoe',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->expectException(ValidationException::class);

        $this->action->reset($user, [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
    }

    #[Test]
    public function throws_validation_error_when_password_confirmation_does_not_match(): void
    {
        $user = User::factory()->create([
            'username' => 'johndoe',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->expectException(ValidationException::class);

        $this->action->reset($user, [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);
    }

    #[Test]
    public function resets_password_without_password_confirmation_field(): void
    {
        $user = User::factory()->create([
            'username' => 'johndoe',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->expectException(ValidationException::class);

        $this->action->reset($user, [
            'password' => 'NewPassword123!',
        ]);
    }
}
