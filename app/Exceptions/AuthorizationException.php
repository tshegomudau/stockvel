<?php

declare(strict_types=1);

namespace App\Exceptions;

class AuthorizationException extends HttpException
{
    public function __construct(string $message = 'You are not authorized to perform this action.')
    {
        parent::__construct(403, $message);
    }
}
