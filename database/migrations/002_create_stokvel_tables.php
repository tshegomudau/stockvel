<?php

declare(strict_types=1);

/**
 * Migration 002 — Stokvel tenancy, membership, RBAC, invitations, and consent.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE stokvels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    stokvel_type VARCHAR(64) NOT NULL DEFAULT 'general_savings',
    registration_number VARCHAR(64) NULL,
    description TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Johannesburg',
    currency_code CHAR(3) NOT NULL DEFAULT 'ZAR',
    financial_year_start_month TINYINT UNSIGNED NOT NULL DEFAULT 3,
    activated_at DATETIME NULL,
    suspended_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stokvels_uuid (uuid),
    UNIQUE KEY uk_stokvels_slug (slug),
    KEY idx_stokvels_status (status),
    KEY idx_stokvels_type (stokvel_type),
    CONSTRAINT fk_stokvels_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE stokvel_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    contribution_day TINYINT UNSIGNED NULL,
    contribution_frequency VARCHAR(32) NOT NULL DEFAULT 'monthly',
    grace_period_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
    require_dual_approval TINYINT(1) NOT NULL DEFAULT 1,
    min_members INT UNSIGNED NOT NULL DEFAULT 5,
    max_members INT UNSIGNED NULL,
    loans_enabled TINYINT(1) NOT NULL DEFAULT 0,
    payout_mode VARCHAR(32) NOT NULL DEFAULT 'rotational',
    bank_name VARCHAR(128) NULL,
    bank_account_name VARCHAR(255) NULL,
    bank_account_number_masked VARCHAR(32) NULL,
    bank_branch_code VARCHAR(16) NULL,
    notification_email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    notification_sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
    branding_primary_colour VARCHAR(7) NULL,
    branding_logo_path VARCHAR(500) NULL,
    settings_json JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stokvel_settings_stokvel_id (stokvel_id),
    CONSTRAINT fk_stokvel_settings_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE stokvel_onboarding (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    current_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
    steps_completed JSON NULL,
    wizard_data JSON NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stokvel_onboarding_stokvel_id (stokvel_id),
    CONSTRAINT fk_stokvel_onboarding_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NULL COMMENT 'NULL = system-wide stokvel role template',
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_roles_stokvel_code (stokvel_id, code),
    KEY idx_roles_stokvel_id (stokvel_id),
    KEY idx_roles_code (code),
    CONSTRAINT fk_roles_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE role_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_role_permissions_role_permission (role_id, permission_id),
    KEY idx_role_permissions_permission_id (permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    member_number VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    joined_at DATETIME NULL,
    approved_at DATETIME NULL,
    resigned_at DATETIME NULL,
    suspended_at DATETIME NULL,
    suspension_reason VARCHAR(512) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_memberships_stokvel_user (stokvel_id, user_id),
    UNIQUE KEY uk_memberships_stokvel_member_number (stokvel_id, member_number),
    KEY idx_memberships_stokvel_id_status (stokvel_id, status),
    KEY idx_memberships_user_id (user_id),
    CONSTRAINT fk_memberships_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE membership_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(32) NULL,
    to_status VARCHAR(32) NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    reason VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_membership_status_history_stokvel_membership (stokvel_id, membership_id),
    KEY idx_membership_status_history_created_at (stokvel_id, created_at),
    CONSTRAINT fk_membership_status_history_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_membership_status_history_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_membership_status_history_changed_by FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE membership_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assigned_by_user_id BIGINT UNSIGNED NULL,
    assigned_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_membership_roles_membership_role (membership_id, role_id),
    KEY idx_membership_roles_stokvel_id (stokvel_id),
    KEY idx_membership_roles_role_id (role_id),
    CONSTRAINT fk_membership_roles_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_membership_roles_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_membership_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_membership_roles_assigned_by FOREIGN KEY (assigned_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE membership_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    granted TINYINT(1) NOT NULL DEFAULT 1,
    granted_by_user_id BIGINT UNSIGNED NULL,
    reason VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_membership_permissions_membership_permission (membership_id, permission_id),
    KEY idx_membership_permissions_stokvel_id (stokvel_id),
    KEY idx_membership_permissions_permission_id (permission_id),
    CONSTRAINT fk_membership_permissions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_membership_permissions_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_membership_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE,
    CONSTRAINT fk_membership_permissions_granted_by FOREIGN KEY (granted_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE invitations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NULL,
    mobile VARCHAR(32) NULL,
    token_hash CHAR(64) NOT NULL,
    role_id BIGINT UNSIGNED NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    invited_by_user_id BIGINT UNSIGNED NOT NULL,
    message TEXT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    rejected_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_invitations_stokvel_id_status (stokvel_id, status),
    KEY idx_invitations_token_hash (token_hash),
    KEY idx_invitations_email (email),
    CONSTRAINT fk_invitations_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_invitations_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE SET NULL,
    CONSTRAINT fk_invitations_invited_by FOREIGN KEY (invited_by_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE member_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_member_groups_stokvel_name (stokvel_id, name),
    KEY idx_member_groups_stokvel_id (stokvel_id),
    CONSTRAINT fk_member_groups_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE member_group_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    member_group_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_member_group_members_group_membership (member_group_id, membership_id),
    KEY idx_member_group_members_stokvel_id (stokvel_id),
    KEY idx_member_group_members_membership_id (membership_id),
    CONSTRAINT fk_member_group_members_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_member_group_members_group FOREIGN KEY (member_group_id) REFERENCES member_groups (id) ON DELETE CASCADE,
    CONSTRAINT fk_member_group_members_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE beneficiaries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    id_number VARCHAR(13) NULL,
    relationship VARCHAR(64) NOT NULL,
    share_percent DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    phone VARCHAR(32) NULL,
    email VARCHAR(255) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_beneficiaries_stokvel_membership (stokvel_id, membership_id),
    CONSTRAINT fk_beneficiaries_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_beneficiaries_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE consent_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    consent_type VARCHAR(64) NOT NULL,
    granted TINYINT(1) NOT NULL,
    consent_text_version VARCHAR(32) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    recorded_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_consent_records_stokvel_user (stokvel_id, user_id),
    KEY idx_consent_records_stokvel_type (stokvel_id, consent_type),
    CONSTRAINT fk_consent_records_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_consent_records_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS consent_records',
        'DROP TABLE IF EXISTS beneficiaries',
        'DROP TABLE IF EXISTS member_group_members',
        'DROP TABLE IF EXISTS member_groups',
        'DROP TABLE IF EXISTS invitations',
        'DROP TABLE IF EXISTS membership_permissions',
        'DROP TABLE IF EXISTS membership_roles',
        'DROP TABLE IF EXISTS membership_status_history',
        'DROP TABLE IF EXISTS memberships',
        'DROP TABLE IF EXISTS role_permissions',
        'DROP TABLE IF EXISTS roles',
        'DROP TABLE IF EXISTS permissions',
        'DROP TABLE IF EXISTS stokvel_onboarding',
        'DROP TABLE IF EXISTS stokvel_settings',
        'DROP TABLE IF EXISTS stokvels',
    ],
];
