<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

class UserSettings
{
    public function __construct(public readonly string $lang = 'en') {}

    public static function fromArray(array $data): self
    {
        return new self(
            lang: $data['lang'] ?? 'en',
        );
    }

    public function toArray(): array
    {
        return [
            'lang' => $this->lang,
        ];
    }
}
