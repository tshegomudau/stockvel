<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Core\Database;
use App\Core\TenantContext;
use App\Services\Notifications\Channels\MailChannel;
use App\Services\Notifications\Channels\SmsChannel;
use App\Services\Notifications\Channels\WhatsAppChannel;

final class NotificationQueueService
{
    public function __construct(
        private readonly Database $db,
        private readonly TenantContext $tenant,
        private readonly MailChannel $mail,
        private readonly SmsChannel $sms,
        private readonly WhatsAppChannel $whatsapp
    ) {
    }

    public function enqueue(
        string $channel,
        string $recipient,
        string $body,
        ?string $subject = null,
        ?array $payload = null,
        ?\DateTimeImmutable $scheduledFor = null
    ): int {
        $scheduled = ($scheduledFor ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        return (int) $this->db->insert(
            'INSERT INTO notification_jobs (stokvel_id, user_id, channel, recipient, subject, body, payload_json, status, attempt_count, max_attempts, scheduled_for, created_at, updated_at)
             VALUES (:stokvel_id, :user_id, :channel, :recipient, :subject, :body, :payload_json, :status, 0, :max_attempts, :scheduled_for, :created_at, :updated_at)',
            [
                'stokvel_id' => $this->tenant->stokvelId(),
                'user_id' => $this->tenant->userId(),
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'payload_json' => $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
                'status' => 'pending',
                'max_attempts' => (int) env('NOTIFICATION_MAX_ATTEMPTS', 5),
                'scheduled_for' => $scheduled,
                'created_at' => now_utc(),
                'updated_at' => now_utc(),
            ]
        );
    }

    public function processDue(int $limit = 50): int
    {
        $jobs = $this->db->fetchAll(
            'SELECT * FROM notification_jobs
             WHERE status IN (\'pending\', \'failed\')
             AND scheduled_for <= :now
             AND attempt_count < max_attempts
             ORDER BY scheduled_for ASC
             LIMIT ' . (int) $limit,
            ['now' => now_utc()]
        );

        $processed = 0;
        foreach ($jobs as $job) {
            if ($this->processJob($job)) {
                $processed++;
            }
        }

        return $processed;
    }

    /** @param array<string, mixed> $job */
    private function processJob(array $job): bool
    {
        $jobId = (int) $job['id'];
        $attempt = (int) $job['attempt_count'] + 1;

        try {
            $ok = match ((string) $job['channel']) {
                'mail' => $this->mail->send(
                    (string) $job['recipient'],
                    (string) ($job['subject'] ?? 'Notification'),
                    (string) $job['body'],
                    $this->decodePayload($job['payload_json'] ?? null)
                ),
                'sms' => $this->sms->send(
                    (string) $job['recipient'],
                    (string) $job['body'],
                    $this->decodePayload($job['payload_json'] ?? null)
                ),
                'whatsapp' => $this->whatsapp->send(
                    (string) $job['recipient'],
                    (string) $job['body'],
                    $this->decodePayload($job['payload_json'] ?? null)
                ),
                default => false,
            };

            if (!$ok) {
                throw new \RuntimeException('Channel returned failure.');
            }

            $this->db->exec(
                'UPDATE notification_jobs SET status = :status, attempt_count = :attempt_count, sent_at = :sent_at, updated_at = :updated_at, last_error = NULL
                 WHERE id = :id',
                [
                    'status' => 'sent',
                    'attempt_count' => $attempt,
                    'sent_at' => now_utc(),
                    'updated_at' => now_utc(),
                    'id' => $jobId,
                ]
            );

            return true;
        } catch (\Throwable $e) {
            $delay = $this->backoffSeconds($attempt);
            $next = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify("+{$delay} seconds")
                ->format('Y-m-d H:i:s');

            $this->db->exec(
                'UPDATE notification_jobs SET status = :status, attempt_count = :attempt_count, scheduled_for = :scheduled_for, last_error = :last_error, updated_at = :updated_at
                 WHERE id = :id',
                [
                    'status' => $attempt >= (int) $job['max_attempts'] ? 'dead' : 'failed',
                    'attempt_count' => $attempt,
                    'scheduled_for' => $next,
                    'last_error' => substr($e->getMessage(), 0, 2000),
                    'updated_at' => now_utc(),
                    'id' => $jobId,
                ]
            );

            return false;
        }
    }

    private function backoffSeconds(int $attempt): int
    {
        return min(3600, (int) (30 * (2 ** max(0, $attempt - 1))));
    }

    /** @return array<string, mixed> */
    private function decodePayload(mixed $payload): array
    {
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($payload) ? $payload : [];
    }
}
