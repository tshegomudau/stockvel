<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Notifications\Channels\MailChannel;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('MailChannel log driver writes eml file', function (): void {
        $prev = $_ENV['MAIL_DRIVER'] ?? null;
        $_ENV['MAIL_DRIVER'] = 'log';
        $_SERVER['MAIL_DRIVER'] = 'log';
        putenv('MAIL_DRIVER=log');

        $dir = App::getInstance()->basePath('storage/mail');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $before = glob($dir . '/*.eml') ?: [];

        /** @var MailChannel $mail */
        $mail = App::getInstance()->container()->get(MailChannel::class);
        $ok = $mail->send('member@example.co.za', 'Test subject', "Hello\nline 2", ['source' => 'MailChannelTest']);
        test_assert($ok === true, 'log send should succeed');

        $after = glob($dir . '/*.eml') ?: [];
        test_assert(count($after) > count($before), 'expected a new .eml under storage/mail');

        if ($prev === null) {
            unset($_ENV['MAIL_DRIVER'], $_SERVER['MAIL_DRIVER']);
            putenv('MAIL_DRIVER');
        } else {
            $_ENV['MAIL_DRIVER'] = $prev;
            $_SERVER['MAIL_DRIVER'] = $prev;
            putenv('MAIL_DRIVER=' . $prev);
        }
    }, 'unit');

    $runner->add('PHPMailer class is available after composer install', function (): void {
        test_assert(
            class_exists(\PHPMailer\PHPMailer\PHPMailer::class),
            'PHPMailer missing — run composer install'
        );
    }, 'unit');
};
