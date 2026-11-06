<?php

declare(strict_types=1);

/**
 * Platform-wide feature flags + storage settings (Backblaze, file uploads, WYSIWYG).
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS platform_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(128) NOT NULL,
    setting_value LONGTEXT NULL,
    updated_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_platform_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
ALTER TABLE document_versions
    ADD COLUMN storage_driver VARCHAR(32) NOT NULL DEFAULT 'local' AFTER storage_path
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS media_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    storage_driver VARCHAR(32) NOT NULL DEFAULT 'local',
    storage_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_media_files_stokvel (stokvel_id),
    CONSTRAINT fk_media_files_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS media_files',
        'ALTER TABLE document_versions DROP COLUMN storage_driver',
        'DROP TABLE IF EXISTS platform_settings',
    ],
];

