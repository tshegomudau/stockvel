<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Core\Database;
use App\Core\Logger;
use App\Services\Finance\ChargeService;
use App\Services\Notifications\NotificationQueueService;

final class CronRunner
{
    /** @var array<string, resource> */
    private array $lockHandles = [];

    public function __construct(
        private readonly Database $db,
        private readonly Logger $logger,
        private readonly ChargeService $charges,
        private readonly NotificationQueueService $notifications,
        private readonly int $lockTtlSeconds
    ) {
    }

    public function runAll(): void
    {
        $this->runTask('charges:generate', fn () => $this->generateCharges());
        $this->runTask('charges:overdue', fn () => $this->markOverdue());
        $this->runTask('penalties:calculate', fn () => $this->calculatePenalties());
        $this->runTask('notifications:process', fn () => $this->notifications->processDue());
        $this->runTask('payouts:process', fn () => $this->processPayoutJobs());
        $this->runTask('tokens:expire', fn () => $this->expireTokens());
        $this->runTask('cleanup:sessions', fn () => $this->cleanupSessions());
        $this->runTask('jobs:detect-failed', fn () => $this->detectFailedJobs());
        $this->runTask('loans:instalments', fn () => $this->markLoanInstalmentsDue());
        $this->runTask('loans:arrears', fn () => $this->markLoanArrears());
    }

    public function runTask(string $name, callable $callback): mixed
    {
        if (!$this->acquireLock($name)) {
            $this->logger->info("Cron skipped (locked): {$name}");
            return null;
        }
        try {
            $this->logger->info("Cron started: {$name}");
            $result = $callback();
            $this->logger->info("Cron finished: {$name}", ['result' => $result]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error("Cron failed: {$name}", ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            $this->releaseLock($name);
        }
    }

    private function acquireLock(string $name): bool
    {
        $dir = dirname(__DIR__, 3) . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/cron-' . preg_replace('/[^a-z0-9:_-]/i', '_', $name) . '.lock';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return false;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        ftruncate($handle, 0);
        fwrite($handle, (gethostname() ?: 'cli') . ':' . getmypid() . '@' . now_utc());
        fflush($handle);
        $this->lockHandles[$name] = $handle;
        return true;
    }

    private function releaseLock(string $name): void
    {
        if (!isset($this->lockHandles[$name])) {
            return;
        }
        $handle = $this->lockHandles[$name];
        flock($handle, LOCK_UN);
        fclose($handle);
        unset($this->lockHandles[$name]);
    }

    private function generateCharges(): int
    {
        $period = (new \DateTimeImmutable('first day of this month', new \DateTimeZone('UTC')))->format('Y-m-d');
        $total = 0;
        foreach ($this->db->fetchAll("SELECT id FROM stokvels WHERE status = 'active'") as $row) {
            $created = $this->charges->generateRecurringCharges((int) $row['id'], $period);
            $total += count($created);
        }
        return $total;
    }

    private function markOverdue(): int
    {
        $total = 0;
        foreach ($this->db->fetchAll("SELECT id FROM stokvels WHERE status = 'active'") as $row) {
            $total += $this->charges->markOverdue((int) $row['id']);
        }
        return $total;
    }

    private function calculatePenalties(): int
    {
        $total = 0;
        foreach ($this->db->fetchAll("SELECT id FROM stokvels WHERE status = 'active'") as $row) {
            $total += $this->charges->calculatePenalties((int) $row['id']);
        }
        return $total;
    }

    private function processPayoutJobs(): int
    {
        $jobs = $this->db->fetchAll(
            "SELECT id FROM scheduled_jobs WHERE job_code = 'payouts:process' AND status = 'pending' AND scheduled_for <= :now LIMIT 50",
            ['now' => now_utc()]
        );
        foreach ($jobs as $job) {
            $this->db->exec(
                "UPDATE scheduled_jobs SET status = 'completed', completed_at = :t, updated_at = :u WHERE id = :id",
                ['t' => now_utc(), 'u' => now_utc(), 'id' => $job['id']]
            );
        }
        return count($jobs);
    }

    private function expireTokens(): int
    {
        $n = $this->db->exec(
            'DELETE FROM password_reset_tokens WHERE expires_at < :now OR used_at IS NOT NULL',
            ['now' => now_utc()]
        );
        $this->db->exec(
            'DELETE FROM email_verification_tokens WHERE expires_at < :now',
            ['now' => now_utc()]
        );
        return $n;
    }

    private function cleanupSessions(): int
    {
        $path = dirname(__DIR__, 3) . '/storage/sessions';
        if (!is_dir($path)) {
            return 0;
        }
        $removed = 0;
        $maxAge = (int) env('SESSION_LIFETIME', 120) * 60 * 2;
        foreach (glob($path . '/sess_*') ?: [] as $file) {
            if (filemtime($file) < time() - $maxAge) {
                @unlink($file);
                $removed++;
            }
        }
        return $removed;
    }

    private function detectFailedJobs(): int
    {
        $dead = $this->db->fetchAll(
            "SELECT id, channel, recipient, last_error FROM notification_jobs WHERE status = 'dead'"
        );
        foreach ($dead as $job) {
            $exists = $this->db->fetch(
                'SELECT id FROM scheduled_jobs WHERE job_code = :code AND payload_json LIKE :payload LIMIT 1',
                ['code' => 'notification:dead', 'payload' => '%"id":' . $job['id'] . '%']
            );
            if (!$exists) {
                $this->db->insert(
                    'INSERT INTO scheduled_jobs (stokvel_id, job_code, payload_json, status, scheduled_for, last_error, created_at, updated_at)
                     VALUES (NULL, :code, :payload, :status, :sched, :err, :c, :u)',
                    [
                        'code' => 'notification:dead',
                        'payload' => json_encode(['id' => $job['id'], 'channel' => $job['channel']]),
                        'status' => 'failed',
                        'sched' => now_utc(),
                        'err' => $job['last_error'] ?? 'dead letter',
                        'c' => now_utc(),
                        'u' => now_utc(),
                    ]
                );
            }
        }
        return count($dead);
    }

    private function markLoanInstalmentsDue(): int
    {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        return $this->db->exec(
            "UPDATE loan_schedules SET status = 'due', updated_at = :u
             WHERE status = 'pending' AND due_date <= :d",
            ['u' => now_utc(), 'd' => $today]
        );
    }

    private function markLoanArrears(): int
    {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        return $this->db->exec(
            "UPDATE loan_schedules SET status = 'overdue', updated_at = :u
             WHERE status = 'due' AND due_date < :d",
            ['u' => now_utc(), 'd' => $today]
        );
    }
}
