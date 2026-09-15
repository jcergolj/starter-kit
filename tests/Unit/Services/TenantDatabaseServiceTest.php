<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\InvalidSubdomainFormat;
use App\Exceptions\TemplateDatabaseNotFound;
use App\Exceptions\TenantDatabaseAlreadyExists;
use App\Exceptions\TenantDatabaseProvisioningFailed;
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

    private string $databaseRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseRoot = sys_get_temp_dir().'/starter-kit-tenant-tests-'.bin2hex(random_bytes(8));
        mkdir($this->databaseRoot, 0755, true);

        $this->service = new TenantDatabaseService($this->databaseRoot);
    }

    protected function tearDown(): void
    {
        $paths = array_merge(
            glob($this->databaseRoot.'/*') ?: [],
            glob($this->databaseRoot.'/.tenant-*') ?: [],
        );

        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->databaseRoot);

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

        $this->assertSame($this->databaseRoot.'/mytenant.sqlite', $result);
    }

    #[Test]
    public function database_exists_returns_true_when_file_exists(): void
    {
        $dbPath = $this->databaseRoot.'/testtenant.sqlite';
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
    public function get_tenant_subdomains_returns_only_sqlite_files_in_the_test_root(): void
    {
        touch($this->databaseRoot.'/first-tenant.sqlite');
        touch($this->databaseRoot.'/second-tenant.sqlite');
        touch($this->databaseRoot.'/not-a-database.txt');

        $result = $this->service->getTenantSubdomains();

        $this->assertSame(['first-tenant', 'second-tenant'], $result);
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
        yield 'hyphen' => ['tenant-name'];
        yield 'mixed' => ['tenant-123-name'];
        yield 'maximum length' => [str_repeat('a', 63)];
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
        yield 'underscore' => ['tenant_name'];
        yield 'leading hyphen' => ['-tenant'];
        yield 'trailing hyphen' => ['tenant-'];
        yield 'too long' => [str_repeat('a', 64)];
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
    public function is_trusted_host_accepts_the_main_domain_and_one_tenant_label(): void
    {
        Config::set('app.domain', 'example.com');

        $this->assertTrue($this->service->isTrustedHost(Request::create('http://example.com')));

        $this->assertTrue($this->service->isTrustedHost(Request::create('http://tenant.example.com')));
    }

    #[Test]
    #[DataProvider('untrustedHostProvider')]
    public function is_trusted_host_rejects_hosts_outside_the_configured_domain(string $host): void
    {
        Config::set('app.domain', 'example.com');

        $this->assertFalse($this->service->isTrustedHost(Request::create("http://{$host}")));
    }

    public static function untrustedHostProvider(): \Iterator
    {
        yield 'unrelated domain' => ['tenant.example.net'];
        yield 'nested tenant' => ['nested.tenant.example.com'];
        yield 'lookalike suffix' => ['tenant-example.com'];
        yield 'domain suffix confusion' => ['example.com.evil.test'];
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

    #[Test]
    public function create_tenant_database_copies_the_template_to_an_isolated_path(): void
    {
        $templatePath = $this->databaseRoot.'/template.sqlite';
        file_put_contents($templatePath, 'template contents');
        $service = $this->persistentService($templatePath);

        $service->createTenantDatabase('tenant');

        $this->assertFileExists($this->databaseRoot.'/tenant.sqlite');
        $this->assertSame('template contents', file_get_contents($this->databaseRoot.'/tenant.sqlite'));
    }

    #[Test]
    public function create_tenant_database_requires_a_template_file(): void
    {
        $service = $this->persistentService($this->databaseRoot.'/missing.sqlite');

        $this->expectException(TemplateDatabaseNotFound::class);

        $service->createTenantDatabase('tenant');
    }

    #[Test]
    public function create_tenant_database_requires_a_writable_database_directory(): void
    {
        $templatePath = $this->databaseRoot.'/template.sqlite';
        file_put_contents($templatePath, 'template contents');
        $service = new TenantDatabaseService($this->databaseRoot.'/missing', $templatePath);
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->databaseRoot.'/application.sqlite');

        $this->expectException(TenantDatabaseProvisioningFailed::class);

        $service->createTenantDatabase('tenant');
    }

    #[Test]
    public function create_tenant_database_does_not_overwrite_an_existing_file(): void
    {
        $templatePath = $this->databaseRoot.'/template.sqlite';
        file_put_contents($templatePath, 'new contents');
        file_put_contents($this->databaseRoot.'/tenant.sqlite', 'existing contents');
        $service = $this->persistentService($templatePath);

        $this->expectException(TenantDatabaseAlreadyExists::class);

        try {
            $service->createTenantDatabase('tenant');
        } finally {
            $this->assertSame('existing contents', file_get_contents($this->databaseRoot.'/tenant.sqlite'));
        }
    }

    private function persistentService(string $templatePath): TenantDatabaseService
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->databaseRoot.'/application.sqlite');
        touch($this->databaseRoot.'/application.sqlite');

        return new TenantDatabaseService($this->databaseRoot, $templatePath);
    }
}
