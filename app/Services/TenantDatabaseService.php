<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DatabaseNotFound;
use App\Exceptions\InvalidSubdomainFormat;
use App\Exceptions\TemplateDatabaseNotFound;
use App\Exceptions\TenantDatabaseAlreadyExists;
use App\Exceptions\TenantDatabaseProvisioningFailed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class TenantDatabaseService
{
    public function __construct(
        private ?string $databaseRoot = null,
        private ?string $templatePath = null,
    ) {}

    public function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        if (count($parts) >= 2) {
            return $parts[0];
        }

        return null;
    }

    public function getDatabasePath(string $subdomain): string
    {
        return ($this->databaseRoot ?? database_path('db'))."/{$subdomain}.sqlite";
    }

    /** @return list<string> */
    public function getTenantSubdomains(): array
    {
        $databasePaths = glob(($this->databaseRoot ?? database_path('db')).'/*.sqlite');

        return $databasePaths === false ? [] : array_map(
            static function (string $path): string {
                return basename($path, '.sqlite');
            },
            $databasePaths,
        );
    }

    public function databaseExists(string $subdomain): bool
    {
        return file_exists($this->getDatabasePath($subdomain));
    }

    public function validateSubdomain(string $subdomain): void
    {
        throw_unless($this->isValidSubdomain($subdomain), InvalidSubdomainFormat::class, $subdomain);
    }

    public function connectToTenant(string $subdomain): void
    {
        $this->validateSubdomain($subdomain);

        if ($this->isTestingWithInMemoryDatabase()) {
            return;
        }

        $databasePath = $this->getDatabasePath($subdomain);

        throw_unless(file_exists($databasePath), DatabaseNotFound::class);

        Config::set('database.connections.tenant.database', $databasePath);
        Config::set('database.default', 'tenant');

        try {
            DB::purge('tenant');
            DB::reconnect('tenant');
        } catch (Throwable) {
            DB::purge('tenant');

            throw new DatabaseNotFound;
        }
    }

    public function createTenantDatabase(string $subdomain): void
    {
        $this->validateSubdomain($subdomain);

        if ($this->isTestingWithInMemoryDatabase()) {
            return;
        }

        $databasePath = $this->getDatabasePath($subdomain);
        $databaseDirectory = dirname($databasePath);
        $templatePath = $this->templatePath ?? database_path('template.sqlite');

        if (! is_dir($databaseDirectory) || ! is_writable($databaseDirectory)) {
            throw new TenantDatabaseProvisioningFailed('Tenant database directory is not writable.');
        }

        if (! is_file($templatePath) || ! is_readable($templatePath)) {
            throw new TemplateDatabaseNotFound;
        }

        $lock = @fopen($databaseDirectory.'/.tenant-database.lock', 'c');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new TenantDatabaseProvisioningFailed('Unable to lock the tenant database directory.');
        }

        $temporaryPath = null;

        try {
            if (file_exists($databasePath)) {
                throw new TenantDatabaseAlreadyExists($subdomain);
            }

            $temporaryPath = @tempnam($databaseDirectory, '.tenant-database-');

            if ($temporaryPath === false || ! @copy($templatePath, $temporaryPath)) {
                throw new TenantDatabaseProvisioningFailed('Unable to copy the tenant database template.');
            }

            if (! @rename($temporaryPath, $databasePath)) {
                throw new TenantDatabaseProvisioningFailed('Unable to publish the tenant database.');
            }

            $temporaryPath = null;
        } finally {
            if ($temporaryPath !== null && file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }

            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function isTestingWithInMemoryDatabase(): bool
    {
        return config('database.connections.'.config('database.default').'.database') === ':memory:';
    }

    public function isMainDomain(Request $request): bool
    {
        return $request->getHost() === Config::get('app.domain');
    }

    public function isTrustedHost(Request $request): bool
    {
        $host = $request->getHost();
        $domain = (string) Config::get('app.domain');

        if ($host === $domain) {
            return true;
        }

        $subdomain = str_ends_with($host, ".{$domain}")
            ? substr($host, 0, -strlen(".{$domain}"))
            : '';

        return $subdomain !== ''
            && ! str_contains($subdomain, '.')
            && $this->isValidSubdomain($subdomain);
    }

    private function isValidSubdomain(string $subdomain): bool
    {
        return strlen($subdomain) <= 63
            && preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $subdomain) === 1;
    }
}
