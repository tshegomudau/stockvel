<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private const SENSITIVE = [
        'password', 'password_confirmation', 'token', 'secret', 'api_key',
        'bank_account', 'account_number', 'id_number', 'identity_number',
        'card_number', 'cvv', 'authorization', 'remember_token',
    ];

    public function __construct(private readonly string $logPath)
    {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0775, true);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function security(string $message, array $context = []): void
    {
        $this->write('SECURITY', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $entry = [
            'ts' => now_utc(),
            'level' => $level,
            'message' => $message,
            'request_id' => request_id(),
            'context' => $this->redact($context),
        ];
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($this->logPath . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    private function redact(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $lk = strtolower((string) $key);
            foreach (self::SENSITIVE as $needle) {
                if (str_contains($lk, $needle)) {
                    $out[$key] = '[REDACTED]';
                    continue 2;
                }
            }
            if (is_array($value)) {
                $out[$key] = $this->redact($value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
