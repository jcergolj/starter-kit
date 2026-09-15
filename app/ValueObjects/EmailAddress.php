<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Str;

class EmailAddress
{
    public static function normalize(?string $email): string
    {
        return Str::of($email ?? '')->trim()->lower()->toString();
    }
}
