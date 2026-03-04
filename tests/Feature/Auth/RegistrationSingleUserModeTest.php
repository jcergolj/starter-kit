<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrationSingleUserModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.single_user_mode', true);
    }

    #[Test]
    public function registration_redirects_to_same_domain_login(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertValid();

        $response->assertRedirect('/login?status=verify-email');
    }

    #[Test]
    public function user_is_created_in_default_database(): void
    {
        $this->post(route('register'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertValid();

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'username' => 'testuser',
        ]);
    }

    #[Test]
    public function subdomain_preview_is_hidden_on_register_page(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertDontSee('Your URL will be:');
    }

    #[Test]
    public function registration_page_redirects_to_login_when_user_exists(): void
    {
        User::factory()->create();

        $this->get('/register')->assertRedirect(route('login'));
    }

    #[Test]
    public function registration_post_redirects_to_login_when_user_exists(): void
    {
        User::factory()->create();

        $this->post(route('register'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('login'));
    }
}
