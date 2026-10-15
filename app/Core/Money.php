<?php

declare(strict_types=1);

namespace App\Core;

final class Money
{
    public static function normalize(float|int|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }
        if (function_exists('bcadd')) {
            return bcadd((string) $amount, '0', 2);
        }
        return number_format((float) $amount, 2, '.', '');
    }

    public static function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd(self::normalize($a), self::normalize($b), 2);
        }
        return self::normalize((float) $a + (float) $b);
    }

    public static function sub(string $a, string $b): string
    {
        if (function_exists('bcsub')) {
            return bcsub(self::normalize($a), self::normalize($b), 2);
        }
        return self::normalize((float) $a - (float) $b);
    }

    public static function mul(string $a, string $b): string
    {
        if (function_exists('bcmul')) {
            return bcmul(self::normalize($a), self::normalize($b), 2);
        }
        return self::normalize((float) $a * (float) $b);
    }

    public static function div(string $a, string $b): string
    {
        if (self::normalize($b) === '0.00') {
            throw new \InvalidArgumentException('Division by zero.');
        }
        if (function_exists('bcdiv')) {
            return bcdiv(self::normalize($a), self::normalize($b), 2);
        }
        return self::normalize((float) $a / (float) $b);
    }

    public static function cmp(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp(self::normalize($a), self::normalize($b), 2);
        }
        $x = (float) self::normalize($a);
        $y = (float) self::normalize($b);
        return $x <=> $y;
    }

    public static function isZero(string $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isPositive(string $a): bool
    {
        return self::cmp($a, '0') > 0;
    }
}
