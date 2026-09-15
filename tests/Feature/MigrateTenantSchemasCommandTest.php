<?php

declare(strict_types=1);

namespace Tests\Feature;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrateTenantSchemasCommandTest extends TestCase
{
    private string $fixtureDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDirectory = sys_get_temp_dir().'/starter-kit-schema-tests-'.bin2hex(random_bytes(8));
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
    public function it_migrates_the_template_and_all_tenant_databases_idempotently(): void
    {
        $template = $this->createEmptyDatabase('template.sqlite');
        $firstTenant = $this->createEmptyDatabase('first.sqlite', true);
        $secondTenant = $this->createEmptyDatabase('second.sqlite', true);

        $arguments = [
            '--tenant-root' => dirname($firstTenant),
            '--template' => $template,
        ];

        $this->artisan('tenants:migrate', $arguments)->assertSuccessful();

        foreach ([$template, $firstTenant, $secondTenant] as $database) {
            $connection = new PDO('sqlite:'.$database);
            $this->assertSame(4, (int) $connection->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
            $this->assertSame(1, (int) $connection->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn());
        }

        $this->artisan('tenants:migrate', $arguments)->assertSuccessful();

        $connection = new PDO('sqlite:'.$firstTenant);
        $this->assertSame(4, (int) $connection->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
    }

    #[Test]
    public function it_reports_a_failed_tenant_without_silently_skipping_it(): void
    {
        $template = $this->createEmptyDatabase('template.sqlite');
        $healthyTenant = $this->createEmptyDatabase('healthy.sqlite', true);
        $failedTenant = $this->fixtureDirectory.'/db/broken.sqlite';
        file_put_contents($failedTenant, 'not a sqlite database');

        $this->artisan('tenants:migrate', [
            '--tenant-root' => dirname($healthyTenant),
            '--template' => $template,
        ])->assertFailed()
            ->expectsOutputToContain('Failed broken:')
            ->expectsOutputToContain('1 migration target(s) failed.');

        $connection = new PDO('sqlite:'.$healthyTenant);
        $this->assertSame(1, (int) $connection->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn());
    }

    private function createEmptyDatabase(string $filename, bool $inTenantDirectory = false): string
    {
        $path = $this->fixtureDirectory.'/'.($inTenantDirectory ? 'db/' : '').$filename;
        touch($path);

        return $path;
    }
}
