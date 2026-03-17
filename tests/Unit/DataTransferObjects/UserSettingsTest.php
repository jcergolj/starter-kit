<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects;

use App\DataTransferObjects\UserSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UserSettings::class)]
class UserSettingsTest extends TestCase
{
    #[Test]
    public function from_array_sets_lang(): void
    {
        $settings = UserSettings::fromArray(['lang' => 'sl']);

        $this->assertSame('sl', $settings->lang);
    }

    #[Test]
    public function from_array_defaults_lang_to_en(): void
    {
        $settings = UserSettings::fromArray([]);

        $this->assertSame('en', $settings->lang);
    }

    #[Test]
    public function to_array_returns_lang(): void
    {
        $settings = UserSettings::fromArray(['lang' => 'sl']);

        $this->assertSame(['lang' => 'sl'], $settings->toArray());
    }
}
