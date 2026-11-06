<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Core\Logger;
use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Outbound mail: log driver (dev) or PHPMailer SMTP (production / SMTP2GO).
 *
 * Drivers:
 * - log     → write .eml under storage/mail
 * - smtp    → PHPMailer using MAIL_HOST / MAIL_PORT / …
 * - smtp2go → PHPMailer with SMTP2GO defaults (mail.smtp2go.com:2525 TLS)
 */
final class MailChannel
{
    public function __construct(private readonly ?Logger $logger = null)
    {
    }

    public function send(string $recipient, string $subject, string $body, array $payload = []): bool
    {
        $driver = strtolower(trim((string) env('MAIL_DRIVER', 'log')));
        if ($driver === '' || $driver === 'log') {
            return $this->writeLog($recipient, $subject, $body, $payload);
        }

        if (!class_exists(PHPMailer::class)) {
            $this->log('error', 'PHPMailer missing — run composer install; falling back to log driver.');
            return $this->writeLog($recipient, $subject, $body, array_merge($payload, [
                'fallback' => 'phpmailer_missing',
            ]));
        }

        if (in_array($driver, ['smtp', 'smtp2go', 'mail'], true)) {
            return $this->sendSmtp($recipient, $subject, $body, $payload, $driver === 'smtp2go');
        }

        $this->log('warning', 'Unknown MAIL_DRIVER "' . $driver . '"; using log driver.');
        return $this->writeLog($recipient, $subject, $body, $payload);
    }

    private function writeLog(string $recipient, string $subject, string $body, array $payload): bool
    {
        $dir = dirname(__DIR__, 4) . '/storage/mail';
        if (function_exists('app')) {
            try {
                $dir = app()->basePath('storage/mail');
            } catch (\Throwable) {
                // Installer / early boot may not have App yet.
            }
        }
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $file = $dir . '/' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.eml';
        $content = implode("\n", [
            'To: ' . $recipient,
            'Subject: ' . $subject,
            'Date: ' . gmdate('D, d M Y H:i:s') . ' GMT',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            $body,
            '',
            '-- payload --',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);

        return file_put_contents($file, $content) !== false;
    }

    private function sendSmtp(
        string $recipient,
        string $subject,
        string $body,
        array $payload,
        bool $smtp2goDefaults
    ): bool {
        $config = $this->resolveSmtpConfig($smtp2goDefaults);
        if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '') {
            $this->log('error', 'SMTP incomplete (host/username/password). Falling back to log.');
            return $this->writeLog($recipient, $subject, $body, array_merge($payload, [
                'fallback' => 'incomplete_smtp_config',
            ]));
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = $config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Timeout = (int) env('MAIL_TIMEOUT', 30);

            match ($config['encryption']) {
                'ssl' => $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS,
                'tls', 'starttls' => $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS,
                default => $mail->SMTPSecure = '',
            };
            if ($config['encryption'] === '' || $config['encryption'] === 'none') {
                $mail->SMTPAutoTLS = false;
            }

            // Shared hosts sometimes need this when OpenSSL CA bundle is incomplete.
            if (filter_var(env('MAIL_SMTP_OPTIONS_INSECURE', false), FILTER_VALIDATE_BOOL)) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            $fromAddress = $config['from_address'];
            $fromName = $config['from_name'];
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($recipient);

            $replyTo = trim((string) env('MAIL_REPLY_TO', ''));
            if ($replyTo !== '') {
                $mail->addReplyTo($replyTo, $fromName);
            }

            $mail->Subject = $subject;
            $isHtml = !empty($payload['html']) || !empty($payload['is_html']);
            if ($isHtml) {
                $mail->isHTML(true);
                $mail->Body = (string) ($payload['html'] ?? $body);
                $mail->AltBody = strip_tags((string) ($payload['text'] ?? $body));
            } else {
                $mail->isHTML(false);
                $mail->Body = $body;
            }

            $mail->send();
            return true;
        } catch (PhpMailerException $e) {
            $this->log('error', 'PHPMailer SMTP failed: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->log('error', 'Mail send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return array{
     *   host: string,
     *   port: int,
     *   username: string,
     *   password: string,
     *   encryption: string,
     *   from_address: string,
     *   from_name: string
     * }
     */
    private function resolveSmtpConfig(bool $smtp2goDefaults): array
    {
        $host = trim((string) env('MAIL_HOST', ''));
        $port = (int) env('MAIL_PORT', 0);
        $encryption = strtolower(trim((string) env('MAIL_ENCRYPTION', '')));
        $username = trim((string) env('MAIL_USERNAME', ''));
        $password = (string) env('MAIL_PASSWORD', '');

        if ($smtp2goDefaults) {
            if ($host === '') {
                $host = 'mail.smtp2go.com';
            }
            if ($port < 1) {
                $port = 2525;
            }
            if ($encryption === '') {
                $encryption = 'tls';
            }
            // SMTP2GO often uses the API key as the SMTP password with a dedicated username.
            if ($password === '' && env('SMTP2GO_API_KEY')) {
                $password = (string) env('SMTP2GO_API_KEY');
            }
            if ($username === '' && env('SMTP2GO_USERNAME')) {
                $username = (string) env('SMTP2GO_USERNAME');
            }
        }

        if ($port < 1) {
            $port = $encryption === 'ssl' ? 465 : 587;
        }
        if ($encryption === '' && in_array($port, [465, 8465, 443], true)) {
            $encryption = 'ssl';
        }
        if ($encryption === '' && in_array($port, [587, 2525, 8025, 80], true)) {
            $encryption = 'tls';
        }

        return [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'from_address' => trim((string) env('MAIL_FROM_ADDRESS', 'noreply@stockvel.local')),
            'from_name' => trim((string) env('MAIL_FROM_NAME', 'stockvel')),
        ];
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger === null) {
            return;
        }
        match ($level) {
            'error' => $this->logger->error($message),
            'warning' => $this->logger->warning($message),
            default => $this->logger->info($message),
        };
    }
}
