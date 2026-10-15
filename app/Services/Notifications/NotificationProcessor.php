<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * CLI/cron entry point — delegates to the queue service.
 */
final class NotificationProcessor
{
    public function __construct(
        private readonly NotificationQueueService $queue
    ) {
    }

    public function processBatch(int $limit = 50): int
    {
        return $this->queue->processDue($limit);
    }
}
