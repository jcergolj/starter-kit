<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.single_user_mode', true);
    }

    #[Test]
    public function it_creates_admin_user(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Name', 'Admin User')
            ->expectsQuestion('Username', 'admin')
            ->expectsQuestion('Email', 'admin@example.com')
            ->expectsQuestion('Password', 'password')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertSame('Admin User', $user->name);
        $this->assertSame('admin', $user->username);
        $this->assertSame('admin@example.com', $user->email);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    #[Test]
    public function it_fails_when_not_in_single_user_mode(): void
    {
        config()->set('app.single_user_mode', false);

        $this->artisan('app:create-admin')
            ->assertFailed();
    }

    #[Test]
    public function it_fails_when_user_already_exists(): void
    {
        User::factory()->create();

        $this->artisan('app:create-admin')
            ->assertFailed();
    }
}
