<?php

declare(strict_types=1);

/**
 * Migration 012 — Optional MFA (TOTP / passkeys), platform module flags,
 * and fee / meeting / rule category catalogues.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS user_totp (
    user_id BIGINT UNSIGNED NOT NULL,
    secret_encrypted TEXT NOT NULL,
    enabled_at DATETIME NULL,
    recovery_codes_json TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_user_totp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS user_webauthn_credentials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    credential_id VARCHAR(512) NOT NULL,
    public_key TEXT NOT NULL,
    sign_count INT UNSIGNED NOT NULL DEFAULT 0,
    label VARCHAR(128) NULL,
    created_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_webauthn_credential_id (credential_id),
    KEY idx_webauthn_user (user_id),
    CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS fee_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fee_categories_stokvel_code (stokvel_id, code),
    KEY idx_fee_categories_stokvel_active (stokvel_id, is_active),
    CONSTRAINT fk_fee_categories_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS meeting_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_meeting_categories_stokvel_code (stokvel_id, code),
    KEY idx_meeting_categories_stokvel_active (stokvel_id, is_active),
    CONSTRAINT fk_meeting_categories_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS rule_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_rule_categories_stokvel_code (stokvel_id, code),
    KEY idx_rule_categories_stokvel_active (stokvel_id, is_active),
    CONSTRAINT fk_rule_categories_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        // Default module flags for every stokvel (idempotent merge into settings_json)
        <<<'SQL'
UPDATE stokvel_settings
SET settings_json = JSON_SET(
    COALESCE(NULLIF(settings_json, ''), '{}'),
    '$.enabled_modules',
    JSON_OBJECT(
        'loans', IF(loans_enabled = 1, true, false),
        'burial', true,
        'grocery', true,
        'meetings', true,
        'rules', true,
        'documents', true,
        'expenses', true,
        'payouts', true,
        'approvals', true,
        'reports', true,
        'support', true
    )
)
WHERE JSON_EXTRACT(COALESCE(NULLIF(settings_json, ''), '{}'), '$.enabled_modules') IS NULL
SQL,
        <<<'SQL'
INSERT INTO fee_categories (stokvel_id, code, name, is_active, created_at, updated_at)
SELECT s.id, v.code, v.name, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM stokvels s
CROSS JOIN (
    SELECT 'joining' AS code, 'Joining fee' AS name
    UNION ALL SELECT 'monthly', 'Monthly contribution'
    UNION ALL SELECT 'penalty', 'Penalty / fine'
    UNION ALL SELECT 'special', 'Special levy'
    UNION ALL SELECT 'other', 'Other'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM fee_categories fc WHERE fc.stokvel_id = s.id AND fc.code = v.code
)
SQL,
        <<<'SQL'
INSERT INTO meeting_categories (stokvel_id, code, name, is_active, created_at, updated_at)
SELECT s.id, v.code, v.name, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM stokvels s
CROSS JOIN (
    SELECT 'regular' AS code, 'Regular' AS name
    UNION ALL SELECT 'agm', 'Annual general meeting'
    UNION ALL SELECT 'special', 'Special'
    UNION ALL SELECT 'committee', 'Committee'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM meeting_categories mc WHERE mc.stokvel_id = s.id AND mc.code = v.code
)
SQL,
        <<<'SQL'
INSERT INTO rule_categories (stokvel_id, code, name, is_active, created_at, updated_at)
SELECT s.id, v.code, v.name, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM stokvels s
CROSS JOIN (
    SELECT 'general' AS code, 'General' AS name
    UNION ALL SELECT 'finance', 'Finance'
    UNION ALL SELECT 'membership', 'Membership'
    UNION ALL SELECT 'conduct', 'Conduct'
    UNION ALL SELECT 'meetings', 'Meetings'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM rule_categories rc WHERE rc.stokvel_id = s.id AND rc.code = v.code
)
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS rule_categories',
        'DROP TABLE IF EXISTS meeting_categories',
        'DROP TABLE IF EXISTS fee_categories',
        'DROP TABLE IF EXISTS user_webauthn_credentials',
        'DROP TABLE IF EXISTS user_totp',
    ],
];
