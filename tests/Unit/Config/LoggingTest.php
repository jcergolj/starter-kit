<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoggingTest extends TestCase
{
    #[Test]
    public function the_default_stack_resolves_using_supported_channels(): void
    {
        $exampleEnvironment = parse_ini_file(base_path('.env.example'));

        $this->assertIsArray($exampleEnvironment);
        $stackChannels = explode(',', (string) $exampleEnvironment['LOG_STACK']);

        $this->assertSame(['single'], $stackChannels);
        $this->assertArrayNotHasKey('bugsnag', config('logging.channels'));

        config()->set('logging.channels.stack.channels', $stackChannels);
        Log::forgetChannel('stack');

        $this->assertInstanceOf(Logger::class, Log::channel('stack'));
    }
}
