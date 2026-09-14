<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ConnectToUserDatabase;
use App\Models\User;
use App\Services\SubdomainUrlBuilder;
use App\Services\TenantDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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

        $this->databaseRoot = sys_get_temp_dir().'/starter-kit-middleware-tests-'.bin2hex(random_bytes(8));
        mkdir($this->databaseRoot, 0755, true);

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
}
