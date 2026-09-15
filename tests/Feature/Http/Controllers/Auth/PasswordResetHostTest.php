<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use App\Services\TenantDatabaseService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetHostTest extends TestCase
{
    private string $databaseRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseRoot = sys_get_temp_dir().'/starter-kit-reset-host-tests-'.bin2hex(random_bytes(8));
        mkdir($this->databaseRoot, 0755, true);
        touch($this->databaseRoot.'/main.sqlite');
        touch($this->databaseRoot.'/acme.sqlite');

        Config::set([
            'app.domain' => 'example.com',
            'app.url' => 'https://example.com:8443',
            'app.single_db_per_app' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => $this->databaseRoot.'/main.sqlite',
            'database.connections.tenant.database' => $this->databaseRoot.'/acme.sqlite',
        ]);

        DB::purge('sqlite');
        DB::purge('tenant');

        $this->app->instance(TenantDatabaseService::class, new TenantDatabaseService($this->databaseRoot));

        foreach (['sqlite', 'tenant'] as $connection) {
            $this->artisan('migrate', ['--database' => $connection, '--no-interaction' => true])
                ->assertSuccessful();
        }

        Notification::fake();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        try {
            DB::purge('sqlite');
            DB::purge('tenant');

            foreach (glob($this->databaseRoot.'/*') ?: [] as $path) {
                unlink($path);
            }

            rmdir($this->databaseRoot);
        } finally {
            parent::tearDown();
        }
    }

    #[Test]
    #[DataProvider('trustedHosts')]
    public function reset_links_stay_on_the_host_containing_the_account(string $host, string $connection): void
    {
        $user = User::factory()->connection($connection)->create();

        $this->post('https://'.$host.':8443'.route('password.email', [], false), [
            'email' => $user->email,
        ])->assertRedirect()->assertSessionHas('status', __(Password::RESET_LINK_SENT));

        $this->assertSame('sqlite', config('database.default'));

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, $host): bool {
            $url = $notification->toMail($user)->actionUrl;

            $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
            $this->assertSame($host, parse_url($url, PHP_URL_HOST));
            $this->assertSame(8443, parse_url($url, PHP_URL_PORT));
            $this->assertSame(route('password.reset', ['token' => $notification->token], false), parse_url($url, PHP_URL_PATH));
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame(['email' => $user->email], $query);
            $this->assertTrue(Password::broker()->tokenExists($user, $notification->token));

            return true;
        });

        Notification::assertCount(1);

        $this->assertDatabaseCount('password_reset_tokens', 1, $connection);

        $this->assertDatabaseCount('password_reset_tokens', 0, $connection === 'tenant' ? 'sqlite' : 'tenant');

        Mail::assertNothingOutgoing();
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function trustedHosts(): \Iterator
    {
        yield 'main host' => ['example.com', 'sqlite'];
        yield 'tenant host' => ['acme.example.com', 'tenant'];
    }

    #[Test]
    #[DataProvider('untrustedHosts')]
    public function untrusted_hosts_cannot_create_reset_tokens_or_notifications(string $host, bool $singleDatabase): void
    {
        Config::set('app.single_db_per_app', $singleDatabase);

        $user = User::factory()->create();
        User::factory()->connection('tenant')->create(['email' => $user->email]);

        $this->post('https://'.$host.':8443'.route('password.email', [], false), [
            'email' => $user->email,
        ])->assertStatus(400);

        Notification::assertNothingSent();

        Mail::assertNothingOutgoing();

        $this->assertDatabaseCount('password_reset_tokens', 0, 'sqlite');

        $this->assertDatabaseCount('password_reset_tokens', 0, 'tenant');
    }

    /** @return \Iterator<string, array{string, bool}> */
    public static function untrustedHosts(): \Iterator
    {
        yield 'arbitrary host' => ['attacker.example.net', false];
        yield 'existing tenant on unrelated domain' => ['acme.attacker.example.net', false];
        yield 'trusted domain with malicious suffix' => ['acme.example.com.attacker.net', false];
        yield 'nested tenant' => ['nested.acme.example.com', false];
        yield 'arbitrary host in single database mode' => ['attacker.example.net', true];
    }
}
