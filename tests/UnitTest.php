<?php

declare(strict_types=1);

use App\Services\Auth\PasswordPolicyService;
use App\Services\Loans\LoanScheduleCalculator;
use App\Services\Platform\PlatformSettingsService;
use App\Support\MoneyMath;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('MoneyMath never uses floats for money', function (): void {
        $sum = MoneyMath::add('0.1', '0.2');
        test_assert($sum === '0.30' || $sum === '0.3' || MoneyMath::equals($sum, '0.30'), 'Expected decimal-safe 0.30, got ' . $sum);
        test_assert(MoneyMath::equals(MoneyMath::sub('10.00', '3.33'), '6.67'));
    }, 'unit');

    $runner->add('Flat interest loan schedule totals', function (): void {
        $calc = new LoanScheduleCalculator();
        $schedule = $calc->buildSchedule('1000.00', '10.00', 5, LoanScheduleCalculator::METHOD_FLAT, date('Y-m-d'));
        test_assert(is_array($schedule) && count($schedule) === 5, 'Expected 5 schedule rows');
        $totalPrincipal = '0.00';
        foreach ($schedule as $row) {
            $totalPrincipal = MoneyMath::add($totalPrincipal, (string) $row['principal']);
        }
        test_assert(MoneyMath::equals($totalPrincipal, '1000.00'), 'Principal schedule must sum to original');
    }, 'unit');

    $runner->add('PHP syntax of core finance classes', function (): void {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $canExec = function_exists('exec') && !in_array('exec', $disabled, true);

        foreach ([
            'app/Services/Finance/LedgerService.php',
            'app/Services/Finance/ContributionService.php',
            'app/Services/PermissionService.php',
        ] as $rel) {
            $path = dirname(__DIR__) . '/' . $rel;
            if ($canExec) {
                $out = [];
                $code = 0;
                exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $out, $code);
                test_assert($code === 0, implode("\n", $out));
                continue;
            }
            // Shared hosts often disable exec(); tokenize as a portable syntax smoke check.
            $src = (string) file_get_contents($path);
            test_assert($src !== '', 'Missing source: ' . $rel);
            $tokens = @token_get_all($src);
            test_assert(is_array($tokens) && $tokens !== [], 'Could not tokenize ' . $rel);
        }
    }, 'unit');

    $runner->add('Password policy validates length and character classes', function (): void {
        PlatformSettingsService::clearCache();
        /** @var PasswordPolicyService $policy */
        $policy = App\Core\App::getInstance()->container()->get(PasswordPolicyService::class);
        $weak = $policy->validate('short');
        test_assert($weak !== [], 'Short password must fail');
        $noUpper = $policy->validate('password1');
        test_assert($noUpper !== [], 'Missing uppercase must fail under defaults');
        $ok = $policy->validate('Password1');
        test_assert($ok === [], 'Password1 should pass default policy, got: ' . implode('; ', $ok));
        test_assert($policy->strengthScore('') === 0);
        test_assert($policy->strengthScore('Password1!') >= 3, 'Expected strong-ish score');
        $cfg = $policy->clientConfig();
        test_assert(($cfg['min_length'] ?? 0) >= 6);
        test_assert(is_array($cfg['labels'] ?? null) && $cfg['labels'] !== []);
    }, 'unit');

    $runner->add('Email datetime helper uses configured app timezone', function (): void {
        $prevEnv = $_ENV['APP_TIMEZONE'] ?? null;
        $_ENV['APP_TIMEZONE'] = 'Africa/Johannesburg';
        $_SERVER['APP_TIMEZONE'] = 'Africa/Johannesburg';
        putenv('APP_TIMEZONE=Africa/Johannesburg');

        // Stored timestamps are wall-clock APP_TIMEZONE values (not UTC).
        $formatted = sa_datetime_email('2026-11-08 12:00:00');
        test_assert(str_contains($formatted, '12:00'), 'Expected local wall-clock time, got: ' . $formatted);
        test_assert(str_contains($formatted, 'SAST') || str_contains($formatted, '+02'), 'Expected timezone label, got: ' . $formatted);
        test_assert(!str_contains($formatted, ' UTC)'), 'Email datetime must not show UTC label');

        $stamp = now_utc();
        $expected = (new DateTimeImmutable('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i');
        test_assert(str_starts_with($stamp, $expected), 'now_utc must use APP_TIMEZONE, got: ' . $stamp);

        if ($prevEnv === null) {
            unset($_ENV['APP_TIMEZONE'], $_SERVER['APP_TIMEZONE']);
            putenv('APP_TIMEZONE');
        } else {
            $_ENV['APP_TIMEZONE'] = $prevEnv;
            $_SERVER['APP_TIMEZONE'] = $prevEnv;
            putenv('APP_TIMEZONE=' . $prevEnv);
        }
    }, 'unit');
};
