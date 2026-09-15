<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ConnectToUserDatabase;
use App\Models\User;
use App\Services\SubdomainUrlBuilder;
use App\Services\TenantDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(ConnectToUserDatabase::class)]
class ConnectToUserDatabaseTest extends TestCase
{
    public ConnectToUserDatabase $middleware;

    private string $databaseRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.domain', 'example.com');

        $this->databaseRoot = sys_get_temp_dir().'/starter-kit-middleware-tests-'.bin2hex(random_bytes(8));
        mkdir($this->databaseRoot, 0755, true);

        Config::set([
            'app.single_db_per_app' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databaseRoot.'/application.sqlite',
            'database.connections.tenant.database' => $this->databaseRoot.'/application-tenant.sqlite',
        ]);
        touch($this->databaseRoot.'/application.sqlite');

        $tenantDb = new TenantDatabaseService($this->databaseRoot);
        $urlBuilder = app(SubdomainUrlBuilder::class);
        $this->middleware = new ConnectToUserDatabase(
            $tenantDb,
            $urlBuilder
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->databaseRoot.'/*') ?: [] as $path) {
            is_file($path) ? unlink($path) : rmdir($path);
        }
        rmdir($this->databaseRoot);

        parent::tearDown();
    }

    #[Test]
    public function passes_through_for_main_domain(): void
    {
        Config::set('app.domain', 'example.com');

        $request = Request::create('http://example.com/dashboard');
        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return new Response('OK');
        });

        $this->assertTrue($nextCalled);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function passes_through_for_localhost(): void
    {
        Config::set('app.domain', 'localhost');

        $request = Request::create('http://localhost/dashboard');
        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return new Response('OK');
        });

        $this->assertTrue($nextCalled);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function returns_404_when_database_does_not_exist(): void
    {
        Config::set('app.url', 'http://example.com');

        $request = Request::create('http://nonexistent.example.com/dashboard');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function returns_404_when_database_connection_fails(): void
    {
        Config::set('app.url', 'http://example.com');

        mkdir($this->databaseRoot.'/unwritable.example.sqlite');

        $request = Request::create('http://unwritable.example.com/dashboard');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function aborts_403_when_user_does_not_own_subdomain(): void
    {
        Config::set('app.url', 'http://example.com');

        $user = new User(['username' => 'differentuser']);

        // Create the tenant database file
        $dbPath = $this->databaseRoot.'/wronguser.sqlite';
        touch($dbPath);

        try {
            $request = Request::create('http://wronguser.example.com/dashboard');
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            $exceptionThrown = false;
            try {
                $this->middleware->handle($request, function ($req) {
                    return new Response('OK');
                });
            } catch (HttpException $e) {
                $exceptionThrown = true;
                $this->assertSame(Response::HTTP_FORBIDDEN, $e->getStatusCode());
                $this->assertStringContainsString('Unauthorized access', $e->getMessage());
            }

            $this->assertTrue($exceptionThrown, 'Expected HttpException was not thrown');
        } finally {
            unlink($dbPath);
        }
    }

    #[Test]
    public function passes_through_when_authenticated_user_owns_subdomain(): void
    {
        Config::set('app.url', 'http://example.com');

        $user = new User(['username' => 'tenantowner']);

        // Create the tenant database file
        $dbPath = $this->databaseRoot.'/tenantowner.sqlite';
        touch($dbPath);

        try {
            $request = Request::create('http://tenantowner.example.com/dashboard');
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            $nextCalled = false;
            $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
                $nextCalled = true;

                return new Response('OK');
            });

            $this->assertTrue($nextCalled);
            $this->assertSame(200, $response->getStatusCode());
        } finally {
            unlink($dbPath);
        }
    }

    #[Test]
    public function passes_through_for_guest_on_existing_tenant(): void
    {
        Config::set('app.url', 'http://example.com');

        // Create the tenant database file
        $dbPath = $this->databaseRoot.'/guesttenant.sqlite';
        touch($dbPath);

        try {
            $request = Request::create('http://guesttenant.example.com/dashboard');

            $nextCalled = false;
            $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
                $nextCalled = true;

                return new Response('OK');
            });

            $this->assertTrue($nextCalled);
            $this->assertSame(200, $response->getStatusCode());
        } finally {
            unlink($dbPath);
        }
    }

    #[Test]
    public function restores_the_application_connection_between_sequential_tenant_requests(): void
    {
        $this->prepareTenantDatabase('first', 'First tenant');
        $this->prepareTenantDatabase('second', 'Second tenant');

        $firstRequest = Request::create('http://first.example.com/dashboard');
        $this->middleware->handle($firstRequest, function (): Response {
            $this->assertSame('tenant', Config::get('database.default'));
            $this->assertSame(
                $this->databaseRoot.'/first.sqlite',
                Config::get('database.connections.tenant.database'),
            );
            $this->assertSame('First tenant', DB::table('tenant_records')->value('name'));

            return new Response('OK');
        });

        $this->assertSame('sqlite', Config::get('database.default'));
        $this->assertSame(
            $this->databaseRoot.'/application-tenant.sqlite',
            Config::get('database.connections.tenant.database'),
        );

        $secondRequest = Request::create('http://second.example.com/dashboard');
        $this->middleware->handle($secondRequest, function (): Response {
            $this->assertSame('tenant', Config::get('database.default'));
            $this->assertSame(
                $this->databaseRoot.'/second.sqlite',
                Config::get('database.connections.tenant.database'),
            );
            $this->assertSame('Second tenant', DB::table('tenant_records')->value('name'));

            return new Response('OK');
        });

        $this->assertSame('sqlite', Config::get('database.default'));
    }

    #[Test]
    public function restores_the_application_connection_when_the_request_fails(): void
    {
        touch($this->databaseRoot.'/failing.sqlite');

        $exceptionThrown = false;

        try {
            $this->middleware->handle(
                Request::create('http://failing.example.com/dashboard'),
                function (): Response {
                    throw new \RuntimeException('Request failed.');
                },
            );
        } catch (\RuntimeException $exception) {
            $exceptionThrown = true;
            $this->assertSame('Request failed.', $exception->getMessage());
        }

        $this->assertTrue($exceptionThrown);
        $this->assertSame('sqlite', Config::get('database.default'));
        $this->assertSame(
            $this->databaseRoot.'/application-tenant.sqlite',
            Config::get('database.connections.tenant.database'),
        );
    }

    private function prepareTenantDatabase(string $subdomain, string $name): void
    {
        $path = $this->databaseRoot."/{$subdomain}.sqlite";
        touch($path);

        Config::set('database.connections.tenant.database', $path);
        DB::purge('tenant');
        DB::connection('tenant')->statement('create table tenant_records (name varchar(255))');
        DB::connection('tenant')->table('tenant_records')->insert(['name' => $name]);

        Config::set('database.connections.tenant.database', $this->databaseRoot.'/application-tenant.sqlite');
        DB::purge('tenant');
    }
}
