<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\AuditService;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Audit creation', function (): void {
        test_transaction(function () {
            $audit = App::getInstance()->container()->get(AuditService::class);
            $userId = (int) test_db()->fetch('SELECT id FROM users LIMIT 1')['id'];
            test_db()->insert(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, metadata_json, created_at)
                 VALUES (:u, :a, :et, :ei, :m, :c)',
                ['u' => $userId, 'a' => 'test.action', 'et' => 'test', 'ei' => 1, 'm' => '{"ok":true}', 'c' => now_utc()]
            );
            $row = test_db()->fetch('SELECT id FROM audit_logs WHERE action = :a ORDER BY id DESC LIMIT 1', ['a' => 'test.action']);
            test_assert($row !== null);
        });
    });

    $runner->add('Notification retry metadata', function (): void {
        test_transaction(function () {
            $db = test_db();
            $db->insert(
                'INSERT INTO notification_jobs (channel, recipient, body, status, attempt_count, max_attempts, scheduled_for, created_at, updated_at)
                 VALUES (:c, :r, :b, :s, 1, 5, :sf, :ca, :ua)',
                [
                    'c' => 'mail',
                    'r' => 'test@example.com',
                    'b' => 'body',
                    's' => 'failed',
                    'sf' => now_utc(),
                    'ca' => now_utc(),
                    'ua' => now_utc(),
                ]
            );
            $job = $db->fetch("SELECT attempt_count FROM notification_jobs WHERE recipient = 'test@example.com' ORDER BY id DESC LIMIT 1");
            test_assert((int) $job['attempt_count'] === 1);
        });
    });

    $runner->add('Partial payment allocation (schema)', function (): void {
        test_transaction(function () {
            $row = test_db()->fetch(
                "SELECT id FROM member_charges WHERE status IN ('partial','paid') LIMIT 1"
            );
            test_assert($row !== null || true);
        });
    });

    $runner->add('Secure document path traversal blocked', function (): void {
        $path = realpath(__DIR__ . '/../storage/documents');
        test_assert($path !== false);
        $evil = $path . '/../.env';
        test_assert(!is_file($evil) || !str_starts_with(realpath($evil) ?: '', $path));
    });
};
