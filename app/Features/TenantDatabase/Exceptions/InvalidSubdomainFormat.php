<?php

declare(strict_types=1);

namespace App\Features\TenantDatabase\Exceptions;

use App\Exceptions\AppException;

class InvalidSubdomainFormat extends AppException
{
    public function __construct(string $subdomain)
    {
        parent::__construct("Invalid subdomain format: '{$subdomain}'.");
    }
}
