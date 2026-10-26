<?php

declare(strict_types=1);

/**
 * Migration 019 — Tenant-scoped loan purpose catalogue (business options, not workflow states).
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS loan_purposes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_loan_purposes_stokvel_code (stokvel_id, code),
    KEY idx_loan_purposes_stokvel_active (stokvel_id, is_active),
    CONSTRAINT fk_loan_purposes_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
INSERT INTO loan_purposes (stokvel_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT s.id, v.code, v.name, 1, v.sort_order, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM stokvels s
CROSS JOIN (
    SELECT 'emergency' AS code, 'Emergency' AS name, 10 AS sort_order
    UNION ALL SELECT 'education', 'Education', 20
    UNION ALL SELECT 'medical', 'Medical', 30
    UNION ALL SELECT 'business', 'Business', 40
    UNION ALL SELECT 'home', 'Home improvement', 50
    UNION ALL SELECT 'other', 'Other', 90
) v
WHERE NOT EXISTS (
    SELECT 1 FROM loan_purposes lp WHERE lp.stokvel_id = s.id AND lp.code = v.code
)
SQL,
        "ALTER TABLE loan_applications ADD COLUMN loan_purpose_id BIGINT UNSIGNED NULL AFTER purpose",
        <<<'SQL'
ALTER TABLE loan_applications
    ADD CONSTRAINT fk_loan_applications_purpose
    FOREIGN KEY (loan_purpose_id) REFERENCES loan_purposes (id) ON DELETE SET NULL
SQL,
    ],
    'down' => [
        'ALTER TABLE loan_applications DROP FOREIGN KEY fk_loan_applications_purpose',
        'ALTER TABLE loan_applications DROP COLUMN loan_purpose_id',
        'DROP TABLE IF EXISTS loan_purposes',
    ],
];
