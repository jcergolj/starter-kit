<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\EmailAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(EmailAddress::class)]
class EmailAddressTest extends TestCase
{
    #[Test]
    public function normalize_trims_and_lowercases_an_email_address(): void
    {
        $this->assertSame(
            'mixed.case@example.com',
            EmailAddress::normalize('  Mixed.Case@Example.COM '),
        );
    }

    #[Test]
    public function normalize_returns_an_empty_string_for_null(): void
    {
        $this->assertSame('', EmailAddress::normalize(null));
    }
}
