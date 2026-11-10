<?php

declare(strict_types=1);

namespace App\Features\TenantDatabase\Listeners;

use DirectoryIterator;
use Illuminate\Filesystem\Filesystem;
use PDO;
use PDOException;
use RuntimeException;
use Spatie\Backup\Events\BackupManifestWasCreated;

class SnapshotTenantDatabases
{
    public function __construct(private readonly Filesystem $files) {}

    public function handle(BackupManifestWasCreated $event): void
    {
        $snapshotDirectory = dirname($event->manifest->path()).'/database/db';

        $this->files->ensureDirectoryExists($snapshotDirectory, 0700, true);

        foreach (new DirectoryIterator(database_path('db')) as $file) {
            if ($file->getExtension() !== 'sqlite' || ! $file->isFile()) {
                continue;
            }

            $snapshot = $snapshotDirectory.'/'.$file->getFilename();

            try {
                $connection = new PDO('sqlite:file:'.$file->getPathname().'?mode=ro');

                $statement = $connection->prepare('VACUUM INTO ?');

                $statement->execute([$snapshot]);
            } catch (PDOException $exception) {
                throw new RuntimeException(
                    __(
                        'Tenant snapshot failed: :tenant',
                        ['tenant' => $file->getFilename()],
                    ),
                    0,
                    $exception,
                );
            }

            $event->manifest->addFiles($snapshot);
        }
    }
}
