<?php

declare(strict_types=1);

namespace Tests\Unit\Scripts;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class DeployScriptTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/starter-kit-deploy-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/bin', 0700, true);
        touch($this->directory.'/artisan');
        file_put_contents($this->directory.'/bin/stub', <<<'BASH'
#!/bin/bash
name="${0##*/}"
echo "$name $*" >> "$DEPLOY_TEST_LOG"
case "$name" in
    systemctl) echo 'php8.5-fpm.service loaded active running' ;;
    git)
        if [[ "$1" == config ]]; then echo "$DEPLOY_TEST_APP"; fi
        ;;
    php)
        if [[ "$*" == 'artisan tenants:migrate --no-interaction' && "$DEPLOY_TEST_FAIL" == 1 ]]; then
            echo 'Failed broken: tenant migration failed' >&2
            exit 1
        fi
        ;;
esac
exit 0
BASH);
        chmod($this->directory.'/bin/stub', 0700);

        foreach (['php', 'git', 'composer', 'systemctl', 'sudo'] as $command) {
            symlink('stub', $this->directory.'/bin/'.$command);
        }
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_migrates_tenants_after_the_main_database_before_building_assets_and_caches(): void
    {
        $process = $this->deploy();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());

        $log = file_get_contents($this->directory.'/commands.log');

        $this->assertMatchesRegularExpression(
            '/php artisan migrate --force\n.*php artisan tenants:migrate --no-interaction\n.*php artisan tailwindcss:build\n.*php artisan importmap:optimize\n.*php artisan config:cache\n/s',
            (string) $log,
        );
    }

    #[Test]
    public function it_reports_a_tenant_failure_and_stops_before_assets_and_caches(): void
    {
        $process = $this->deploy(failTenantMigration: true);

        $this->assertSame(1, $process->getExitCode());

        $this->assertStringContainsString('Failed broken: tenant migration failed', $process->getErrorOutput());

        $this->assertStringContainsString('Deploy failed', $process->getOutput());

        $log = file_get_contents($this->directory.'/commands.log');

        $this->assertMatchesRegularExpression(
            '/php artisan tenants:migrate --no-interaction\n.*php artisan up\n/s',
            (string) $log,
        );

        $this->assertStringNotContainsString('php artisan tailwindcss:build', (string) $log);

        $this->assertStringNotContainsString('php artisan config:cache', (string) $log);

        $this->assertStringNotContainsString('horizon:terminate', (string) $log);

        $this->assertStringNotContainsString('Deployment complete!', $process->getOutput());
    }

    private function deploy(bool $failTenantMigration = false): Process
    {
        $process = new Process(['bash', dirname(__DIR__, 3).'/scripts/deploy.sh', $this->directory], env: [
            'PATH' => $this->directory.'/bin:'.getenv('PATH'),
            'DEPLOY_TEST_LOG' => $this->directory.'/commands.log',
            'DEPLOY_TEST_APP' => $this->directory,
            'DEPLOY_TEST_FAIL' => $failTenantMigration ? '1' : '0',
        ]);
        $process->run();

        return $process;
    }
}
