<?php

declare(strict_types=1);

/**
 * Migration 006 — Notifications, notification_jobs, audit_logs, cron locks.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(32) NOT NULL DEFAULT 'in_app',
    notification_type VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    data_json JSON NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'unread',
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_notifications_stokvel_user_status (stokvel_id, user_id, status),
    KEY idx_notifications_user_created (user_id, created_at),
    CONSTRAINT fk_notifications_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE notification_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    channel VARCHAR(32) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    body TEXT NOT NULL,
    payload_json JSON NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
    scheduled_for DATETIME NOT NULL,
    sent_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_notification_jobs_stokvel_status_scheduled (stokvel_id, status, scheduled_for),
    KEY idx_notification_jobs_status_scheduled (status, scheduled_for),
    CONSTRAINT fk_notification_jobs_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_jobs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    old_values_json JSON NULL,
    new_values_json JSON NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_logs_stokvel_created (stokvel_id, created_at),
    KEY idx_audit_logs_stokvel_entity (stokvel_id, entity_type, entity_id),
    KEY idx_audit_logs_user_created (user_id, created_at),
    CONSTRAINT fk_audit_logs_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE scheduled_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NULL,
    job_code VARCHAR(64) NOT NULL,
    payload_json JSON NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    scheduled_for DATETIME NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_scheduled_jobs_stokvel_status_scheduled (stokvel_id, status, scheduled_for),
    KEY idx_scheduled_jobs_code_status (job_code, status),
    CONSTRAINT fk_scheduled_jobs_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE job_locks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NULL,
    lock_key VARCHAR(128) NOT NULL,
    locked_by VARCHAR(128) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_job_locks_stokvel_key (stokvel_id, lock_key),
    KEY idx_job_locks_expires_at (expires_at),
    CONSTRAINT fk_job_locks_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS job_locks',
        'DROP TABLE IF EXISTS scheduled_jobs',
        'DROP TABLE IF EXISTS audit_logs',
        'DROP TABLE IF EXISTS notification_jobs',
        'DROP TABLE IF EXISTS notifications',
    ],
];
