<?php

declare(strict_types=1);

/**
 * Internal funds/pockets (Admin Pocket), joining fee policy + obligations,
 * fine types/fines, announcements, constitutions, beneficiary relationships.
 *
 * Funds are accounting allocations — not bank accounts.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS stokvel_funds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    allow_negative TINYINT(1) NOT NULL DEFAULT 0,
    target_amount DECIMAL(15,2) NULL,
    target_date DATE NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stokvel_funds_code (stokvel_id, code),
    KEY idx_stokvel_funds_active (stokvel_id, is_active),
    CONSTRAINT fk_stokvel_funds_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS fund_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    fund_id BIGINT UNSIGNED NOT NULL,
    entry_date DATE NOT NULL,
    direction ENUM('credit','debit') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    reference VARCHAR(64) NULL,
    category_name VARCHAR(128) NULL,
    expense_category_id BIGINT UNSIGNED NULL,
    source_type VARCHAR(64) NULL,
    source_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    idempotency_key VARCHAR(128) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fund_tx_idempotency (stokvel_id, idempotency_key),
    KEY idx_fund_tx_fund_date (fund_id, entry_date),
    KEY idx_fund_tx_stokvel (stokvel_id, entry_date),
    CONSTRAINT fk_fund_tx_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_fund_tx_fund FOREIGN KEY (fund_id) REFERENCES stokvel_funds (id) ON DELETE RESTRICT,
    CONSTRAINT fk_fund_tx_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS joining_fee_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_compulsory TINYINT(1) NOT NULL DEFAULT 1,
    is_once_off TINYINT(1) NOT NULL DEFAULT 1,
    charge_on_approval TINYINT(1) NOT NULL DEFAULT 1,
    charge_on_anniversary TINYINT(1) NOT NULL DEFAULT 0,
    effective_from DATE NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_joining_fee_settings_stokvel (stokvel_id),
    CONSTRAINT fk_joining_fee_settings_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS member_joining_fees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    amount_charged DECIMAL(15,2) NOT NULL,
    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'outstanding',
    charged_at DATETIME NOT NULL,
    due_date DATE NULL,
    waived_at DATETIME NULL,
    waived_by_user_id BIGINT UNSIGNED NULL,
    waived_reason VARCHAR(512) NULL,
    member_charge_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_mjf_member (stokvel_id, membership_id),
    KEY idx_mjf_status (stokvel_id, status),
    CONSTRAINT fk_mjf_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_mjf_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS fine_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NULL,
    default_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_automatic_eligible TINYINT(1) NOT NULL DEFAULT 0,
    rule_id BIGINT UNSIGNED NULL,
    effective_from DATE NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fine_types_code (stokvel_id, code),
    KEY idx_fine_types_active (stokvel_id, is_active),
    CONSTRAINT fk_fine_types_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS fines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    fine_type_id BIGINT UNSIGNED NOT NULL,
    fine_type_name_snapshot VARCHAR(128) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    waived_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    reason VARCHAR(512) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'outstanding',
    issued_by_user_id BIGINT UNSIGNED NULL,
    issued_at DATETIME NOT NULL,
    due_date DATE NULL,
    waived_by_user_id BIGINT UNSIGNED NULL,
    waived_at DATETIME NULL,
    waived_reason VARCHAR(512) NULL,
    source_type VARCHAR(64) NULL,
    source_id BIGINT UNSIGNED NULL,
    idempotency_key VARCHAR(128) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fines_idempotency (stokvel_id, idempotency_key),
    KEY idx_fines_member_status (stokvel_id, membership_id, status),
    CONSTRAINT fk_fines_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_fines_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_fines_type FOREIGN KEY (fine_type_id) REFERENCES fine_types (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS announcements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    priority VARCHAR(32) NOT NULL DEFAULT 'normal',
    audience VARCHAR(32) NOT NULL DEFAULT 'all',
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    publish_at DATETIME NULL,
    expire_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_announcements_status (stokvel_id, status, publish_at),
    CONSTRAINT fk_announcements_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS announcement_reads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    announcement_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_announcement_reads (announcement_id, user_id),
    CONSTRAINT fk_announcement_reads_ann FOREIGN KEY (announcement_id) REFERENCES announcements (id) ON DELETE CASCADE,
    CONSTRAINT fk_announcement_reads_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS constitutions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT 'Stokvel Constitution',
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    current_version_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_constitutions_stokvel (stokvel_id),
    CONSTRAINT fk_constitutions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS constitution_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    constitution_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    effective_from DATE NULL,
    approved_at DATETIME NULL,
    approved_by_user_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_constitution_versions (constitution_id, version_number),
    CONSTRAINT fk_constitution_versions_parent FOREIGN KEY (constitution_id) REFERENCES constitutions (id) ON DELETE CASCADE,
    CONSTRAINT fk_constitution_versions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS beneficiary_relationship_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ben_rel_types (stokvel_id, code),
    CONSTRAINT fk_ben_rel_types_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        "ALTER TABLE stokvel_settings
            ADD COLUMN joining_fee_amount DECIMAL(15,2) NULL AFTER minimum_contribution",
        "ALTER TABLE stokvel_settings
            ADD COLUMN allow_admin_pocket_negative TINYINT(1) NOT NULL DEFAULT 0 AFTER joining_fee_amount",
        "ALTER TABLE stokvels
            ADD COLUMN formation_date DATE NULL AFTER activated_at",
        "ALTER TABLE stokvels
            ADD COLUMN contact_email VARCHAR(255) NULL AFTER formation_date",
        "ALTER TABLE stokvels
            ADD COLUMN contact_phone VARCHAR(32) NULL AFTER contact_email",
        "ALTER TABLE stokvels
            ADD COLUMN physical_address VARCHAR(512) NULL AFTER contact_phone",
        "ALTER TABLE stokvels
            ADD COLUMN visibility VARCHAR(32) NOT NULL DEFAULT 'private' AFTER physical_address",
    ],
    'down' => [
        'ALTER TABLE stokvels DROP COLUMN visibility',
        'ALTER TABLE stokvels DROP COLUMN physical_address',
        'ALTER TABLE stokvels DROP COLUMN contact_phone',
        'ALTER TABLE stokvels DROP COLUMN contact_email',
        'ALTER TABLE stokvels DROP COLUMN formation_date',
        'ALTER TABLE stokvel_settings DROP COLUMN allow_admin_pocket_negative',
        'ALTER TABLE stokvel_settings DROP COLUMN joining_fee_amount',
        'DROP TABLE IF EXISTS beneficiary_relationship_types',
        'DROP TABLE IF EXISTS constitution_versions',
        'DROP TABLE IF EXISTS constitutions',
        'DROP TABLE IF EXISTS announcement_reads',
        'DROP TABLE IF EXISTS announcements',
        'DROP TABLE IF EXISTS fines',
        'DROP TABLE IF EXISTS fine_types',
        'DROP TABLE IF EXISTS member_joining_fees',
        'DROP TABLE IF EXISTS joining_fee_settings',
        'DROP TABLE IF EXISTS fund_transactions',
        'DROP TABLE IF EXISTS stokvel_funds',
    ],
];
