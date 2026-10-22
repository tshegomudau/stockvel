<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/**
 * Platform-managed catalogue of stokvel types. Stokvels pick a code from this list.
 */
final class StokvelTypeCatalog
{
    /** @var array<string, string> */
    private const FALLBACK = [
        'general_savings' => 'Savings / contribution',
        'rotational' => 'Rotational (umgalelo)',
        'investment' => 'Investment / high budget',
        'burial_society' => 'Burial society',
        'grocery' => 'Grocery / commodity',
        'youth' => 'Youth / student',
        'stokvel_loan' => 'Lending circle',
    ];

    /** @return list<array{id?: int, code: string, name: string, description: ?string, sort_order: int, is_active: int}> */
    public static function all(?Database $db = null): array
    {
        $db ??= app()->container()->get(Database::class);
        try {
            $rows = $db->fetchAll(
                'SELECT id, code, name, description, sort_order, is_active
                 FROM stokvel_types
                 ORDER BY sort_order ASC, name ASC'
            );
            if ($rows !== []) {
                return $rows;
            }
        } catch (\Throwable) {
        }

        $out = [];
        $i = 10;
        foreach (self::FALLBACK as $code => $name) {
            $out[] = [
                'code' => $code,
                'name' => $name,
                'description' => null,
                'sort_order' => $i,
                'is_active' => 1,
            ];
            $i += 10;
        }

        return $out;
    }

    /** @return list<array{code: string, name: string, description: ?string}> */
    public static function activeOptions(?Database $db = null): array
    {
        $options = [];
        foreach (self::all($db) as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $options[] = [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'description' => $row['description'] ?? null,
            ];
        }

        return $options;
    }

    /** @return array<string, string> code => name */
    public static function labelMap(?Database $db = null): array
    {
        $map = [];
        foreach (self::all($db) as $row) {
            $map[(string) $row['code']] = (string) $row['name'];
        }

        return $map !== [] ? $map : self::FALLBACK;
    }

    public static function isValidCode(string $code, bool $activeOnly = true, ?Database $db = null): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }
        $db ??= app()->container()->get(Database::class);
        try {
            $row = $db->fetch(
                'SELECT code, is_active FROM stokvel_types WHERE code = :code LIMIT 1',
                ['code' => $code]
            );
            if ($row) {
                return !$activeOnly || !empty($row['is_active']);
            }
        } catch (\Throwable) {
        }

        return array_key_exists($code, self::FALLBACK);
    }

    public static function label(string $code, ?Database $db = null): string
    {
        $map = self::labelMap($db);

        return $map[$code] ?? $code;
    }
}
