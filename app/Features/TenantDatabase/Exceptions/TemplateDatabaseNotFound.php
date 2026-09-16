<?php

declare(strict_types=1);

namespace App\Features\TenantDatabase\Exceptions;

use App\Exceptions\AppException;

class TemplateDatabaseNotFound extends AppException
{
    public function __construct()
    {
        parent::__construct('Template database not found. Please run migrations first.');
    }
}
