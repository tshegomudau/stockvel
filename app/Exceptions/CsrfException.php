<?php

declare(strict_types=1);

namespace App\Exceptions;

class CsrfException extends HttpException
{
    public function __construct(string $message = 'Invalid CSRF token.')
    {
        parent::__construct(419, $message);
    }
}
