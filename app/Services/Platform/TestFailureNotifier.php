<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Services\Notifications\Channels\MailChannel;

/**
 * Emails support when php bin/console test:all reports failures.
 * Recipient: platform setting support_email, else SUPPORT_EMAIL / MAIL_REPLY_TO.
 * Temporarily applies production .env MAIL_* keys only (never DB_*) so alerts
 * are not trapped by the testing log driver.
 */
final class TestFailureNotifier
{
    /** @var list<string> */
    private const MAIL_ENV_KEYS = [
        'MAIL_DRIVER',
        'MAIL_FROM_ADDRESS',
        'MAIL_FROM_NAME',
        'MAIL_REPLY_TO',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'MAIL_ENCRYPTION',
        'MAIL_TIMEOUT',
        'MAIL_PORT_FALLBACKS',
        'MAIL_SMTP_OPTIONS_INSECURE',
        'SMTP2GO_USERNAME',
        'SMTP2GO_API_KEY',
        'SUPPORT_EMAIL',
    ];

    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly MailChannel $mail,
        private readonly string $basePath
    ) {
    }

    /**
     * @param array{
     *   suite:string,
     *   passed:int,
     *   failed:int,
     *   blocked:int,
     *   cases:list<array{name:string,status:string,error?:string}>,
     *   database?:string,
     *   finished_at?:string
     * } $report
     */
    public function notifyIfNeeded(array $report): bool
    {
        $failed = (int) ($report['failed'] ?? 0);
        $blocked = (int) ($report['blocked'] ?? 0);
        if ($failed === 0 && $blocked === 0) {
            return false;
        }

        $enabled = $this->settings->get('test_failure_email_enabled', true);
        $envEnabled = env('TEST_FAILURE_EMAIL_ENABLED', null);
        if ($envEnabled !== null && $envEnabled !== '') {
            // Explicit env false disables; otherwise platform setting wins.
            if (!filter_var($envEnabled, FILTER_VALIDATE_BOOL)) {
                return false;
            }
        }
        if (!filter_var($enabled, FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $recipient = $this->resolveSupportEmail();
        if ($recipient === null) {
            return false;
        }

        $appName = (string) env('APP_NAME', 'stockvel');
        $subject = sprintf(
            '[%s] test:all failed — %d failed, %d blocked',
            $appName,
            $failed,
            $blocked
        );
        $body = $this->buildBody($report, $appName);

        return $this->sendWithProductionMailConfig($recipient, $subject, $body, [
            'kind' => 'test_failure',
            'suite' => (string) ($report['suite'] ?? 'all'),
        ]);
    }

    public function resolveSupportEmail(): ?string
    {
        $fromFile = $this->readEnvFileValues($this->basePath . '/.env');
        $candidates = [
            trim((string) $this->settings->get('support_email', '')),
            trim((string) ($fromFile['SUPPORT_EMAIL'] ?? '')),
            trim((string) env('SUPPORT_EMAIL', '')),
            trim((string) ($fromFile['MAIL_REPLY_TO'] ?? '')),
            trim((string) env('MAIL_REPLY_TO', '')),
        ];
        foreach ($candidates as $email) {
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * @param array{
     *   suite:string,
     *   passed:int,
     *   failed:int,
     *   blocked:int,
     *   cases:list<array{name:string,status:string,error?:string}>,
     *   database?:string,
     *   finished_at?:string
     * } $report
     */
    private function buildBody(array $report, string $appName): string
    {
        $lines = [
            $appName . ' automated test suite reported failures.',
            '',
            'Suite: ' . (string) ($report['suite'] ?? 'all'),
            'Passed: ' . (int) ($report['passed'] ?? 0),
            'Failed: ' . (int) ($report['failed'] ?? 0),
            'Blocked: ' . (int) ($report['blocked'] ?? 0),
            'Database: ' . (string) ($report['database'] ?? 'n/a'),
            'Finished (UTC): ' . (string) ($report['finished_at'] ?? gmdate('c')),
            'Host: ' . (string) (gethostname() ?: 'unknown'),
            '',
            'Failed / blocked cases:',
        ];

        foreach ($report['cases'] ?? [] as $case) {
            $status = (string) ($case['status'] ?? '');
            if (!in_array($status, ['fail', 'blocked'], true)) {
                continue;
            }
            $name = (string) ($case['name'] ?? 'unnamed');
            $error = trim((string) ($case['error'] ?? ''));
            // Keep bodies short and free of secrets.
            if (strlen($error) > 500) {
                $error = substr($error, 0, 500) . '…';
            }
            $lines[] = sprintf('- [%s] %s%s', strtoupper($status), $name, $error !== '' ? ' — ' . $error : '');
        }

        $lines[] = '';
        $lines[] = 'Artifact: storage/test-artifacts/last-run.json';
        $lines[] = 'Re-run: php bin/console test:all --env=testing';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $payload */
    private function sendWithProductionMailConfig(
        string $recipient,
        string $subject,
        string $body,
        array $payload
    ): bool {
        // Integration tests set this to keep MAIL_DRIVER=log and avoid real SMTP.
        if (filter_var(env('TEST_FAILURE_SKIP_PRODUCTION_MAIL', false), FILTER_VALIDATE_BOOL)) {
            try {
                return $this->mail->send($recipient, $subject, $body, $payload);
            } catch (\Throwable) {
                return false;
            }
        }

        $productionMail = $this->readEnvFileValues($this->basePath . '/.env');
        $backup = [];
        foreach (self::MAIL_ENV_KEYS as $key) {
            $backup[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'putenv' => getenv($key),
            ];
            if (!array_key_exists($key, $productionMail)) {
                continue;
            }
            $value = $productionMail[$key];
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

        try {
            return $this->mail->send($recipient, $subject, $body, $payload);
        } catch (\Throwable) {
            return false;
        } finally {
            foreach ($backup as $key => $prev) {
                if ($prev['env'] === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $prev['env'];
                }
                if ($prev['server'] === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $prev['server'];
                }
                if ($prev['putenv'] === false) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $prev['putenv']);
                }
            }
        }
    }

    /** @return array<string, string> */
    private function readEnvFileValues(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
        $out = [];
        $allowed = array_fill_keys(self::MAIL_ENV_KEYS, true);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (!isset($allowed[$key])) {
                continue;
            }
            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
