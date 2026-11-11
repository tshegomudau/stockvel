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
        $this->runTask('cleanup:email', fn () => $this->runCleanupEmail()['removed']);
        $this->runTask('cleanup:logs', fn () => $this->runCleanupLogs()['removed']);
    }

    /**
     * Remove aged MAIL_DRIVER=log files under storage/mail.
     *
     * @return array{removed:int,scanned:int,failed:int,kept:int,days:int,cutoff:string,path:string}
     */
    public function runCleanupEmail(): array
    {
        return $this->cleanupEmailLog();
    }

    /**
     * Remove aged application/deploy logs under storage/logs.
     *
     * @return array{removed:int,scanned:int,failed:int,kept:int,days:int,cutoff:string,path:string}
     */
    public function runCleanupLogs(): array
    {
        return $this->cleanupLogs();
    }

    /** @return array{removed:int,scanned:int,failed:int,kept:int,days:int,cutoff:string,path:string} */
    public function runCleanupSessions(): array
    {
        return $this->cleanupSessions();
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
        fwrite($handle, $this->lockIdentity() . '@' . now_utc());
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
    /**
     * Host:pid (or safe fallbacks) for lock diagnostics.
     * getmypid/gethostname are often disabled on shared hosting.
     */
    private function lockIdentity(): string
    {
        $host = 'cli';
        if (\function_exists('gethostname')) {
            $resolved = @\gethostname();
            if (is_string($resolved) && $resolved !== '') {
                $host = $resolved;
            }
        } elseif (isset($_SERVER['SERVER_NAME']) && is_string($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== '') {
            $host = $_SERVER['SERVER_NAME'];
        }

        $pid = '0';
        if (\function_exists('getmypid')) {
            $resolvedPid = @\getmypid();
            if (is_int($resolvedPid) && $resolvedPid > 0) {
                $pid = (string) $resolvedPid;
            }
        } else {
            try {
                $pid = bin2hex(random_bytes(4));
            } catch (\Throwable) {
                $pid = str_replace('.', '', uniqid('', true));
            }
        }

        return $host . ':' . $pid;
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

    /** @return array{removed:int,scanned:int,failed:int,kept:int,days:int,cutoff:string,path:string} */
    private function cleanupSessions(): array
    {
        $path = $this->storagePath('sessions');
        $days = 0;
        $maxAge = (int) env('SESSION_LIFETIME', 120) * 60 * 2;
        $cutoffTs = time() - max(0, $maxAge);
        $stats = $this->emptyCleanupStats($path, $days, $cutoffTs);
        if (!is_dir($path)) {
            return $stats;
        }

        foreach (glob($path . '/sess_*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $stats['scanned']++;
            $mtime = (int) (filemtime($file) ?: 0);
            if ($mtime >= $cutoffTs) {
                $stats['kept']++;
                continue;
            }
            if (@unlink($file)) {
                $stats['removed']++;
            } else {
                $stats['failed']++;
                $this->logger->warning('cleanup:sessions failed to unlink', ['file' => $file]);
            }
        }

        return $stats;
    }

    /**
     * Remove aged .eml files written by MAIL_DRIVER=log under storage/mail.
     *
     * @return array{removed:int,scanned:int,failed:int,kept:int,days:int,cutoff:string,path:string}
     */
    private function cleanupEmailLog(): array
    {
        $path = $this->storagePath('mail');
        $days = $this->retentionDays('EMAIL_LOG_RETENTION_DAYS', 'LOG_RETENTION_DAYS', 7);
        $cutoffTs = $this->retentionCutoffTimestamp($days);
        $stats = $this->emptyCleanupStats($path, $days, $cutoffTs);
        if (!is_dir($path)) {
            return $stats;
        }

        foreach (glob($path . '/*.eml') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $stats['scanned']++;
            $name = basename($file);
            $mtime = (int) (filemtime($file) ?: 0);
            $ageAnchor = $this->mailFileAgeAnchor($name, $mtime);
            if ($ageAnchor >= $cutoffTs) {
                $stats['kept']++;
                continue;
            }
            if (@unlink($file)) {
                $stats['removed']++;
            } else {
                $stats['failed']++;
                $this->logger->warning('cleanup:email failed to unlink', ['file' => $file]);
            }
        }

        return $stats;
    }

    /**
     * Remove aged application/deploy/cron logs under storage/logs only.
     *
     * @return array{removed:int,scanned:int,failed:int,kept:int,days:int,cutoff:string,path:string}
     */
    private function cleanupLogs(): array
    {
        $path = $this->storagePath('logs');
        $days = $this->retentionDays('LOG_RETENTION_DAYS', 'DOCUMENT_RETENTION_DAYS', 14);
        $cutoffTs = $this->retentionCutoffTimestamp($days);
        $stats = $this->emptyCleanupStats($path, $days, $cutoffTs);
        if (!is_dir($path)) {
            return $stats;
        }

        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name === '.gitkeep') {
                continue;
            }
            $file = $path . DIRECTORY_SEPARATOR . $name;
            if (!is_file($file)) {
                continue;
            }
            $stats['scanned']++;
            $ageAnchor = $this->logFileAgeAnchor($name, (int) (filemtime($file) ?: 0));
            if ($ageAnchor >= $cutoffTs) {
                $stats['kept']++;
                continue;
            }
            if (@unlink($file)) {
                $stats['removed']++;
            } else {
                $stats['failed']++;
                $this->logger->warning('cleanup:logs failed to unlink', ['file' => $file]);
            }
        }

        return $stats;
    }

    private function storagePath(string $subdir = ''): string
    {
        try {
            $base = app()->basePath('storage');
        } catch (\Throwable) {
            $base = dirname(__DIR__, 3) . '/storage';
        }

        if ($subdir === '') {
            return $base;
        }

        return $base . '/' . trim($subdir, '/');
    }

    /** Keep the last N calendar days (app timezone). 0 = keep only today. */
    private function retentionDays(string $primary, string $fallback, int $default): int
    {
        $raw = env($primary);
        if ($raw === null || $raw === '') {
            $raw = env($fallback);
        }
        if ($raw === null || $raw === '') {
            $raw = $default;
        }

        return max(0, (int) $raw);
    }

    private function retentionCutoffTimestamp(int $days): int
    {
        $tz = new \DateTimeZone(app_timezone());
        $startOfToday = new \DateTimeImmutable('today', $tz);

        return $startOfToday->modify('-' . $days . ' days')->getTimestamp();
    }

    /**
     * @return array{removed:int,scanned:int,failed:int,kept:int,days:int,cutoff:string,path:string}
     */
    private function emptyCleanupStats(string $path, int $days, int $cutoffTs): array
    {
        return [
            'removed' => 0,
            'scanned' => 0,
            'failed' => 0,
            'kept' => 0,
            'days' => $days,
            'cutoff' => date('Y-m-d H:i:s', $cutoffTs),
            'path' => $path,
        ];
    }

    /** Prefer dated filename (app-YYYY-MM-DD / deploy-YYYYMMDD); else mtime. */
    private function logFileAgeAnchor(string $filename, int $mtime): int
    {
        if (preg_match('/^app-(\d{4})-(\d{2})-(\d{2})(?:\.|\z)/', $filename, $m) === 1) {
            return (int) (strtotime(sprintf('%s-%s-%s 23:59:59', $m[1], $m[2], $m[3])) ?: $mtime);
        }
        if (preg_match('/^deploy-(\d{4})(\d{2})(\d{2})(?:\.|\z)/', $filename, $m) === 1) {
            return (int) (strtotime(sprintf('%s-%s-%s 23:59:59', $m[1], $m[2], $m[3])) ?: $mtime);
        }

        return $mtime;
    }

    /** Prefer YYYY-MM-DD_ prefix on .eml files; else mtime. */
    private function mailFileAgeAnchor(string $filename, int $mtime): int
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[_.]/', $filename, $m) === 1) {
            return (int) (strtotime(sprintf('%s-%s-%s 23:59:59', $m[1], $m[2], $m[3])) ?: $mtime);
        }

        return $mtime;
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
