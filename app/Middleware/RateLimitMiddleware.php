<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Exceptions\HttpException;
use App\Services\RateLimitService;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimitService $rateLimit,
        private readonly int $maxAttempts = 0,
        private readonly int $windowSeconds = 0,
        private readonly string $bucketPrefix = 'http'
    ) {
    }

    public function handle(Request $request, callable $next): mixed
    {
        $max = $this->maxAttempts > 0 ? $this->maxAttempts : (int) env('RATE_LIMIT_MAX', 120);
        $window = $this->windowSeconds > 0 ? $this->windowSeconds : (int) env('RATE_LIMIT_WINDOW', 60);

        $bucket = $this->bucketPrefix . ':' . sha1($request->ip() . '|' . $request->method() . '|' . $request->path());

        if (!$this->rateLimit->attempt($bucket, $max, $window)) {
            $retryAfter = $this->rateLimit->retryAfter($bucket);
            if ($retryAfter > 0) {
                header('Retry-After: ' . $retryAfter);
            }
            throw new HttpException(429, 'Too many requests. Please try again later.');
        }

        return $next($request);
    }
}
