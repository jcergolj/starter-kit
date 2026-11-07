<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[CoversClass(AppServiceProvider::class)]
class AppServiceProviderTest extends TestCase
{
    #[Test]
    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(['0'])]
    public function backup_disk_requires_a_host_fingerprint(?string $fingerprint): void
    {
        config([
            'filesystems.disks.sftp-backup.host' => 'backup.example.com',
            'filesystems.disks.sftp-backup.username' => 'backup',
            'filesystems.disks.sftp-backup.hostFingerprint' => $fingerprint,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->expectExceptionMessage('Set SFTP_HOST_FINGERPRINT to the independently verified backup server fingerprint.');

        Storage::disk('sftp-backup');
    }

    #[Test]
    public function backup_disk_passes_the_configured_fingerprint_to_sftp(): void
    {
        config([
            'filesystems.disks.sftp-backup.host' => 'backup.example.com',
            'filesystems.disks.sftp-backup.username' => 'backup',
            'filesystems.disks.sftp-backup.hostFingerprint' => 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
        ]);

        $disk = Storage::disk('sftp-backup');

        $this->assertInstanceOf(SftpAdapter::class, $disk->getAdapter());

        $this->assertSame('SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', $disk->getConfig()['hostFingerprint']);
    }

    #[Test]
    public function it_forces_https_outside_local_and_testing_environments(): void
    {
        $this->app['env'] = 'production';

        $url = URL::spy();

        (new AppServiceProvider($this->app))->boot();

        $url->shouldHaveReceived('forceScheme')
            ->with('https')
            ->once();
    }

    #[Test]
    public function it_does_not_force_https_in_the_testing_environment(): void
    {
        $this->app['env'] = 'testing';

        $url = URL::spy();

        (new AppServiceProvider($this->app))->boot();

        $url->shouldNotHaveReceived('forceScheme');
    }
}
