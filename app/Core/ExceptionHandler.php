<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use Throwable;

final class ExceptionHandler
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function handle(Throwable $e): never
    {
        $status = $e instanceof HttpException ? $e->getStatus() : 500;
        if ($status >= 500) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } else {
            $this->logger->warning($e->getMessage(), ['status' => $status]);
        }

        $debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL);
        $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');

        if ($wantsJson) {
            Response::json([
                'error' => $e->getMessage(),
                'status' => $status,
                'debug' => $debug ? [
                    'exception' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], $status)->send();
        }

        $message = $status >= 500 && !$debug ? 'An unexpected error occurred.' : $e->getMessage();
        try {
            $html = View::render('errors/error', [
                'status' => $status,
                'message' => $message,
                'debug' => $debug ? $e : null,
                'title' => 'Error ' . $status,
            ], 'layouts/guest');
        } catch (Throwable) {
            $html = '<h1>' . e((string) $status) . '</h1><p>' . e($message) . '</p>';
        }
        Response::html($html, $status)->send();
    }
}
