<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Database;

/**
 * Platform-admin controlled feature flags and integration credentials.
 */
final class PlatformSettingsService
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'file_uploads_enabled' => false,
        'storage_driver' => 'local',
        'b2_key_id' => '',
        'b2_application_key' => '',
        'b2_bucket_id' => '',
        'b2_bucket_name' => '',
        'b2_endpoint' => '',
        'wysiwyg_full_enabled' => false,
        'wysiwyg_images_enabled' => false,
        'wysiwyg_tables_enabled' => false,
        'wysiwyg_colors_enabled' => false,
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $out = self::DEFAULTS;
        try {
            $rows = $this->db->fetchAll('SELECT setting_key, setting_value FROM platform_settings');
            foreach ($rows as $row) {
                $key = (string) ($row['setting_key'] ?? '');
                if ($key === '' || !array_key_exists($key, self::DEFAULTS)) {
                    continue;
                }
                $raw = $row['setting_value'];
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                if (json_last_error() === JSON_ERROR_NONE) {
                    $out[$key] = $decoded;
                } else {
                    $out[$key] = $raw;
                }
            }
        } catch (\Throwable) {
            // Table may not exist until migration 021 runs.
        }

        // Env fallbacks for B2 when DB empty (deploy convenience).
        if ($out['b2_key_id'] === '' && env('B2_KEY_ID')) {
            $out['b2_key_id'] = (string) env('B2_KEY_ID');
        }
        if ($out['b2_application_key'] === '' && env('B2_APPLICATION_KEY')) {
            $out['b2_application_key'] = (string) env('B2_APPLICATION_KEY');
        }
        if ($out['b2_bucket_id'] === '' && env('B2_BUCKET_ID')) {
            $out['b2_bucket_id'] = (string) env('B2_BUCKET_ID');
        }
        if ($out['b2_bucket_name'] === '' && env('B2_BUCKET_NAME')) {
            $out['b2_bucket_name'] = (string) env('B2_BUCKET_NAME');
        }
        if ($out['storage_driver'] === 'local' && env('STORAGE_DRIVER')) {
            $out['storage_driver'] = (string) env('STORAGE_DRIVER');
        }

        self::$cache = $out;
        return $out;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    public function enabled(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    /** @param array<string, mixed> $values */
    public function save(array $values): void
    {
        $now = now_utc();
        foreach (self::DEFAULTS as $key => $default) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            if (is_bool($default)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOL);
            } elseif (is_string($default)) {
                $value = trim((string) $value);
            }
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
            $existing = $this->db->fetch(
                'SELECT id FROM platform_settings WHERE setting_key = :k LIMIT 1',
                ['k' => $key]
            );
            if ($existing) {
                $this->db->exec(
                    'UPDATE platform_settings SET setting_value = :v, updated_at = :ua WHERE id = :id',
                    ['v' => $encoded, 'ua' => $now, 'id' => (int) $existing['id']]
                );
            } else {
                $this->db->insert(
                    'INSERT INTO platform_settings (setting_key, setting_value, created_at, updated_at)
                     VALUES (:k, :v, :ca, :ua)',
                    ['k' => $key, 'v' => $encoded, 'ca' => $now, 'ua' => $now]
                );
            }
        }
        self::$cache = null;
    }

    /** Front-end safe subset (no secrets). */
    /** @return array<string, mixed> */
    public function clientConfig(): array
    {
        $all = $this->all();
        return [
            'file_uploads_enabled' => (bool) $all['file_uploads_enabled'],
            'wysiwyg_full_enabled' => (bool) $all['wysiwyg_full_enabled'],
            'wysiwyg_images_enabled' => (bool) $all['wysiwyg_full_enabled'] && (bool) $all['wysiwyg_images_enabled'],
            'wysiwyg_tables_enabled' => (bool) $all['wysiwyg_full_enabled'] && (bool) $all['wysiwyg_tables_enabled'],
            'wysiwyg_colors_enabled' => (bool) $all['wysiwyg_full_enabled'] && (bool) $all['wysiwyg_colors_enabled'],
            'storage_driver' => (string) $all['storage_driver'],
        ];
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
