<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use ZipArchive;

#[Signature('backup:verify
            {archive : The backup archive to verify}
            {--main-database= : The main SQLite database path}
            {--tenant-root= : The tenant database directory}')]
class VerifyBackupCommand extends Command
{
    protected $description = 'Verify that a backup contains usable main and tenant SQLite databases.';

    public function handle(): int
    {
        $archivePath = (string) $this->argument('archive');
        $mainDatabase = (string) ($this->option('main-database') ?? database_path('database.sqlite'));
        $tenantRoot = (string) ($this->option('tenant-root') ?? database_path('db'));
        $tenantDatabases = glob($tenantRoot.'/*.sqlite') ?: [];

        if (! is_file($archivePath)) {
            $this->error("Backup archive not found: {$archivePath}");

            return self::FAILURE;
        }

        $archive = new ZipArchive;

        if ($archive->open($archivePath) !== true) {
            $this->error("Unable to open backup archive: {$archivePath}");

            return self::FAILURE;
        }

        $temporaryDirectory = storage_path('app/backup-verify-'.bin2hex(random_bytes(8)));
        mkdir($temporaryDirectory, 0700, true);

        try {
            $databaseEntries = $this->getDatabaseEntries($archive, $mainDatabase, $tenantDatabases);
            $this->restoreAndCheckDatabases($archive, $temporaryDirectory, $databaseEntries);
            $this->info(sprintf('Backup verified: %d SQLite database(s) restored successfully.', count($databaseEntries)));

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $archive->close();
            $this->removeDirectory($temporaryDirectory);
        }
    }

    /** @param list<string> $tenantDatabases */
    private function getDatabaseEntries(ZipArchive $archive, string $mainDatabase, array $tenantDatabases): array
    {
        $entries = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->getNameIndex($index);

            throw_if($entry === false || str_contains($entry, '../') || str_starts_with($entry, '/'), RuntimeException::class, 'Backup contains an unsafe archive path.');

            $entries[] = $entry;
        }

        $databaseEntries = [];

        foreach ([$mainDatabase => 'database/'.basename($mainDatabase)] as $database => $expectedPath) {
            $entry = $this->findDatabaseEntry($entries, $expectedPath);

            if ($entry === null) {
                throw new RuntimeException('Backup is missing database: '.basename($database));
            }

            $databaseEntries[$database] = $entry;
        }

        foreach ($tenantDatabases as $database) {
            $expectedPath = 'database/db/'.basename($database);
            $entry = $this->findDatabaseEntry($entries, $expectedPath);

            if ($entry === null) {
                throw new RuntimeException('Backup is missing database: '.basename($database));
            }

            $databaseEntries[$database] = $entry;
        }

        return $databaseEntries;
    }

    /** @param array<string, string> $databaseEntries */
    private function restoreAndCheckDatabases(ZipArchive $archive, string $temporaryDirectory, array $databaseEntries): void
    {
        throw_unless($archive->extractTo($temporaryDirectory), RuntimeException::class, 'Unable to extract backup archive.');

        foreach ($databaseEntries as $database => $entry) {
            $restoredDatabase = $temporaryDirectory.'/'.$entry;

            if (! is_file($restoredDatabase) || ! $this->isHealthySqliteDatabase($restoredDatabase)) {
                throw new RuntimeException('Restored database failed integrity check: '.basename($database));
            }
        }
    }

    /** @param list<string> $entries */
    private function findDatabaseEntry(array $entries, string $expectedPath): ?string
    {
        $matches = [];

        foreach ($entries as $entry) {
            if ($entry === $expectedPath || str_ends_with($entry, '/'.$expectedPath)) {
                $matches[] = $entry;
            }
        }

        if (count($matches) > 1) {
            throw new RuntimeException('Backup contains multiple entries for database: '.basename($expectedPath));
        }

        return $matches[0] ?? null;
    }

    private function isHealthySqliteDatabase(string $database): bool
    {
        try {
            $connection = new \PDO('sqlite:'.$database);
            $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            return $connection->query('PRAGMA integrity_check')->fetchColumn() === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
