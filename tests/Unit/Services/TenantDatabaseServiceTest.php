<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\InvalidSubdomainFormat;
use App\Services\TenantDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TenantDatabaseService::class)]
class TenantDatabaseServiceTest extends TestCase
{
    public TenantDatabaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TenantDatabaseService;
    }

    protected function tearDown(): void
    {
        $testDbPath = database_path('db/testtenant.sqlite');
        if (file_exists($testDbPath)) {
            unlink($testDbPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function extract_subdomain_returns_first_part_of_host(): void
    {
        $request = Request::create('http://tenant.example.com');

        $result = $this->service->extractSubdomain($request);

        $this->assertSame('tenant', $result);
    }

    #[Test]
    public function extract_subdomain_returns_first_part_for_two_part_domain(): void
    {
        $request = Request::create('http://example.com');

        $result = $this->service->extractSubdomain($request);

        $this->assertSame('example', $result);
    }

    #[Test]
    public function extract_subdomain_returns_null_for_single_part_host(): void
    {
        $request = Request::create('http://localhost');

        $result = $this->service->extractSubdomain($request);

        $this->assertNull($result);
    }

    #[Test]
    public function extract_subdomain_handles_nested_subdomains(): void
    {
        $request = Request::create('http://deep.nested.example.com');

        $result = $this->service->extractSubdomain($request);

        $this->assertSame('deep', $result);
    }

    #[Test]
    public function get_database_path_returns_correct_path(): void
    {
        $result = $this->service->getDatabasePath('mytenant');

        $this->assertSame(database_path('db/mytenant.sqlite'), $result);
    }

    #[Test]
    public function database_exists_returns_true_when_file_exists(): void
    {
        $dbPath = database_path('db/testtenant.sqlite');
        if (! is_dir(dirname($dbPath))) {
            mkdir(dirname($dbPath), 0755, true);
        }
        touch($dbPath);

        $result = $this->service->databaseExists('testtenant');

        $this->assertTrue($result);
    }

    #[Test]
    public function database_exists_returns_false_when_file_does_not_exist(): void
    {
        $result = $this->service->databaseExists('nonexistent');

        $this->assertFalse($result);
    }

    #[Test]
    #[DataProvider('validSubdomainProvider')]
    public function validate_subdomain_accepts_valid_formats(string $subdomain): void
    {
        $this->service->validateSubdomain($subdomain);

        $this->assertTrue(true); // If no exception thrown, test passes
    }

    public static function validSubdomainProvider(): \Iterator
    {
        yield 'lowercase letters' => ['tenant'];
        yield 'numbers' => ['tenant123'];
        yield 'underscore' => ['tenant_name'];
        yield 'hyphen' => ['tenant-name'];
        yield 'mixed' => ['tenant_123-name'];
    }

    #[Test]
    #[DataProvider('invalidSubdomainProvider')]
    public function validate_subdomain_throws_exception_for_invalid_formats(string $subdomain): void
    {
        $this->expectException(InvalidSubdomainFormat::class);
        $this->expectExceptionMessage("Invalid subdomain format: '{$subdomain}'.");

        $this->service->validateSubdomain($subdomain);
    }

    public static function invalidSubdomainProvider(): \Iterator
    {
        yield 'uppercase letters' => ['Tenant'];
        yield 'spaces' => ['tenant name'];
        yield 'special characters' => ['tenant@name'];
        yield 'dots' => ['tenant.name'];
        yield 'empty string' => [''];
        yield 'slashes' => ['tenant/name'];
    }

    #[Test]
    public function is_main_domain_returns_true_when_host_matches_app_domain(): void
    {
        Config::set('app.domain', 'example.com');
        $request = Request::create('http://example.com');

        $result = $this->service->isMainDomain($request);

        $this->assertTrue($result);
    }

    #[Test]
    public function is_main_domain_returns_false_for_subdomain(): void
    {
        Config::set('app.domain', 'example.com');
        $request = Request::create('http://tenant.example.com');

        $result = $this->service->isMainDomain($request);

        $this->assertFalse($result);
    }

    #[Test]
    public function create_tenant_database_throws_exception_for_invalid_subdomain(): void
    {
        $this->expectException(InvalidSubdomainFormat::class);

        $this->service->createTenantDatabase('InvalidSubdomain');
    }

    #[Test]
    public function connect_to_tenant_throws_exception_for_invalid_subdomain(): void
    {
        $this->expectException(InvalidSubdomainFormat::class);

        $this->service->connectToTenant('InvalidSubdomain');
    }

    #[Test]
    public function create_tenant_database_skips_in_testing_with_memory_database(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        $this->service->createTenantDatabase('testtenant');

        $this->assertFalse($this->service->databaseExists('testtenant'));
    }

    #[Test]
    public function connect_to_tenant_skips_in_testing_with_memory_database(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        $this->service->connectToTenant('testtenant');

        $this->assertTrue(true); // If no exception thrown, test passes
    }
}
