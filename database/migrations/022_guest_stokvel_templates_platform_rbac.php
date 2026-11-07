<?php

declare(strict_types=1);

/**
 * Additive: guest stokvel creation requests, email templates, platform permission overrides,
 * and provenance columns on stokvels. No drops or destructive renames.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS stokvel_creation_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_token CHAR(64) NOT NULL,
    requester_first_name VARCHAR(100) NOT NULL,
    requester_last_name VARCHAR(100) NOT NULL,
    requester_email VARCHAR(255) NOT NULL,
    stokvel_name VARCHAR(200) NOT NULL,
    stokvel_type VARCHAR(64) NOT NULL DEFAULT 'general_savings',
    description TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    duplicate_key CHAR(64) NOT NULL,
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    stokvel_id BIGINT UNSIGNED NULL,
    claim_token_hash CHAR(64) NULL,
    claim_expires_at DATETIME NULL,
    claimed_at DATETIME NULL,
    claimed_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stokvel_creation_public_token (public_token),
    KEY idx_stokvel_creation_status (status, created_at),
    KEY idx_stokvel_creation_email (requester_email),
    KEY idx_stokvel_creation_duplicate (duplicate_key, status),
    CONSTRAINT fk_stokvel_creation_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS stokvel_email_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    template_key VARCHAR(64) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stokvel_email_templates (stokvel_id, template_key),
    CONSTRAINT fk_stokvel_email_templates_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS platform_user_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    permission_code VARCHAR(128) NOT NULL,
    granted TINYINT(1) NOT NULL DEFAULT 1,
    granted_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_platform_user_permissions (user_id, permission_code),
    KEY idx_platform_user_permissions_user (user_id),
    CONSTRAINT fk_platform_user_permissions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
ALTER TABLE stokvels
    ADD COLUMN created_via VARCHAR(32) NOT NULL DEFAULT 'legacy' AFTER created_by_user_id
SQL,
        <<<'SQL'
ALTER TABLE stokvels
    ADD COLUMN creation_approved_by_user_id BIGINT UNSIGNED NULL AFTER created_via
SQL,
        <<<'SQL'
ALTER TABLE stokvels
    ADD COLUMN approval_bypassed TINYINT(1) NOT NULL DEFAULT 0 AFTER creation_approved_by_user_id
SQL,
    ],
    'down' => [
        // Additive-only rollback helpers; prefer not to run against production.
        'ALTER TABLE stokvels DROP COLUMN approval_bypassed',
        'ALTER TABLE stokvels DROP COLUMN creation_approved_by_user_id',
        'ALTER TABLE stokvels DROP COLUMN created_via',
        'DROP TABLE IF EXISTS platform_user_permissions',
        'DROP TABLE IF EXISTS stokvel_email_templates',
        'DROP TABLE IF EXISTS stokvel_creation_requests',
    ],
];
