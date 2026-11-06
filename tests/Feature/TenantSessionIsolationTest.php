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
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
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
            'database.connections.sqlite.database' => ($this->databaseRoot = sys_get_temp_dir().
                '/starter-kit-session-tests-'.
                bin2hex(random_bytes(8))).
                '/application.sqlite',
            'database.connections.tenant.database' => $this->databaseRoot.'/application-tenant.sqlite',
            'session.driver' => 'file',
            'session.files' => $this->sessionRoot = sys_get_temp_dir().
                '/starter-kit-session-tests-'.
                bin2hex(random_bytes(8)),
        ]);

        mkdir($this->databaseRoot, 0755, true);
        mkdir($this->sessionRoot, 0755, true);

        touch($this->databaseRoot.'/application.sqlite');
        $this->createUsersTable('sqlite');

        DB::connection('sqlite')->table('users')->insert([
            'id' => 1,
            'username' => 'root-admin',
            'name' => 'Root administrator',
            'email' => 'root@example.com',
            'role' => RoleEnum::Admin->value,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'two_factor_recovery_codes' => encrypt(json_encode(['valid-recovery-code'])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            ->assertForbidden();
    }

    #[Test]
    public function a_root_session_is_rejected_by_a_tenant_with_the_same_user_id(): void
    {
        $this->createTenantDatabase('tenant-a', 'tenant-a@example.com');

        $response = $this->post('http://example.com/login', [
            'email' => 'root@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $sessionCookie = $response->getCookie(config('session.cookie'));

        $this->assertInstanceOf(Cookie::class, $sessionCookie);

        $this->app['auth']->forgetGuards();

        $this->withCookie(config('session.cookie'), $sessionCookie->getValue())
            ->get('http://tenant-a.example.com/dashboard')
            ->assertForbidden();
    }

    #[Test]
    public function a_persisted_root_session_can_be_reused_on_the_root_host(): void
    {
        $response = $this->post('http://example.com/login', [
            'email' => 'root@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $sessionCookie = $response->getCookie(config('session.cookie'));

        $this->assertInstanceOf(Cookie::class, $sessionCookie);

        $this->app['auth']->forgetGuards();

        $this->withCookie(config('session.cookie'), $sessionCookie->getValue())
            ->get('http://example.com/dashboard')
            ->assertOk();
    }

    #[Test]
    public function an_old_authenticated_session_without_a_host_binding_is_rejected(): void
    {
        $this->createTenantDatabase('tenant-a', 'tenant-a@example.com');

        $response = $this->post('http://tenant-a.example.com/login', [
            'email' => 'tenant-a@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $sessionCookie = $response->getCookie(config('session.cookie'));

        $this->assertInstanceOf(Cookie::class, $sessionCookie);

        $this->app['session.store']->forget('tenant_host');
        $this->app['session.store']->save();
        $this->app['auth']->forgetGuards();

        $this->withCookie(config('session.cookie'), $sessionCookie->getValue())
            ->get('http://example.com/dashboard')
            ->assertForbidden();
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

    #[Test]
    #[TestWith(['tenant-a.example.com', 'tenant-b.example.com'])]
    #[TestWith(['example.com', 'tenant-a.example.com'])]
    #[TestWith(['tenant-a.example.com', 'example.com'])]
    public function database_hosts_with_matching_user_ids_have_separate_two_factor_limits(
        string $firstHost,
        string $secondHost,
    ): void {
        $this->freezeTime();
        $this->createTenantDatabase('tenant-a', 'tenant-a@example.com');
        $this->createTenantDatabase('tenant-b', 'tenant-b@example.com');

        foreach (range(1, 5) as $_) {
            $this->submitRecoveryCode($firstHost)->assertUnprocessable()->assertInvalid('recovery_code');
        }

        $this->submitRecoveryCode($firstHost)->assertTooManyRequests();

        $this->submitRecoveryCode($secondHost)->assertUnprocessable()->assertInvalid('recovery_code');

        $this->submitRecoveryCode($secondHost, 'valid-recovery-code')->assertNoContent();

        $this->submitRecoveryCode($firstHost)->assertTooManyRequests();
    }

    private function submitRecoveryCode(string $host, string $code = 'invalid-recovery-code'): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->app['session.store']->flush();

        return $this->withSession([
            'tenant_host' => $host,
            'login.id' => 1,
            'login.remember' => false,
        ])->postJson("http://{$host}/two-factor-challenge", ['recovery_code' => $code]);
    }

    #[Test]
    public function single_database_hosts_share_the_same_account_limit(): void
    {
        config(['app.single_db_per_app' => true]);
        $this->freezeTime();

        foreach (range(1, 5) as $_) {
            $this->submitRecoveryCode('example.com')->assertUnprocessable()->assertInvalid('recovery_code');
        }

        $this->submitRecoveryCode('tenant-a.example.com')->assertTooManyRequests();
    }

    private function createTenantDatabase(string $subdomain, string $email): void
    {
        $path = $this->databaseRoot."/{$subdomain}.sqlite";

        touch($path);
        Config::set('database.connections.tenant.database', $path);
        DB::purge('tenant');

        $this->createUsersTable('tenant');

        DB::connection('tenant')->table('users')->insert([
            'id' => 1,
            'username' => $subdomain,
            'name' => ucfirst($subdomain),
            'email' => $email,
            'role' => RoleEnum::User->value,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'two_factor_recovery_codes' => encrypt(json_encode(['valid-recovery-code'])),
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
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
