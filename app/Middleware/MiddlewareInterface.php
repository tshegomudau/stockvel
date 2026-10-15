<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed;
}
