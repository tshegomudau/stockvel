<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Core\Logger;

final class WhatsAppChannel
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    public function send(string $recipient, string $body, array $payload = []): bool
    {
        $this->logger->info('WhatsApp placeholder dispatch', [
            'recipient' => $recipient,
            'body' => $body,
            'payload' => $payload,
            'driver' => env('WHATSAPP_DRIVER', 'log'),
        ]);

        return true;
    }
}
