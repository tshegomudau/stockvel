<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catalogue of optional stokvel feature modules and the permission prefixes they gate.
 */
final class ModuleRegistry
{
    /** @var array<string, array{label: string, permission_prefixes: list<string>}> */
    public const MODULES = [
        'loans' => [
            'label' => 'Loans',
            'permission_prefixes' => ['loans.'],
        ],
        'burial' => [
            'label' => 'Burial claims',
            'permission_prefixes' => ['burial.'],
        ],
        'grocery' => [
            'label' => 'Grocery cycles',
            'permission_prefixes' => ['grocery.'],
        ],
        'meetings' => [
            'label' => 'Meetings',
            'permission_prefixes' => ['meetings.'],
        ],
        'rules' => [
            'label' => 'Rules',
            'permission_prefixes' => ['rules.'],
        ],
        'documents' => [
            'label' => 'Documents',
            'permission_prefixes' => ['documents.'],
        ],
        'expenses' => [
            'label' => 'Expenses',
            'permission_prefixes' => ['expenses.'],
        ],
        'payouts' => [
            'label' => 'Payouts',
            'permission_prefixes' => ['payouts.'],
        ],
        'approvals' => [
            'label' => 'Approvals',
            'permission_prefixes' => ['approvals.'],
        ],
        'reports' => [
            'label' => 'Reports',
            'permission_prefixes' => ['reports.'],
        ],
        'support' => [
            'label' => 'Support',
            'permission_prefixes' => ['support.'],
        ],
    ];

    /** @return array<string, array{label: string, permission_prefixes: list<string>}> */
    public static function all(): array
    {
        return self::MODULES;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::MODULES);
    }

    /**
     * Default enabled_modules map (all on except loans when column is off).
     *
     * @return array<string, bool>
     */
    public static function defaults(bool $loansEnabled = true): array
    {
        $out = [];
        foreach (self::keys() as $key) {
            $out[$key] = $key === 'loans' ? $loansEnabled : true;
        }
        return $out;
    }

    public static function moduleForPermission(string $permission): ?string
    {
        foreach (self::MODULES as $key => $meta) {
            foreach ($meta['permission_prefixes'] as $prefix) {
                if (str_starts_with($permission, $prefix) || $permission === rtrim($prefix, '.')) {
                    return $key;
                }
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $enabledModules
     */
    public static function isEnabled(array $enabledModules, string $moduleKey): bool
    {
        if (!isset(self::MODULES[$moduleKey])) {
            return true;
        }
        if (!array_key_exists($moduleKey, $enabledModules)) {
            return true;
        }
        $value = $enabledModules[$moduleKey];
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $value;
    }

    /**
     * Whether a permission is allowed given the current module flags.
     * Permissions not tied to a module always pass this gate.
     *
     * @param array<string, mixed> $enabledModules
     */
    public static function permissionAllowed(string $permission, array $enabledModules): bool
    {
        $module = self::moduleForPermission($permission);
        if ($module === null) {
            return true;
        }
        return self::isEnabled($enabledModules, $module);
    }

    /**
     * Normalise a decoded settings_json enabled_modules value.
     *
     * @param mixed $raw
     * @return array<string, bool>
     */
    public static function normalize(mixed $raw, bool $loansEnabledFallback = true): array
    {
        $defaults = self::defaults($loansEnabledFallback);
        if (!is_array($raw)) {
            return $defaults;
        }
        $out = $defaults;
        foreach (self::keys() as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = self::isEnabled($raw, $key);
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed>|null $settingsJson
     * @return array<string, bool>
     */
    public static function fromSettings(?array $settingsJson, bool $loansEnabledFallback = false): array
    {
        $raw = is_array($settingsJson) ? ($settingsJson['enabled_modules'] ?? null) : null;

        return self::normalize($raw, $loansEnabledFallback);
    }

    public static function moduleForNavKey(string $navKey): ?string
    {
        return match ($navKey) {
            'loans', 'loan-products' => 'loans',
            'burial' => 'burial',
            'grocery' => 'grocery',
            'meetings' => 'meetings',
            'rules' => 'rules',
            'documents', 'document-categories' => 'documents',
            'expenses', 'expense-categories' => 'expenses',
            'payouts', 'payout-categories' => 'payouts',
            'approvals' => 'approvals',
            'reports' => 'reports',
            'support' => 'support',
            default => null,
        };
    }
}
