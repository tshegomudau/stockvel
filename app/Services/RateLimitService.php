<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class RateLimitService
{
    private const DEFAULT_ACTION = 'http';

    public function __construct(
        private readonly Database $db
    ) {
    }

    public function attempt(string $bucket, int $maxAttempts, int $windowSeconds, string $actionCode = self::DEFAULT_ACTION): bool
    {
        $now = time();
        $row = $this->db->fetch(
            'SELECT * FROM rate_limits WHERE limit_key = :limit_key AND action_code = :action_code LIMIT 1',
            ['limit_key' => $bucket, 'action_code' => $actionCode]
        );

        if (!$row) {
            $this->insertRow($bucket, $actionCode, $now);
            return true;
        }

        $windowStart = strtotime((string) ($row['window_starts_at'] ?? $row['window_start'] ?? ''));
        if ($windowStart === false || ($now - $windowStart) >= $windowSeconds) {
            $this->resetRow($bucket, $actionCode, $now);
            return true;
        }

        $hits = (int) ($row['attempt_count'] ?? $row['hits'] ?? 0);
        if ($hits >= $maxAttempts) {
            return false;
        }

        $this->incrementRow($bucket, $actionCode);

        return true;
    }

    public function retryAfter(string $bucket, string $actionCode = self::DEFAULT_ACTION): int
    {
        $row = $this->db->fetch(
            'SELECT window_starts_at, window_start FROM rate_limits
             WHERE limit_key = :limit_key AND action_code = :action_code LIMIT 1',
            ['limit_key' => $bucket, 'action_code' => $actionCode]
        );
        if (!$row) {
            return 0;
        }

        $window = (int) env('RATE_LIMIT_WINDOW', 60);
        $windowStart = strtotime((string) ($row['window_starts_at'] ?? $row['window_start'] ?? ''));
        if ($windowStart === false) {
            return 0;
        }

        return max(0, ($windowStart + $window) - time());
    }

    public function clear(string $bucket, string $actionCode = self::DEFAULT_ACTION): void
    {
        $this->db->exec(
            'DELETE FROM rate_limits WHERE limit_key = :limit_key AND action_code = :action_code',
            ['limit_key' => $bucket, 'action_code' => $actionCode]
        );
    }

    private function insertRow(string $bucket, string $actionCode, int $now): void
    {
        try {
            $this->db->insert(
                'INSERT INTO rate_limits (limit_key, action_code, attempt_count, window_starts_at, created_at, updated_at)
                 VALUES (:limit_key, :action_code, 1, :window_starts_at, :created_at, :updated_at)',
                [
                    'limit_key' => $bucket,
                    'action_code' => $actionCode,
                    'window_starts_at' => gmdate('Y-m-d H:i:s', $now),
                    'created_at' => now_utc(),
                    'updated_at' => now_utc(),
                ]
            );
        } catch (\Throwable) {
            $this->db->insert(
                'INSERT INTO rate_limits (bucket_key, hits, window_start, created_at, updated_at)
                 VALUES (:bucket_key, 1, :window_start, :created_at, :updated_at)',
                [
                    'bucket_key' => $bucket,
                    'window_start' => gmdate('Y-m-d H:i:s', $now),
                    'created_at' => now_utc(),
                    'updated_at' => now_utc(),
                ]
            );
        }
    }

    private function resetRow(string $bucket, string $actionCode, int $now): void
    {
        try {
            $this->db->exec(
                'UPDATE rate_limits SET attempt_count = 1, window_starts_at = :window_starts_at, updated_at = :updated_at
                 WHERE limit_key = :limit_key AND action_code = :action_code',
                [
                    'window_starts_at' => gmdate('Y-m-d H:i:s', $now),
                    'updated_at' => now_utc(),
                    'limit_key' => $bucket,
                    'action_code' => $actionCode,
                ]
            );
        } catch (\Throwable) {
            $this->db->exec(
                'UPDATE rate_limits SET hits = 1, window_start = :window_start, updated_at = :updated_at
                 WHERE bucket_key = :bucket_key',
                [
                    'window_start' => gmdate('Y-m-d H:i:s', $now),
                    'updated_at' => now_utc(),
                    'bucket_key' => $bucket,
                ]
            );
        }
    }

    private function incrementRow(string $bucket, string $actionCode): void
    {
        try {
            $this->db->exec(
                'UPDATE rate_limits SET attempt_count = attempt_count + 1, updated_at = :updated_at
                 WHERE limit_key = :limit_key AND action_code = :action_code',
                ['updated_at' => now_utc(), 'limit_key' => $bucket, 'action_code' => $actionCode]
            );
        } catch (\Throwable) {
            $this->db->exec(
                'UPDATE rate_limits SET hits = hits + 1, updated_at = :updated_at WHERE bucket_key = :bucket_key',
                ['updated_at' => now_utc(), 'bucket_key' => $bucket]
            );
        }
    }
}
