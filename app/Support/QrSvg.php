<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal QR Code (byte mode, ECC M) → SVG. Pure PHP, no external services.
 * Sufficient for otpauth:// URIs (~100 chars). Not a full QR library.
 */
final class QrSvg
{
    public static function render(string $text, int $moduleSize = 4, int $margin = 2): string
    {
        $matrix = self::encode($text);
        $n = count($matrix);
        $size = ($n + 2 * $margin) * $moduleSize;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" role="img" aria-label="QR code">';
        $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrix[$y][$x]) {
                    $px = ($x + $margin) * $moduleSize;
                    $py = ($y + $margin) * $moduleSize;
                    $svg .= '<rect x="' . $px . '" y="' . $py . '" width="' . $moduleSize . '" height="' . $moduleSize . '" fill="#111"/>';
                }
            }
        }
        return $svg . '</svg>';
    }

    /** @return list<list<bool>> */
    private static function encode(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $version = self::pickVersion(count($bytes));
        $size = 17 + 4 * $version;
        $dataCodewords = self::dataCapacity($version);
        $ecCodewords = self::eccCount($version);

        $bits = '0100'; // byte mode
        $bits .= str_pad(decbin(count($bytes)), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        $capacityBits = $dataCodewords * 8;
        $terminator = min(4, $capacityBits - strlen($bits));
        $bits .= str_repeat('0', max(0, $terminator));
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }
        $pad = ['11101100', '00010001'];
        $pi = 0;
        while (strlen($bits) < $capacityBits) {
            $bits .= $pad[$pi % 2];
            $pi++;
        }
        $bits = substr($bits, 0, $capacityBits);

        $data = [];
        foreach (str_split($bits, 8) as $byte) {
            $data[] = bindec($byte);
        }
        $ecc = self::reedSolomon($data, $ecCodewords);
        $codewords = array_merge($data, $ecc);

        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        self::placeFinders($matrix, $size);
        self::placeTiming($matrix, $size);
        self::placeAlignment($matrix, $version, $size);
        self::reserveFormat($matrix, $size);
        if ($version >= 7) {
            self::reserveVersion($matrix, $size);
        }
        self::placeData($matrix, $size, $codewords);
        $masked = self::applyMask($matrix, $size, 0);
        self::writeFormat($masked, $size, 0);

        $out = [];
        for ($y = 0; $y < $size; $y++) {
            $row = [];
            for ($x = 0; $x < $size; $x++) {
                $row[] = (bool) $masked[$y][$x];
            }
            $out[] = $row;
        }
        return $out;
    }

    private static function pickVersion(int $byteLen): int
    {
        // ECC level M capacities (bytes) for versions 1–6
        $caps = [1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84, 6 => 106];
        foreach ($caps as $v => $cap) {
            if ($byteLen <= $cap) {
                return $v;
            }
        }
        return 6;
    }

    private static function dataCapacity(int $version): int
    {
        $total = [1 => 26, 2 => 44, 3 => 70, 4 => 100, 5 => 134, 6 => 172];
        return $total[$version] - self::eccCount($version);
    }

    private static function eccCount(int $version): int
    {
        return [1 => 10, 2 => 16, 3 => 26, 4 => 36, 5 => 48, 6 => 64][$version];
    }

    /** @param list<int> $data */
    private static function reedSolomon(array $data, int $ecLen): array
    {
        $gfExp = [];
        $gfLog = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $gfExp[$i] = $x;
            $gfLog[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $gfExp[$i] = $gfExp[$i - 255];
        }

        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j] ^= $gen[$j];
                $next[$j + 1] ^= self::gfMul($gen[$j], $gfExp[$i], $gfExp, $gfLog);
            }
            $gen = $next;
        }

        $msg = array_merge($data, array_fill(0, $ecLen, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $msg[$i];
            if ($coef === 0) {
                continue;
            }
            for ($j = 0; $j < count($gen); $j++) {
                $msg[$i + $j] ^= self::gfMul($gen[$j], $coef, $gfExp, $gfLog);
            }
        }
        return array_slice($msg, count($data));
    }

    /** @param array<int,int> $gfExp @param array<int,int> $gfLog */
    private static function gfMul(int $a, int $b, array $gfExp, array $gfLog): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return $gfExp[($gfLog[$a] + $gfLog[$b]) % 255];
    }

    /** @param array<int, array<int, mixed>> $m */
    private static function placeFinders(array &$m, int $size): void
    {
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$row, $col]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $r = $row + $y;
                    $c = $col + $x;
                    if ($r < 0 || $c < 0 || $r >= $size || $c >= $size) {
                        continue;
                    }
                    $in = $x >= 0 && $x <= 6 && $y >= 0 && $y <= 6;
                    $dark = $in && (
                        $x === 0 || $x === 6 || $y === 0 || $y === 6
                        || ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4)
                    );
                    $m[$r][$c] = $dark;
                }
            }
        }
    }

    /** @param array<int, array<int, mixed>> $m */
    private static function placeTiming(array &$m, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            if ($m[6][$i] === null) {
                $m[6][$i] = $i % 2 === 0;
            }
            if ($m[$i][6] === null) {
                $m[$i][6] = $i % 2 === 0;
            }
        }
        $m[$size - 8][8] = true; // dark module
    }

    /** @param array<int, array<int, mixed>> $m */
    private static function placeAlignment(array &$m, int $version, int $size): void
    {
        if ($version < 2) {
            return;
        }
        $centers = [
            2 => [6, 18],
            3 => [6, 22],
            4 => [6, 26],
            5 => [6, 30],
            6 => [6, 34],
        ][$version] ?? [6, 18];
        foreach ($centers as $cy) {
            foreach ($centers as $cx) {
                if ($m[$cy][$cx] !== null) {
                    continue;
                }
                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $dark = abs($x) === 2 || abs($y) === 2 || ($x === 0 && $y === 0);
                        $m[$cy + $y][$cx + $x] = $dark;
                    }
                }
            }
        }
    }

    /** @param array<int, array<int, mixed>> $m */
    private static function reserveFormat(array &$m, int $size): void
    {
        for ($i = 0; $i < 9; $i++) {
            if ($m[8][$i] === null) {
                $m[8][$i] = false;
            }
            if ($m[$i][8] === null) {
                $m[$i][8] = false;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            if ($m[8][$size - 1 - $i] === null) {
                $m[8][$size - 1 - $i] = false;
            }
            if ($m[$size - 1 - $i][8] === null) {
                $m[$size - 1 - $i][8] = false;
            }
        }
    }

    /** @param array<int, array<int, mixed>> $m */
    private static function reserveVersion(array &$m, int $size): void
    {
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $m[$i][$size - 11 + $j] = false;
                $m[$size - 11 + $j][$i] = false;
            }
        }
    }

    /** @param array<int, array<int, mixed>> $m @param list<int> $codewords */
    private static function placeData(array &$m, int $size, array $codewords): void
    {
        $bits = '';
        foreach ($codewords as $cw) {
            $bits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        $bi = 0;
        $bitLen = strlen($bits);
        $up = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($i = 0; $i < $size; $i++) {
                $row = $up ? $size - 1 - $i : $i;
                foreach ([0, 1] as $c) {
                    $x = $col - $c;
                    if ($m[$row][$x] !== null) {
                        continue;
                    }
                    $m[$row][$x] = $bi < $bitLen ? $bits[$bi] === '1' : false;
                    $bi++;
                }
            }
            $up = !$up;
        }
    }

    /**
     * @param array<int, array<int, mixed>> $m
     * @return array<int, array<int, bool>>
     */
    private static function applyMask(array $m, int $size, int $mask): array
    {
        $out = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $bit = (bool) $m[$y][$x];
                $flip = ($x + $y) % 2 === 0; // mask 0
                if ($m[$y][$x] === null) {
                    $out[$y][$x] = false;
                } elseif (self::isFunction($m, $x, $y, $size)) {
                    $out[$y][$x] = $bit;
                } else {
                    $out[$y][$x] = $flip ? !$bit : $bit;
                }
            }
        }
        return $out;
    }

    /** @param array<int, array<int, mixed>> $m */
    private static function isFunction(array $m, int $x, int $y, int $size): bool
    {
        // Function patterns already filled before data; after placeData nulls are gone.
        // Use geometric checks instead.
        if ($y < 9 && $x < 9) {
            return true;
        }
        if ($y < 9 && $x >= $size - 8) {
            return true;
        }
        if ($y >= $size - 8 && $x < 9) {
            return true;
        }
        if ($y === 6 || $x === 6) {
            return true;
        }
        return false;
    }

    /** @param array<int, array<int, bool>> $m */
    private static function writeFormat(array &$m, int $size, int $mask): void
    {
        // ECC M (01) + mask pattern, BCH encoded
        $formatInfo = [
            0 => 0b101010000010010,
            1 => 0b101000100100101,
            2 => 0b101111001111100,
            3 => 0b101101101001011,
            4 => 0b100010111111001,
            5 => 0b100000011001110,
            6 => 0b100111110010111,
            7 => 0b100101010100000,
        ][$mask] ?? 0b101010000010010;

        $bits = str_pad(decbin($formatInfo), 15, '0', STR_PAD_LEFT);
        $positions = [
            [[0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8], [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0]],
            [[8, $size - 1], [8, $size - 2], [8, $size - 3], [8, $size - 4], [8, $size - 5], [8, $size - 6], [8, $size - 7],
                [$size - 8, 8], [$size - 7, 8], [$size - 6, 8], [$size - 5, 8], [$size - 4, 8], [$size - 3, 8], [$size - 2, 8], [$size - 1, 8]],
        ];
        foreach ($positions as $pos) {
            for ($i = 0; $i < 15; $i++) {
                [$r, $c] = $pos[$i];
                $m[$r][$c] = $bits[$i] === '1';
            }
        }
    }
}
