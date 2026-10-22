<?php

declare(strict_types=1);

/**
 * Platform-managed catalogue of stokvel types. Each stokvel still chooses its own type code.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS stokvel_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stokvel_types_code (code),
    KEY idx_stokvel_types_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
INSERT INTO stokvel_types (code, name, description, sort_order, is_active, created_at, updated_at)
SELECT v.code, v.name, v.description, v.sort_order, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM (
    SELECT 'general_savings' AS code, 'Savings / contribution' AS name, 'Regular contribution savings circle' AS description, 10 AS sort_order
    UNION ALL SELECT 'rotational', 'Rotational (umgalelo)', 'Rotating payout among members', 20
    UNION ALL SELECT 'investment', 'Investment / high budget', 'Investment-focused group', 30
    UNION ALL SELECT 'burial_society', 'Burial society', 'Burial and funeral support', 40
    UNION ALL SELECT 'grocery', 'Grocery / commodity', 'Bulk grocery or commodity buying', 50
    UNION ALL SELECT 'youth', 'Youth / student', 'Youth or student stokvel', 60
    UNION ALL SELECT 'stokvel_loan', 'Lending circle', 'Member lending circle', 70
) AS v
WHERE NOT EXISTS (SELECT 1 FROM stokvel_types t WHERE t.code = v.code)
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS stokvel_types',
    ],
];
