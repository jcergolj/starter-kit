<?php

declare(strict_types=1);

namespace App\Features\TenantDatabase\Exceptions;

use App\Exceptions\AppException;

class TenantDatabaseAlreadyExists extends AppException
{
    public function __construct(string $subdomain)
    {
        parent::__construct("Tenant database for subdomain '{$subdomain}' already exists.");
    }
}
