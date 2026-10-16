<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

final class MailChannel
{
    public function send(string $recipient, string $subject, string $body, array $payload = []): bool
    {
        $driver = strtolower((string) env('MAIL_DRIVER', 'log'));
        if ($driver === 'log') {
            return $this->writeLog($recipient, $subject, $body, $payload);
        }

        return $this->sendSmtp($recipient, $subject, $body);
    }

    private function writeLog(string $recipient, string $subject, string $body, array $payload): bool
    {
        $dir = app()->basePath('storage/mail');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
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

    private function sendSmtp(string $recipient, string $subject, string $body): bool
    {
        $host = (string) env('MAIL_HOST', '');
        if ($host === '') {
            return $this->writeLog($recipient, $subject, $body, ['fallback' => 'missing SMTP host']);
        }

        $headers = [
            'From: ' . env('MAIL_FROM_NAME', 'stockvel') . ' <' . env('MAIL_FROM_ADDRESS', 'noreply@stockvel.local') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return mail($recipient, $subject, $body, implode("\r\n", $headers));
    }
}
