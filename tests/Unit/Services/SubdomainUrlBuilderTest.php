<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SubdomainUrlBuilder;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SubdomainUrlBuilder::class)]
final class SubdomainUrlBuilderTest extends TestCase
{
    public SubdomainUrlBuilder $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SubdomainUrlBuilder;
    }

    #[Test]
    public function build_returns_subdomain_url_with_default_app_url(): void
    {
        Config::set('app.url', 'http://example.com');

        $result = $this->service->build('tenant', '/dashboard');

        $this->assertSame('http://tenant.example.com/dashboard', $result);
    }

    #[Test]
    public function build_returns_subdomain_url_without_leading_slash_in_path(): void
    {
        Config::set('app.url', 'http://example.com');

        $result = $this->service->build('tenant', 'dashboard');

        $this->assertSame('http://tenant.example.com/dashboard', $result);
    }

    #[Test]
    public function build_returns_subdomain_url_with_empty_path(): void
    {
        Config::set('app.url', 'http://example.com');

        $result = $this->service->build('tenant', '');

        $this->assertSame('http://tenant.example.com', $result);
    }

    #[Test]
    public function build_uses_https_when_app_url_is_https(): void
    {
        Config::set('app.url', 'https://example.com');

        $result = $this->service->build('tenant', '/dashboard');

        $this->assertSame('https://tenant.example.com/dashboard', $result);
    }

    #[Test]
    public function build_includes_port_when_app_url_has_port(): void
    {
        Config::set('app.url', 'http://example.com:8000');

        $result = $this->service->build('tenant', '/dashboard');

        $this->assertSame('http://tenant.example.com:8000/dashboard', $result);
    }

    #[Test]
    public function build_main_domain_returns_url_with_path(): void
    {
        Config::set('app.url', 'http://example.com');

        $result = $this->service->buildMainDomain('/dashboard');

        $this->assertSame('http://example.com/dashboard', $result);
    }

    #[Test]
    public function build_main_domain_returns_url_without_leading_slash(): void
    {
        Config::set('app.url', 'http://example.com');

        $result = $this->service->buildMainDomain('dashboard');

        $this->assertSame('http://example.com/dashboard', $result);
    }

    #[Test]
    public function build_main_domain_returns_base_url_with_empty_path(): void
    {
        Config::set('app.url', 'http://example.com');

        $result = $this->service->buildMainDomain('');

        $this->assertSame('http://example.com', $result);
    }

    #[Test]
    public function build_main_domain_uses_https_when_app_url_is_https(): void
    {
        Config::set('app.url', 'https://example.com');

        $result = $this->service->buildMainDomain('/dashboard');

        $this->assertSame('https://example.com/dashboard', $result);
    }

    #[Test]
    public function build_main_domain_includes_port_when_app_url_has_port(): void
    {
        Config::set('app.url', 'http://example.com:8000');

        $result = $this->service->buildMainDomain('/dashboard');

        $this->assertSame('http://example.com:8000/dashboard', $result);
    }

    #[Test]
    public function build_uses_localhost_when_app_url_has_no_scheme(): void
    {
        // When URL has no scheme, parse_url treats it as path, not host
        Config::set('app.url', 'example.com');

        $result = $this->service->build('tenant', '/dashboard');

        // Falls back to localhost since parse_url can't extract host
        $this->assertSame('http://tenant.localhost/dashboard', $result);
    }

    #[Test]
    public function build_main_domain_uses_localhost_as_default_when_app_url_has_no_host(): void
    {
        Config::set('app.url', '');

        $result = $this->service->buildMainDomain('/dashboard');

        $this->assertSame('http://localhost/dashboard', $result);
    }

    #[Test]
    #[DataProvider('pathProvider')]
    public function build_handles_various_path_formats(string $path, string $expected): void
    {
        Config::set('app.url', 'http://example.com');

        $result = $this->service->build('tenant', $path);

        $this->assertSame($expected, $result);
    }

    public static function pathProvider(): \Iterator
    {
        yield 'empty path' => ['', 'http://tenant.example.com'];
        yield 'path without slash' => ['dashboard', 'http://tenant.example.com/dashboard'];
        yield 'path with leading slash' => ['/dashboard', 'http://tenant.example.com/dashboard'];
        yield 'nested path' => ['/admin/users', 'http://tenant.example.com/admin/users'];
        yield 'path with trailing slash' => ['/dashboard/', 'http://tenant.example.com/dashboard/'];
    }
}
