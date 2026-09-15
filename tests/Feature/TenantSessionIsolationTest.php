<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Services\TenantDatabaseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class TenantSessionIsolationTest extends TestCase
{
    private string $databaseRoot;

    private string $sessionRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'app.domain' => 'example.com',
            'app.single_db_per_app' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ($this->databaseRoot = sys_get_temp_dir().'/starter-kit-session-tests-'.bin2hex(random_bytes(8))).'/application.sqlite',
            'database.connections.tenant.database' => $this->databaseRoot.'/application-tenant.sqlite',
            'session.driver' => 'file',
            'session.files' => $this->sessionRoot = sys_get_temp_dir().'/starter-kit-session-tests-'.bin2hex(random_bytes(8)),
        ]);

        mkdir($this->databaseRoot, 0755, true);
        mkdir($this->sessionRoot, 0755, true);

        touch($this->databaseRoot.'/application.sqlite');
        $this->createUsersTable('sqlite');

        $this->app->instance(TenantDatabaseService::class, new TenantDatabaseService($this->databaseRoot));
    }

    protected function tearDown(): void
    {
        foreach ([$this->databaseRoot, $this->sessionRoot] as $directory) {
            foreach (glob($directory.'/*') ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            rmdir($directory);
        }

        parent::tearDown();
    }

    #[Test]
    public function a_persisted_session_can_be_reused_on_the_authenticated_tenant_host(): void
    {
        $this->createTenantDatabase('tenant-a', 'tenant-a@example.com');

        $response = $this->post('http://tenant-a.example.com/login', [
            'email' => 'tenant-a@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $sessionCookie = $response->getCookie(config('session.cookie'));

        $this->assertInstanceOf(Cookie::class, $sessionCookie);

        $this->app['auth']->forgetGuards();

        $this->withCookie(config('session.cookie'), $sessionCookie->getValue())
            ->get('http://tenant-a.example.com/dashboard')
            ->assertOk();
    }

    #[Test]
    public function the_same_session_cookie_is_rejected_by_another_tenant_with_the_same_user_id(): void
    {
        $this->createTenantDatabase('tenant-a', 'tenant-a@example.com');
        $this->createTenantDatabase('tenant-b', 'tenant-b@example.com');

        $response = $this->post('http://tenant-a.example.com/login', [
            'email' => 'tenant-a@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $sessionCookie = $response->getCookie(config('session.cookie'));

        $this->assertInstanceOf(Cookie::class, $sessionCookie);

        $this->app['auth']->forgetGuards();

        $this->withCookie(config('session.cookie'), $sessionCookie->getValue())
            ->get('http://tenant-b.example.com/dashboard')
            ->assertForbidden();
    }

    #[Test]
    public function a_tenant_session_is_not_authenticated_on_the_root_domain(): void
    {
        $this->createTenantDatabase('tenant-a', 'tenant-a@example.com');

        $response = $this->post('http://tenant-a.example.com/login', [
            'email' => 'tenant-a@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $sessionCookie = $response->getCookie(config('session.cookie'));

        $this->assertInstanceOf(Cookie::class, $sessionCookie);

        $this->app['auth']->forgetGuards();

        $this->withCookie(config('session.cookie'), $sessionCookie->getValue())
            ->get('http://example.com/dashboard')
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function remember_me_session_is_also_rejected_by_another_tenant(): void
    {
        $this->createTenantDatabase('tenant-a', 'tenant-a@example.com');
        $this->createTenantDatabase('tenant-b', 'tenant-b@example.com');

        $response = $this->post('http://tenant-a.example.com/login', [
            'email' => 'tenant-a@example.com',
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertRedirect(route('dashboard'));

        $sessionCookie = $response->getCookie(config('session.cookie'));
        $rememberCookie = $response->getCookie(app('auth')->guard()->getRecallerName());

        $this->assertInstanceOf(Cookie::class, $sessionCookie);

        $this->assertInstanceOf(Cookie::class, $rememberCookie);

        $this->app['auth']->forgetGuards();

        $this->withCookie(config('session.cookie'), $sessionCookie->getValue())
            ->withCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->get('http://tenant-b.example.com/dashboard')
            ->assertForbidden();
    }

    private function createTenantDatabase(string $subdomain, string $email): void
    {
        $path = $this->databaseRoot."/{$subdomain}.sqlite";

        touch($path);
        Config::set('database.connections.tenant.database', $path);
        DB::purge('tenant');

        $this->createUsersTable('tenant');

        DB::connection('tenant')->table('users')->insert([
            'username' => $subdomain,
            'name' => ucfirst($subdomain),
            'email' => $email,
            'role' => RoleEnum::User->value,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Config::set('database.connections.tenant.database', $this->databaseRoot.'/application-tenant.sqlite');
        DB::purge('tenant');
    }

    private function createUsersTable(string $connection): void
    {
        Schema::connection($connection)->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default(RoleEnum::User->value);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamp('blocked_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
