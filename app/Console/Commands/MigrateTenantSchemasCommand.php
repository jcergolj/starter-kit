<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('tenants:migrate
            {--tenant-root= : The persistent tenant database directory}
            {--template= : The current tenant schema template path}')]
class MigrateTenantSchemasCommand extends Command
{
    protected $description = 'Apply forward-only migrations to the tenant databases and schema template.';

    public function handle(): int
    {
        $tenantRoot = (string) ($this->option('tenant-root') ?? database_path('db'));
        $templatePath = (string) ($this->option('template') ?? database_path('template.sqlite'));
        $tenantPaths = $this->tenantPaths($tenantRoot);

        if (! is_file($templatePath)) {
            $this->error("Tenant schema template not found: {$templatePath}");

            return self::FAILURE;
        }

        $snapshot = $tenantPaths;
        $targets = ['template' => $templatePath];

        foreach ($tenantPaths as $tenantPath) {
            $targets[basename($tenantPath, '.sqlite')] = $tenantPath;
        }

        $failed = [];
        $originalTenantDatabase = config('database.connections.tenant.database');

        try {
            foreach ($targets as $name => $path) {
                try {
                    $this->migrateDatabase($path);
                    $this->info("Migrated {$name}.");
                } catch (Throwable $exception) {
                    $failed[$name] = $exception->getMessage();
                    $this->error("Failed {$name}: {$exception->getMessage()}");
                }
            }
        } finally {
            Config::set('database.connections.tenant.database', $originalTenantDatabase);
            DB::purge('tenant');
        }

        if ($snapshot !== $this->tenantPaths($tenantRoot)) {
            $this->error('Tenant database membership changed during migration; run the command again.');
            $failed['membership'] = 'changed during migration';
        }

        if ($failed !== []) {
            $this->error(sprintf('%d migration target(s) failed.', count($failed)));

            return self::FAILURE;
        }

        $this->components->info(sprintf('Migrated template and %d tenant database(s).', count($tenantPaths)));

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function tenantPaths(string $tenantRoot): array
    {
        $paths = glob($tenantRoot.'/*.sqlite') ?: [];
        sort($paths);

        return $paths;
    }

    private function migrateDatabase(string $databasePath): void
    {
        Config::set('database.connections.tenant.database', $databasePath);
        DB::purge('tenant');

        $exitCode = Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations'),
            '--realpath' => true,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        throw_if($exitCode !== self::SUCCESS, \RuntimeException::class, 'The migration command returned a failure status.');
    }
}
