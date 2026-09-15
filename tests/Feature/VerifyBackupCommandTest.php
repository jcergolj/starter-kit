<?php

declare(strict_types=1);

namespace Tests\Feature;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class VerifyBackupCommandTest extends TestCase
{
    private string $fixtureDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDirectory = sys_get_temp_dir().'/starter-kit-backup-tests-'.bin2hex(random_bytes(8));
        mkdir($this->fixtureDirectory.'/db', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->fixtureDirectory.'/db/*') ?: [] as $path) {
            unlink($path);
        }

        foreach (glob($this->fixtureDirectory.'/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($this->fixtureDirectory.'/db');
        rmdir($this->fixtureDirectory);

        parent::tearDown();
    }

    #[Test]
    public function it_restores_and_checks_the_main_and_all_tenant_databases(): void
    {
        $mainDatabase = $this->createDatabase('main.sqlite', 'main-record');
        $tenantDatabase = $this->createDatabase('acme.sqlite', 'tenant-record', true);
        $archive = $this->createArchive($mainDatabase, $tenantDatabase);

        $this->artisan('backup:verify', [
            'archive' => $archive,
            '--main-database' => $mainDatabase,
            '--tenant-root' => dirname($tenantDatabase),
        ])->assertSuccessful();
    }

    #[Test]
    public function it_fails_when_a_tenant_database_is_missing_from_the_backup(): void
    {
        $mainDatabase = $this->createDatabase('main.sqlite', 'main-record');
        $tenantDatabase = $this->createDatabase('acme.sqlite', 'tenant-record', true);
        $archive = $this->createArchive($mainDatabase);

        $this->artisan('backup:verify', [
            'archive' => $archive,
            '--main-database' => $mainDatabase,
            '--tenant-root' => dirname($tenantDatabase),
        ])->assertFailed()
            ->expectsOutputToContain('Backup is missing database: acme.sqlite');
    }

    #[Test]
    public function it_fails_when_a_restored_tenant_database_is_corrupt(): void
    {
        $mainDatabase = $this->createDatabase('main.sqlite', 'main-record');
        $tenantDatabase = $this->createDatabase('acme.sqlite', 'tenant-record', true);
        $archive = $this->createArchive($mainDatabase, $tenantDatabase, true);

        $this->artisan('backup:verify', [
            'archive' => $archive,
            '--main-database' => $mainDatabase,
            '--tenant-root' => dirname($tenantDatabase),
        ])->assertFailed()
            ->expectsOutputToContain('Restored database failed integrity check: acme.sqlite');
    }

    #[Test]
    public function it_does_not_accept_a_tenant_database_from_an_unrelated_archive_path(): void
    {
        $mainDatabase = $this->createDatabase('main.sqlite', 'main-record');
        $tenantDatabase = $this->createDatabase('acme.sqlite', 'tenant-record', true);
        $archive = $this->createArchive($mainDatabase, $tenantDatabase, false, 'database/archive-copy/acme.sqlite');

        $this->artisan('backup:verify', [
            'archive' => $archive,
            '--main-database' => $mainDatabase,
            '--tenant-root' => dirname($tenantDatabase),
        ])->assertFailed()
            ->expectsOutputToContain('Backup is missing database: acme.sqlite');
    }

    private function createDatabase(string $filename, string $value, bool $inTenantDirectory = false): string
    {
        $path = $this->fixtureDirectory.'/'.($inTenantDirectory ? 'db/' : '').$filename;
        $connection = new PDO('sqlite:'.$path);
        $connection->exec('CREATE TABLE records (value TEXT NOT NULL)');
        $statement = $connection->prepare('INSERT INTO records (value) VALUES (?)');
        $statement->execute([$value]);

        return $path;
    }

    private function createArchive(
        string $mainDatabase,
        ?string $tenantDatabase = null,
        bool $corruptTenant = false,
        string $tenantArchivePath = 'database/db/acme.sqlite',
    ): string {
        $archivePath = $this->fixtureDirectory.'/backup.zip';
        $archive = new ZipArchive;
        $archive->open($archivePath, ZipArchive::CREATE);
        $archive->addFile($mainDatabase, 'database/'.basename($mainDatabase));

        if ($tenantDatabase !== null) {
            if ($corruptTenant) {
                $archive->addFromString($tenantArchivePath, 'not a sqlite database');
            } else {
                $archive->addFile($tenantDatabase, $tenantArchivePath);
            }
        }

        $archive->close();

        return $archivePath;
    }
}
