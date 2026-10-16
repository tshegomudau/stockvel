<?php

declare(strict_types=1);

use App\Services\Loans\LoanScheduleCalculator;
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
        foreach ([
            'app/Services/Finance/LedgerService.php',
            'app/Services/Finance/ContributionService.php',
            'app/Services/PermissionService.php',
        ] as $rel) {
            $path = dirname(__DIR__) . '/' . $rel;
            $out = [];
            $code = 0;
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $out, $code);
            test_assert($code === 0, implode("\n", $out));
        }
    }, 'unit');
};
