<?php

declare(strict_types=1);

namespace App\Exceptions;

class TenantException extends HttpException
{
    public function __construct(string $message = 'Tenant context is required.')
    {
        parent::__construct(403, $message);
    }
}
