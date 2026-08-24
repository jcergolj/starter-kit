<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AppServiceProvider::class)]
class AppServiceProviderTest extends TestCase
{
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
