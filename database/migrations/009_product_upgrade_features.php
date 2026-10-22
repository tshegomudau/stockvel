<?php

declare(strict_types=1);

/**
 * Migration 009 — Support tickets, encrypted ID storage, typed documents,
 * member change requests, settings fields, meeting notes.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    assigned_to_user_id BIGINT UNSIGNED NULL,
    subject VARCHAR(255) NOT NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'bug',
    priority VARCHAR(32) NOT NULL DEFAULT 'normal',
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    body TEXT NOT NULL,
    platform_notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_support_tickets_status (status, created_at),
    KEY idx_support_tickets_stokvel (stokvel_id, status),
    CONSTRAINT fk_support_tickets_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE SET NULL,
    CONSTRAINT fk_support_tickets_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_support_tickets_assignee FOREIGN KEY (assigned_to_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS support_ticket_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NULL,
    body TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ticket_updates_ticket (ticket_id, created_at),
    CONSTRAINT fk_ticket_updates_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_updates_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS member_change_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    requested_by_user_id BIGINT UNSIGNED NOT NULL,
    change_type VARCHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending_member',
    member_decided_at DATETIME NULL,
    member_decision_notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_member_change_requests_membership (membership_id, status),
    CONSTRAINT fk_mcr_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_mcr_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_mcr_requester FOREIGN KEY (requested_by_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        'ALTER TABLE user_profiles ADD COLUMN id_number_encrypted TEXT NULL AFTER id_number',
        'ALTER TABLE user_profiles ADD COLUMN id_number_hash CHAR(64) NULL AFTER id_number_encrypted',
        'ALTER TABLE documents ADD COLUMN body_html MEDIUMTEXT NULL AFTER title',
        "ALTER TABLE documents ADD COLUMN content_type VARCHAR(32) NOT NULL DEFAULT 'file' AFTER body_html",
        'ALTER TABLE stokvel_settings ADD COLUMN minimum_contribution DECIMAL(15,2) NULL AFTER contribution_frequency',
        'ALTER TABLE stokvel_settings ADD COLUMN payment_due_day TINYINT UNSIGNED NULL AFTER minimum_contribution',
        'ALTER TABLE stokvel_settings ADD COLUMN arrears_after_days INT UNSIGNED NOT NULL DEFAULT 7 AFTER payment_due_day',
        'ALTER TABLE stokvel_settings ADD COLUMN year_end_month TINYINT UNSIGNED NOT NULL DEFAULT 12 AFTER arrears_after_days',
        'ALTER TABLE meetings ADD COLUMN notes TEXT NULL AFTER venue',
        <<<'SQL'
CREATE TABLE IF NOT EXISTS notification_preferences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    stokvel_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(64) NOT NULL,
    channel_in_app TINYINT(1) NOT NULL DEFAULT 1,
    channel_email TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_notif_prefs (user_id, stokvel_id, event_type),
    CONSTRAINT fk_notif_prefs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_prefs_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS notification_preferences',
        'DROP TABLE IF EXISTS member_change_requests',
        'DROP TABLE IF EXISTS support_ticket_updates',
        'DROP TABLE IF EXISTS support_tickets',
    ],
];
