<?php

declare(strict_types=1);

/**
 * Migration 011 — Payout categories, document categories, expense category status,
 * meeting minutes / agenda, beneficiary status.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS payout_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_payout_categories_stokvel_code (stokvel_id, code),
    KEY idx_payout_categories_stokvel_active (stokvel_id, is_active),
    CONSTRAINT fk_payout_categories_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS document_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_document_categories_stokvel_code (stokvel_id, code),
    KEY idx_document_categories_stokvel_active (stokvel_id, is_active),
    CONSTRAINT fk_document_categories_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        'ALTER TABLE expense_categories ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER name',
        'ALTER TABLE payout_cycles ADD COLUMN payout_category_id BIGINT UNSIGNED NULL AFTER stokvel_id',
        'ALTER TABLE documents ADD COLUMN document_category_id BIGINT UNSIGNED NULL AFTER category',
        'ALTER TABLE meetings ADD COLUMN agenda TEXT NULL AFTER notes',
        'ALTER TABLE meetings ADD COLUMN minutes_html MEDIUMTEXT NULL AFTER agenda',
        'ALTER TABLE beneficiaries ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT \'active\' AFTER is_primary',
        // Seed default payout categories for every stokvel (idempotent insert ignore via unique code)
        <<<'SQL'
INSERT INTO payout_categories (stokvel_id, code, name, description, is_active, created_at, updated_at)
SELECT s.id, v.code, v.name, v.description, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM stokvels s
CROSS JOIN (
    SELECT 'burial' AS code, 'Burial' AS name, 'Funeral benefit payouts' AS description
    UNION ALL SELECT 'grocery', 'Grocery', 'Grocery / hamper distributions'
    UNION ALL SELECT 'emergency', 'Emergency', 'Emergency member support'
    UNION ALL SELECT 'member_benefit', 'Member benefit', 'General member benefits'
    UNION ALL SELECT 'education', 'Education', 'Education support'
    UNION ALL SELECT 'other', 'Other', 'Other payouts'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM payout_categories pc WHERE pc.stokvel_id = s.id AND pc.code = v.code
)
SQL,
        <<<'SQL'
INSERT INTO document_categories (stokvel_id, code, name, is_active, created_at, updated_at)
SELECT s.id, v.code, v.name, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM stokvels s
CROSS JOIN (
    SELECT 'general' AS code, 'General' AS name
    UNION ALL SELECT 'constitution', 'Constitution'
    UNION ALL SELECT 'minutes', 'Minutes'
    UNION ALL SELECT 'policy', 'Policy'
    UNION ALL SELECT 'notice', 'Notice'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM document_categories dc WHERE dc.stokvel_id = s.id AND dc.code = v.code
)
SQL,
    ],
    'down' => [
        'ALTER TABLE beneficiaries DROP COLUMN status',
        'ALTER TABLE meetings DROP COLUMN minutes_html',
        'ALTER TABLE meetings DROP COLUMN agenda',
        'ALTER TABLE documents DROP COLUMN document_category_id',
        'ALTER TABLE payout_cycles DROP COLUMN payout_category_id',
        'ALTER TABLE expense_categories DROP COLUMN is_active',
        'DROP TABLE IF EXISTS document_categories',
        'DROP TABLE IF EXISTS payout_categories',
    ],
];
