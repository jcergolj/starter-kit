<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\DataTransferObjects\UserSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function exists_returns_false_when_no_users(): void
    {
        $this->assertFalse(User::exists());
    }

    #[Test]
    public function exists_returns_true_when_user_exists(): void
    {
        User::factory()->create();

        $this->assertTrue(User::exists());
    }

    #[Test]
    public function settings_returns_user_settings_dto(): void
    {
        $user = User::factory()->create([
            'settings' => ['lang' => 'sl'],
        ]);

        $this->assertInstanceOf(UserSettings::class, $user->settings);
        $this->assertSame('sl', $user->settings->lang);
    }

    #[Test]
    public function settings_defaults_to_english_when_null(): void
    {
        $user = User::factory()->create([
            'settings' => null,
        ]);

        $this->assertInstanceOf(UserSettings::class, $user->settings);
        $this->assertSame('en', $user->settings->lang);
    }
}
