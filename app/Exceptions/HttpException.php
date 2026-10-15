<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class HttpException extends Exception
{
    public function __construct(private readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'HTTP Error', $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
