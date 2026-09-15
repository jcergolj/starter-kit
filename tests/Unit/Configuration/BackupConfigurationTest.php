<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupConfigurationTest extends TestCase
{
    #[Test]
    public function backup_includes_the_persistent_tenant_directory_and_follows_shared_links(): void
    {
        $configuration = require base_path('config/backup.php');
        $files = $configuration['backup']['source']['files'];

        $this->assertContains(database_path(), $files['include']);

        $this->assertContains(database_path('db'), $files['include']);

        $this->assertTrue($files['follow_links']);

        $this->assertContains('sqlite', $configuration['backup']['source']['databases']);
    }

    #[Test]
    public function deployment_shares_the_tenant_database_directory(): void
    {
        $deployment = file_get_contents(base_path('deploy.php'));

        $this->assertIsString($deployment);

        $this->assertStringContainsString("add('shared_dirs', [", $deployment);

        $this->assertStringContainsString("    'database/db',", $deployment);
    }

    #[Test]
    public function deployment_migrates_tenants_before_publishing_cache(): void
    {
        $deployment = file_get_contents(base_path('deploy.php'));

        $this->assertIsString($deployment);
        $this->assertStringContainsString("task('artisan:tenant-migrate'", $deployment);
        $this->assertStringContainsString('artisan tenants:migrate', $deployment);
        $this->assertStringContainsString("after('artisan:migrate', 'artisan:tenant-migrate');", $deployment);
        $this->assertStringContainsString("after('artisan:tenant-migrate', 'deploy:cache');", $deployment);
    }
}
