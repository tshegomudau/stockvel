<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Support\MoneyMath;

/**
 * Pure loan schedule calculations (no database access).
 */
final class LoanScheduleCalculator
{
    public const METHOD_FLAT = 'flat';
    public const METHOD_REDUCING_BALANCE = 'reducing_balance';

    /**
     * @return list<array{
     *   instalment_number: int,
     *   due_date: string,
     *   opening_balance: string,
     *   principal: string,
     *   interest: string,
     *   fee: string,
     *   total_instalment: string,
     *   closing_balance: string
     * }>
     */
    public function buildSchedule(
        string $principal,
        string $annualInterestRatePercent,
        int $termMonths,
        string $method,
        string $startDate,
        string $instalmentFee = '0.00'
    ): array {
        $principal = MoneyMath::normalize($principal);
        if (!MoneyMath::isPositive($principal)) {
            throw new \InvalidArgumentException('Principal must be positive.');
        }
        if ($termMonths <= 0) {
            throw new \InvalidArgumentException('Term months must be positive.');
        }

        return match ($method) {
            self::METHOD_FLAT => $this->flatSchedule(
                $principal,
                $annualInterestRatePercent,
                $termMonths,
                $startDate,
                $instalmentFee
            ),
            self::METHOD_REDUCING_BALANCE => $this->reducingBalanceSchedule(
                $principal,
                $annualInterestRatePercent,
                $termMonths,
                $startDate,
                $instalmentFee
            ),
            default => throw new \InvalidArgumentException('Unsupported amortization method.'),
        };
    }

    public function calculateEarlySettlement(
        array $schedule,
        int $fromInstalmentNumber,
        string $penaltyPercent = '0.00'
    ): array {
        $remainingPrincipal = '0.00';
        $accruedInterest = '0.00';
        foreach ($schedule as $row) {
            if ((int) $row['instalment_number'] >= $fromInstalmentNumber) {
                $remainingPrincipal = MoneyMath::add($remainingPrincipal, $row['principal']);
                if ((int) $row['instalment_number'] === $fromInstalmentNumber) {
                    $accruedInterest = MoneyMath::add($accruedInterest, $row['interest']);
                }
            }
        }

        $penalty = MoneyMath::div(MoneyMath::mul($remainingPrincipal, $penaltyPercent), '100');
        $settlementTotal = MoneyMath::add(MoneyMath::add($remainingPrincipal, $accruedInterest), $penalty);

        return [
            'remaining_principal' => $remainingPrincipal,
            'accrued_interest' => $accruedInterest,
            'penalty' => $penalty,
            'settlement_total' => $settlementTotal,
        ];
    }

    private function flatSchedule(
        string $principal,
        string $annualRatePercent,
        int $termMonths,
        string $startDate,
        string $instalmentFee
    ): array {
        $totalInterest = MoneyMath::mul(
            MoneyMath::mul($principal, $annualRatePercent),
            MoneyMath::div((string) $termMonths, '12', 6)
        );
        $totalInterest = MoneyMath::div($totalInterest, '100');

        $principalPerMonth = MoneyMath::div($principal, (string) $termMonths);
        $interestPerMonth = MoneyMath::div($totalInterest, (string) $termMonths);
        $fee = MoneyMath::normalize($instalmentFee);
        $instalmentTotal = MoneyMath::add(MoneyMath::add($principalPerMonth, $interestPerMonth), $fee);

        $balance = $principal;
        $schedule = [];
        $due = new \DateTimeImmutable($startDate);

        for ($i = 1; $i <= $termMonths; ++$i) {
            $opening = $balance;
            $principalComponent = $i === $termMonths
                ? $balance
                : $principalPerMonth;
            $interestComponent = $interestPerMonth;
            $closing = MoneyMath::sub($opening, $principalComponent);
            if ($i === $termMonths) {
                $closing = '0.00';
            }

            $schedule[] = [
                'instalment_number' => $i,
                'due_date' => $due->format('Y-m-d'),
                'opening_balance' => MoneyMath::normalize($opening),
                'principal' => MoneyMath::normalize($principalComponent),
                'interest' => MoneyMath::normalize($interestComponent),
                'fee' => $fee,
                'total_instalment' => MoneyMath::normalize($instalmentTotal),
                'closing_balance' => MoneyMath::normalize($closing),
            ];

            $balance = $closing;
            $due = $due->modify('+1 month');
        }

        return $schedule;
    }

    private function reducingBalanceSchedule(
        string $principal,
        string $annualRatePercent,
        int $termMonths,
        string $startDate,
        string $instalmentFee
    ): array {
        $monthlyRate = MoneyMath::div(MoneyMath::div($annualRatePercent, '12', 8), '100', 8);
        $fee = MoneyMath::normalize($instalmentFee);
        $pmt = $this->calculatePmt($principal, $monthlyRate, $termMonths);

        $balance = $principal;
        $schedule = [];
        $due = new \DateTimeImmutable($startDate);

        for ($i = 1; $i <= $termMonths; ++$i) {
            $opening = $balance;
            $interest = MoneyMath::mul($opening, $monthlyRate);
            $principalComponent = MoneyMath::sub($pmt, $interest);
            if ($i === $termMonths || MoneyMath::comp($principalComponent, $opening) > 0) {
                $principalComponent = $opening;
            }
            $total = MoneyMath::add(MoneyMath::add($principalComponent, $interest), $fee);
            $closing = MoneyMath::sub($opening, $principalComponent);
            if (MoneyMath::comp($closing, '0') < 0) {
                $closing = '0.00';
            }

            $schedule[] = [
                'instalment_number' => $i,
                'due_date' => $due->format('Y-m-d'),
                'opening_balance' => MoneyMath::normalize($opening),
                'principal' => MoneyMath::normalize($principalComponent),
                'interest' => MoneyMath::normalize($interest),
                'fee' => $fee,
                'total_instalment' => MoneyMath::normalize($total),
                'closing_balance' => MoneyMath::normalize($closing),
            ];

            $balance = $closing;
            $due = $due->modify('+1 month');
        }

        return $schedule;
    }

    private function calculatePmt(string $principal, string $monthlyRate, int $termMonths): string
    {
        if (MoneyMath::isZero($monthlyRate)) {
            return MoneyMath::div($principal, (string) $termMonths);
        }

        $scale = 8;
        $onePlusR = function_exists('bcadd') ? bcadd('1', $monthlyRate, $scale) : (string) (1 + (float) $monthlyRate);
        $pow = '1';
        for ($i = 0; $i < $termMonths; ++$i) {
            $pow = function_exists('bcmul') ? bcmul($pow, $onePlusR, $scale) : (string) ((float) $pow * (float) $onePlusR);
        }

        $rateTimesPow = function_exists('bcmul') ? bcmul($monthlyRate, $pow, $scale) : (string) ((float) $monthlyRate * (float) $pow);
        $numerator = function_exists('bcmul') ? bcmul($principal, $rateTimesPow, $scale) : (string) ((float) $principal * (float) $rateTimesPow);
        $denominator = function_exists('bcsub') ? bcsub($pow, '1', $scale) : (string) ((float) $pow - 1);
        if (MoneyMath::isZero($denominator)) {
            return MoneyMath::div($principal, (string) $termMonths);
        }

        $pmt = function_exists('bcdiv') ? bcdiv($numerator, $denominator, MoneyMath::SCALE) : MoneyMath::div($numerator, $denominator);

        return MoneyMath::normalize($pmt);
    }
}
