<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Core\Database;
use App\Core\TenantContext;

final class NotificationService
{
    public function __construct(
        private readonly Database $db,
        private readonly TenantContext $tenant,
        private readonly NotificationQueueService $queue
    ) {
    }

    public function notifyInApp(
        int $userId,
        string $type,
        string $title,
        string $body,
        ?int $stokvelId = null,
        ?array $data = null
    ): int {
        $stokvelId = $stokvelId ?? $this->tenant->stokvelId();
        return (int) $this->db->insert(
            'INSERT INTO notifications (stokvel_id, user_id, channel, notification_type, title, body, data_json, status, created_at, updated_at)
             VALUES (:stokvel_id, :user_id, :channel, :notification_type, :title, :body, :data_json, :status, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'user_id' => $userId,
                'channel' => 'in_app',
                'notification_type' => $type,
                'title' => $title,
                'body' => $body,
                'data_json' => $data !== null ? json_encode($data, JSON_THROW_ON_ERROR) : null,
                'status' => 'unread',
                'created_at' => now_utc(),
                'updated_at' => now_utc(),
            ]
        );
    }

    public function queueMail(string $recipient, string $subject, string $body, ?array $payload = null): int
    {
        return $this->queue->enqueue('mail', $recipient, $body, $subject, $payload);
    }

    public function queueSms(string $recipient, string $body, ?array $payload = null): int
    {
        return $this->queue->enqueue('sms', $recipient, $body, null, $payload);
    }

    public function queueWhatsApp(string $recipient, string $body, ?array $payload = null): int
    {
        return $this->queue->enqueue('whatsapp', $recipient, $body, null, $payload);
    }
}
