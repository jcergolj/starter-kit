<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class BackupCommandTest extends TestCase
{
    private string $fixtureDirectory;

    private string $originalDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDirectory = '/tmp/opencode/tenant-backup-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixtureDirectory.'/database/db', 0700, true);
        $this->originalDatabasePath = database_path();
        $this->app->useDatabasePath($this->fixtureDirectory.'/database');

        $configuration = require base_path('config/backup.php');
        $configuration['backup']['name'] = 'fixture';
        $configuration['backup']['temporary_directory'] = $this->fixtureDirectory.'/temporary';
        $configuration['backup']['destination']['disks'] = ['backup-fixture'];
        $configuration['backup']['password'] = null;
        config()->set('backup', $configuration);
        config()->set('filesystems.disks.backup-fixture', [
            'driver' => 'local',
            'root' => $this->fixtureDirectory.'/archives',
        ]);
        config()->set('database.connections.sqlite.database', database_path('main.sqlite'));

        $main = new PDO('sqlite:'.database_path('main.sqlite'));
        $main->exec("CREATE TABLE records (value TEXT); INSERT INTO records VALUES ('main-record')");
    }

    protected function tearDown(): void
    {
        $this->app->useDatabasePath($this->originalDatabasePath);
        File::deleteDirectory($this->fixtureDirectory);

        parent::tearDown();
    }

    #[Test]
    public function it_fails_visibly_without_publishing_an_archive_when_a_tenant_snapshot_fails(): void
    {
        file_put_contents(database_path('db/broken.sqlite'), 'not a database');

        $this->artisan('backup:run', [
            '--filename' => 'backup.zip',
            '--tries' => 1,
            '--disable-notifications' => true,
        ])->assertFailed()
            ->expectsOutputToContain('Tenant snapshot failed: broken.sqlite');

        $this->assertFileDoesNotExist($this->fixtureDirectory.'/archives/fixture/backup.zip');

        $this->assertDirectoryDoesNotExist($this->fixtureDirectory.'/temporary/temp');
    }

    #[Test]
    public function it_restores_consistent_transactions_while_tenants_keep_committing_writes(): void
    {
        $writers = [];

        foreach (['acme', 'beta'] as $tenant) {
            $path = database_path('db/'.$tenant.'.sqlite');

            $connection = new PDO('sqlite:'.$path);
            $connection->exec('PRAGMA journal_mode=WAL; PRAGMA wal_autocheckpoint=0');
            $connection->exec('CREATE TABLE records (id INTEGER PRIMARY KEY); CREATE TABLE copies (id INTEGER PRIMARY KEY)');

            $writer = new Process([PHP_BINARY, '-r', <<<'PHP'
                $db = new PDO('sqlite:'.$argv[1]);
                $db->exec('PRAGMA wal_autocheckpoint=0');
                for ($id = 1; ; $id++) {
                    $db->beginTransaction();
                    $db->exec("INSERT INTO records VALUES ($id)");
                    $db->exec("INSERT INTO copies VALUES ($id)");
                    $db->commit();
                    if ($id === 1) {
                        echo "ready\n";
                        flush();
                    }
                }
                PHP, $path]);
            $writer->start();
            $writers[] = $writer;
        }

        try {
            foreach ($writers as $writer) {
                $this->assertTrue($writer->waitUntil(static function (string $type, string $output): bool {
                    return str_contains($output, 'ready');
                }));
            }

            $this->artisan('backup:run', [
                '--filename' => 'backup.zip',
                '--tries' => 1,
                '--disable-notifications' => true,
            ])->assertSuccessful();

            foreach ($writers as $writer) {
                $this->assertTrue($writer->isRunning());
            }
        } finally {
            foreach ($writers as $writer) {
                $writer->stop();
            }
        }

        $archive = new ZipArchive;

        $this->assertTrue($archive->open($this->fixtureDirectory.'/archives/fixture/backup.zip'));

        try {
            $this->assertTrue($archive->extractTo($this->fixtureDirectory.'/restored'));

            foreach (['acme', 'beta'] as $tenant) {
                $restored = new PDO('sqlite:'.$this->fixtureDirectory.'/restored/database/db/'.$tenant.'.sqlite');

                $this->assertSame('ok', $restored->query('PRAGMA integrity_check')->fetchColumn());

                $this->assertSame(1, $restored->query('SELECT id FROM records WHERE id = 1')->fetchColumn());

                $this->assertGreaterThan(1, $restored->query('SELECT COUNT(*) FROM records')->fetchColumn());

                $this->assertSame(
                    0,
                    $restored->query('SELECT COUNT(*) FROM (SELECT id FROM records EXCEPT SELECT id FROM copies)')
                        ->fetchColumn(),
                );

                $this->assertSame(
                    0,
                    $restored->query('SELECT COUNT(*) FROM (SELECT id FROM copies EXCEPT SELECT id FROM records)')
                        ->fetchColumn(),
                );
            }
        } finally {
            $archive->close();
        }
    }

    #[Test]
    public function it_restores_distinct_main_and_tenant_records_while_writes_are_open(): void
    {
        $writers = [];

        foreach (['acme' => 'acme-record', 'beta' => 'beta-record'] as $tenant => $value) {
            $writer = new PDO('sqlite:'.database_path('db/'.$tenant.'.sqlite'));
            $writer->exec('PRAGMA journal_mode=WAL; PRAGMA wal_autocheckpoint=0');
            $writer->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, value TEXT)');
            $statement = $writer->prepare('INSERT INTO records VALUES (1, ?)');
            $statement->execute([$value]);
            $writer->beginTransaction();
            $writer->exec("UPDATE records SET value = 'uncommitted-record' WHERE id = 1");
            $writers[] = $writer;
        }

        try {
            $this->artisan('backup:run', [
                '--filename' => 'backup.zip',
                '--tries' => 1,
                '--disable-notifications' => true,
            ])->assertSuccessful();
        } finally {
            foreach ($writers as $writer) {
                $writer->rollBack();
            }
        }

        $archive = new ZipArchive;

        $this->assertTrue($archive->open($this->fixtureDirectory.'/archives/fixture/backup.zip'));

        try {
            $this->assertSame(3, $archive->numFiles);

            $this->assertTrue($archive->extractTo($this->fixtureDirectory.'/restored'));

            foreach (['acme' => 'acme-record', 'beta' => 'beta-record'] as $tenant => $expectedValue) {
                $this->assertNotFalse($archive->locateName('database/db/'.$tenant.'.sqlite'));

                $restored = new PDO('sqlite:file:'.
                    $this->fixtureDirectory.
                    '/restored/database/db/'.
                    $tenant.
                    '.sqlite?mode=ro');

                $this->assertSame('ok', $restored->query('PRAGMA integrity_check')->fetchColumn());

                $this->assertSame(
                    $expectedValue,
                    $restored->query('SELECT value FROM records WHERE id = 1')
                        ->fetchColumn(),
                );
            }

            $dump = $archive->getFromName('db-dumps/sqlite-sqlite-database.sql');

            $this->assertIsString($dump);

            $main = new PDO('sqlite:'.$this->fixtureDirectory.'/restored/main.sqlite');
            $main->exec($dump);

            $this->assertSame('ok', $main->query('PRAGMA integrity_check')->fetchColumn());

            $this->assertSame('main-record', $main->query('SELECT value FROM records')->fetchColumn());
        } finally {
            $archive->close();
        }

        $this->artisan('backup:verify', [
            'archive' => $this->fixtureDirectory.'/archives/fixture/backup.zip',
            '--main-database' => database_path('main.sqlite'),
            '--tenant-root' => database_path('db'),
        ])->assertSuccessful();
    }
}
