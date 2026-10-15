<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Core\Logger;

final class SmsChannel
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    public function send(string $recipient, string $body, array $payload = []): bool
    {
        $driver = strtolower((string) env('SMS_DRIVER', 'log'));
        if ($driver !== 'log') {
            $this->logger->info('SMS provider dispatch placeholder', [
                'recipient' => $recipient,
                'length' => strlen($body),
            ]);
            return true;
        }

        $this->logger->info('SMS (log driver)', [
            'recipient' => $recipient,
            'body' => $body,
            'payload' => $payload,
        ]);

        return true;
    }
}
