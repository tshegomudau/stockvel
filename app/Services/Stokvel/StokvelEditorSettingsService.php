<?php

declare(strict_types=1);

namespace App\Services\Stokvel;

use App\Core\Database;
use App\Services\Platform\PlatformSettingsService;

/**
 * Per-stokvel editor/upload feature flags with fallback to platform defaults.
 */
final class StokvelEditorSettingsService
{
    /** @var list<string> */
    public const KEYS = [
        'file_uploads_enabled',
        'wysiwyg_full_enabled',
        'wysiwyg_images_enabled',
        'wysiwyg_tables_enabled',
        'wysiwyg_colors_enabled',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly PlatformSettingsService $platform,
    ) {
    }

    /** @return array<string, bool> */
    public function forStokvel(int $stokvelId): array
    {
        $platform = $this->platform->all();
        $out = [];
        foreach (self::KEYS as $key) {
            $out[$key] = (bool) ($platform[$key] ?? false);
        }

        $json = $this->settingsJson($stokvelId);
        $editor = is_array($json['editor'] ?? null) ? $json['editor'] : [];
        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $editor)) {
                $out[$key] = (bool) $editor[$key];
            }
        }

        // Images/tables/colours require full toolbar.
        if (!$out['wysiwyg_full_enabled']) {
            $out['wysiwyg_images_enabled'] = false;
            $out['wysiwyg_tables_enabled'] = false;
            $out['wysiwyg_colors_enabled'] = false;
        }

        return $out;
    }

    /** Front-end safe subset. */
    /** @return array<string, mixed> */
    public function clientConfig(int $stokvelId): array
    {
        $all = $this->forStokvel($stokvelId);
        $driver = (string) $this->platform->get('storage_driver', 'local');

        return [
            'file_uploads_enabled' => $all['file_uploads_enabled'],
            'wysiwyg_full_enabled' => $all['wysiwyg_full_enabled'],
            'wysiwyg_images_enabled' => $all['wysiwyg_full_enabled'] && $all['wysiwyg_images_enabled'],
            'wysiwyg_tables_enabled' => $all['wysiwyg_full_enabled'] && $all['wysiwyg_tables_enabled'],
            'wysiwyg_colors_enabled' => $all['wysiwyg_full_enabled'] && $all['wysiwyg_colors_enabled'],
            'storage_driver' => $driver,
        ];
    }

    public function enabled(int $stokvelId, string $key): bool
    {
        $all = $this->forStokvel($stokvelId);
        return (bool) ($all[$key] ?? false);
    }

    /** @param array<string, mixed> $values */
    public function save(int $stokvelId, array $values): void
    {
        $json = $this->settingsJson($stokvelId);
        $editor = is_array($json['editor'] ?? null) ? $json['editor'] : [];
        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $editor[$key] = filter_var($values[$key], FILTER_VALIDATE_BOOL);
        }
        if (empty($editor['wysiwyg_full_enabled'])) {
            $editor['wysiwyg_images_enabled'] = false;
            $editor['wysiwyg_tables_enabled'] = false;
            $editor['wysiwyg_colors_enabled'] = false;
        }
        $json['editor'] = $editor;
        $encoded = json_encode($json, JSON_THROW_ON_ERROR);
        $now = now_utc();
        $existing = $this->db->fetch(
            'SELECT stokvel_id FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        if ($existing) {
            $this->db->exec(
                'UPDATE stokvel_settings SET settings_json = :j, updated_at = :ua WHERE stokvel_id = :sid',
                ['j' => $encoded, 'ua' => $now, 'sid' => $stokvelId]
            );
            return;
        }
        $this->db->insert(
            'INSERT INTO stokvel_settings (stokvel_id, settings_json, created_at, updated_at)
             VALUES (:sid, :j, :ca, :ua)',
            ['sid' => $stokvelId, 'j' => $encoded, 'ca' => $now, 'ua' => $now]
        );
    }

    /** @return array<string, mixed> */
    private function settingsJson(int $stokvelId): array
    {
        try {
            $row = $this->db->fetch(
                'SELECT settings_json FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
                ['sid' => $stokvelId]
            );
            $decoded = json_decode((string) ($row['settings_json'] ?? '{}'), true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
