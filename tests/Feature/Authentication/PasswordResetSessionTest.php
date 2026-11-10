<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication;

use App\Models\User;
use App\Services\TenantDatabaseService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class PasswordResetSessionTest extends TestCase
{
    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = sys_get_temp_dir().'/starter-kit-reset-sessions-'.bin2hex(random_bytes(8));

        File::makeDirectory($this->storageRoot.'/sessions', 0755, true);

        touch($this->storageRoot.'/main.sqlite');

        touch($this->storageRoot.'/acme.sqlite');

        Config::set([
            'app.domain' => 'example.com',
            'app.single_db_per_app' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => $this->storageRoot.'/main.sqlite',
            'database.connections.tenant.url' => null,
            'database.connections.tenant.database' => $this->storageRoot.'/acme.sqlite',
            'session.files' => $this->storageRoot.'/sessions',
            'session.connection' => 'sqlite',
        ]);

        DB::purge('sqlite');

        DB::purge('tenant');

        $this->app->instance(TenantDatabaseService::class, new TenantDatabaseService($this->storageRoot));

        foreach (['sqlite', 'tenant'] as $connection) {
            $this->artisan('migrate', ['--database' => $connection, '--no-interaction' => true])
                ->assertSuccessful();

            User::factory()->connection($connection)->create([
                'username' => 'acme',
                'email' => 'owner@example.com',
            ]);
        }
    }

    protected function tearDown(): void
    {
        try {
            DB::purge('sqlite');

            DB::purge('tenant');

            File::deleteDirectory($this->storageRoot);
        } finally {
            parent::tearDown();
        }
    }

    /** @return \Iterator<string, array{string, string, string, string}> */
    public static function sessionHosts(): \Iterator
    {
        yield 'root file sessions' => ['file', 'example.com', 'sqlite', 'acme.example.com'];

        yield 'tenant file sessions' => ['file', 'acme.example.com', 'tenant', 'example.com'];

        yield 'root database sessions' => ['database', 'example.com', 'sqlite', 'acme.example.com'];

        yield 'tenant database sessions' => ['database', 'acme.example.com', 'tenant', 'example.com'];
    }

    #[Test]
    #[DataProvider('sessionHosts')]
    public function password_reset_revokes_only_the_accounts_old_sessions(
        string $driver,
        string $host,
        string $connection,
        string $otherHost,
    ): void {
        Config::set('session.driver', $driver);

        Notification::fake();

        $otherUser = User::factory()->create();

        $otherUserCookies = $this->login('example.com', $otherUser->email);

        $otherTenantCookies = $this->login($otherHost, 'owner@example.com');

        $cookies = $this->login($host, 'owner@example.com', remember: true);

        $rememberName = Auth::guard('web')->getRecallerName();

        $this->browserRequest('GET', $host, 'dashboard', cookies: $cookies)->assertOk();

        $this->browserRequest('POST', $host, 'password.email', [
            'email' => 'owner@example.com',
        ])->assertValid()->assertSessionHas(
            'status',
            'If an account exists for that email address, you will receive a password reset link.',
        );

        $user = User::on($connection)->where('email', 'owner@example.com')->firstOrFail();

        $token = Notification::sent($user, ResetPassword::class)->sole()->token;

        $oldRememberToken = $user->remember_token;

        $this->browserRequest('POST', $host, 'password.update', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertValid()->assertSessionHas('status', __(Password::PASSWORD_RESET));

        $savedUser = User::on($connection)->findOrFail($user->id);

        $this->assertTrue(Hash::check('new-password', $savedUser->password));

        $this->assertNotSame($oldRememberToken, $savedUser->remember_token);

        $this->browserRequest('GET', $host, 'dashboard', cookies: $cookies)->assertRedirectToRoute('login');

        $this->browserRequest('GET', $host, 'dashboard', cookies: [
            $rememberName => $cookies[$rememberName],
        ])->assertRedirectToRoute('login');

        $this->browserRequest('POST', $host, 'login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertInvalid('email');

        $freshCookies = $this->login($host, $user->email, 'new-password');

        $this->browserRequest('GET', $host, 'dashboard', cookies: $freshCookies)->assertOk();

        $this->browserRequest('GET', 'example.com', 'dashboard', cookies: $otherUserCookies)->assertOk();

        $this->browserRequest('GET', $otherHost, 'dashboard', cookies: $otherTenantCookies)->assertOk();
    }

    /** @return array<string, string> */
    private function login(string $host, string $email, string $password = 'password', bool $remember = false): array
    {
        $response = $this->browserRequest('POST', $host, 'login', [
            'email' => $email,
            'password' => $password,
            'remember' => $remember,
        ])->assertRedirectToRoute('dashboard');

        $cookie = $response->getCookie(config('session.cookie'));

        $this->assertInstanceOf(Cookie::class, $cookie);

        $cookies = [$cookie->getName() => $cookie->getValue()];

        if ($remember) {
            $recaller = $response->getCookie(Auth::guard('web')->getRecallerName());

            $this->assertInstanceOf(Cookie::class, $recaller);

            $cookies[$recaller->getName()] = $recaller->getValue();
        }

        return $cookies;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $cookies
     */
    private function browserRequest(string $method, string $host, string $route, array $data = [], array $cookies = []): TestResponse
    {
        Auth::forgetGuards();

        $this->app['session']->forgetDrivers();

        $this->app->forgetInstance('session.store');

        $this->app->forgetInstance('redirect');

        $this->app['cookie']->flushQueuedCookies();

        foreach ($this->app['router']->getRoutes() as $registeredRoute) {
            $registeredRoute->flushController();
        }

        $this->defaultCookies = $cookies;

        return $this->{$method === 'GET' ? 'get' : 'post'}('http://'.$host.route($route, [], false), $data);
    }
}
