<?php

declare(strict_types=1);

namespace App\Support;

final class MoneyMath
{
    public const SCALE = 2;

    public static function normalize(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return self::format('0');
        }

        $value = is_string($amount) ? trim($amount) : (string) $amount;
        if ($value === '' || !is_numeric($value)) {
            throw new \InvalidArgumentException('Invalid monetary amount.');
        }

        return self::format($value);
    }

    public static function add(string $a, string $b): string
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if (function_exists('bcadd')) {
            return bcadd($a, $b, self::SCALE);
        }

        return self::format((float) $a + (float) $b);
    }

    public static function sub(string $a, string $b): string
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if (function_exists('bcsub')) {
            return bcsub($a, $b, self::SCALE);
        }

        return self::format((float) $a - (float) $b);
    }

    public static function mul(string $a, string $b, int $scale = self::SCALE): string
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if (function_exists('bcmul')) {
            return bcmul($a, $b, $scale);
        }

        return self::format(round((float) $a * (float) $b, $scale));
    }

    public static function div(string $a, string $b, int $scale = self::SCALE): string
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if (self::comp($b, '0') === 0) {
            throw new \InvalidArgumentException('Division by zero.');
        }
        if (function_exists('bcdiv')) {
            return bcdiv($a, $b, $scale);
        }

        return self::format(round((float) $a / (float) $b, $scale));
    }

    public static function comp(string $a, string $b): int
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if (function_exists('bccomp')) {
            return bccomp($a, $b, self::SCALE);
        }

        $fa = (float) $a;
        $fb = (float) $b;
        return $fa <=> $fb;
    }

    public static function equals(string $a, string $b): bool
    {
        return self::comp($a, $b) === 0;
    }

    public static function isZero(string $a): bool
    {
        return self::comp($a, '0') === 0;
    }

    public static function isPositive(string $a): bool
    {
        return self::comp($a, '0') > 0;
    }

    public static function min(string $a, string $b): string
    {
        return self::comp($a, $b) <= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function max(string $a, string $b): string
    {
        return self::comp($a, $b) >= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function sum(array $amounts): string
    {
        $total = '0.00';
        foreach ($amounts as $amount) {
            $total = self::add($total, (string) $amount);
        }

        return $total;
    }

    private static function format(string|float $value): string
    {
        return number_format((float) $value, self::SCALE, '.', '');
    }
}
