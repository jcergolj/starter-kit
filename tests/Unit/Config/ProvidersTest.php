<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProvidersTest extends TestCase
{
    #[Test]
    public function explicitly_registered_providers_are_available(): void
    {
        $providers = require dirname(__DIR__, 3).'/bootstrap/providers.php';

        foreach ($providers as $provider) {
            $this->assertTrue(
                is_subclass_of(
                    $provider,
                    ServiceProvider::class,
                ),
                "Provider [{$provider}] must be an available service provider.",
            );
        }
    }
}
