<?php

declare(strict_types=1);

/**
 * Migration 005 — Meetings, rules/constitution, folders, and documents.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE meetings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    meeting_ref VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    meeting_type VARCHAR(32) NOT NULL DEFAULT 'regular',
    status VARCHAR(32) NOT NULL DEFAULT 'scheduled',
    scheduled_at DATETIME NOT NULL,
    venue VARCHAR(255) NULL,
    virtual_link VARCHAR(512) NULL,
    quorum_required INT UNSIGNED NULL,
    chaired_by_membership_id BIGINT UNSIGNED NULL,
    minutes_approved_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_meetings_stokvel_ref (stokvel_id, meeting_ref),
    KEY idx_meetings_stokvel_scheduled (stokvel_id, scheduled_at),
    KEY idx_meetings_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_meetings_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_meetings_chair FOREIGN KEY (chaired_by_membership_id) REFERENCES memberships (id) ON DELETE SET NULL,
    CONSTRAINT fk_meetings_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE meeting_attendance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    meeting_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    attendance_status VARCHAR(32) NOT NULL DEFAULT 'present',
    recorded_at DATETIME NOT NULL,
    notes VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_meeting_attendance_meeting_membership (meeting_id, membership_id),
    KEY idx_meeting_attendance_stokvel_meeting (stokvel_id, meeting_id),
    CONSTRAINT fk_meeting_attendance_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_attendance_meeting FOREIGN KEY (meeting_id) REFERENCES meetings (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_attendance_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE meeting_agendas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    meeting_id BIGINT UNSIGNED NOT NULL,
    item_number TINYINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    presenter_membership_id BIGINT UNSIGNED NULL,
    duration_minutes TINYINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_meeting_agendas_meeting_item (meeting_id, item_number),
    KEY idx_meeting_agendas_stokvel_meeting (stokvel_id, meeting_id),
    CONSTRAINT fk_meeting_agendas_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_agendas_meeting FOREIGN KEY (meeting_id) REFERENCES meetings (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_agendas_presenter FOREIGN KEY (presenter_membership_id) REFERENCES memberships (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE meeting_minutes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    meeting_id BIGINT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    recorded_by_user_id BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_meeting_minutes_meeting (meeting_id),
    KEY idx_meeting_minutes_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_meeting_minutes_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_minutes_meeting FOREIGN KEY (meeting_id) REFERENCES meetings (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_minutes_recorded_by FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE resolutions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    meeting_id BIGINT UNSIGNED NOT NULL,
    resolution_number VARCHAR(32) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'proposed',
    votes_for INT UNSIGNED NOT NULL DEFAULT 0,
    votes_against INT UNSIGNED NOT NULL DEFAULT 0,
    votes_abstain INT UNSIGNED NOT NULL DEFAULT 0,
    passed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_resolutions_stokvel_number (stokvel_id, resolution_number),
    KEY idx_resolutions_stokvel_meeting (stokvel_id, meeting_id),
    CONSTRAINT fk_resolutions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_resolutions_meeting FOREIGN KEY (meeting_id) REFERENCES meetings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE votes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    resolution_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    vote_value VARCHAR(16) NOT NULL,
    cast_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_votes_resolution_membership (resolution_id, membership_id),
    KEY idx_votes_stokvel_resolution (stokvel_id, resolution_id),
    CONSTRAINT fk_votes_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_resolution FOREIGN KEY (resolution_id) REFERENCES resolutions (id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE action_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    meeting_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    assigned_to_membership_id BIGINT UNSIGNED NULL,
    due_date DATE NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_action_items_stokvel_status (stokvel_id, status),
    KEY idx_action_items_stokvel_meeting (stokvel_id, meeting_id),
    CONSTRAINT fk_action_items_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_action_items_meeting FOREIGN KEY (meeting_id) REFERENCES meetings (id) ON DELETE CASCADE,
    CONSTRAINT fk_action_items_assigned FOREIGN KEY (assigned_to_membership_id) REFERENCES memberships (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_rules_stokvel_code (stokvel_id, code),
    KEY idx_rules_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_rules_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE rule_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    rule_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    published_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_rule_versions_rule_version (rule_id, version_number),
    KEY idx_rule_versions_stokvel_effective (stokvel_id, effective_from),
    CONSTRAINT fk_rule_versions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_rule_versions_rule FOREIGN KEY (rule_id) REFERENCES rules (id) ON DELETE CASCADE,
    CONSTRAINT fk_rule_versions_published_by FOREIGN KEY (published_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE rule_acknowledgements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    rule_version_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    acknowledged_at DATETIME NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_rule_ack_version_membership (rule_version_id, membership_id),
    KEY idx_rule_ack_stokvel_membership (stokvel_id, membership_id),
    CONSTRAINT fk_rule_ack_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_rule_ack_version FOREIGN KEY (rule_version_id) REFERENCES rule_versions (id) ON DELETE CASCADE,
    CONSTRAINT fk_rule_ack_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE folders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_folders_stokvel_slug (stokvel_id, slug),
    KEY idx_folders_stokvel_parent (stokvel_id, parent_id),
    CONSTRAINT fk_folders_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id) REFERENCES folders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    folder_id BIGINT UNSIGNED NULL,
    document_ref VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    current_version_id BIGINT UNSIGNED NULL,
    expires_at DATETIME NULL,
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_documents_stokvel_ref (stokvel_id, document_ref),
    KEY idx_documents_stokvel_folder (stokvel_id, folder_id),
    KEY idx_documents_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_documents_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_folder FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE SET NULL,
    CONSTRAINT fk_documents_uploaded_by FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE document_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NULL,
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_document_versions_document_version (document_id, version_number),
    KEY idx_document_versions_stokvel_document (stokvel_id, document_id),
    CONSTRAINT fk_document_versions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_document_versions_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_document_versions_uploaded_by FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
ALTER TABLE documents
    ADD CONSTRAINT fk_documents_current_version FOREIGN KEY (current_version_id) REFERENCES document_versions (id) ON DELETE SET NULL
SQL,
    ],
    'down' => [
        'ALTER TABLE documents DROP FOREIGN KEY fk_documents_current_version',
        'DROP TABLE IF EXISTS document_versions',
        'DROP TABLE IF EXISTS documents',
        'DROP TABLE IF EXISTS folders',
        'DROP TABLE IF EXISTS rule_acknowledgements',
        'DROP TABLE IF EXISTS rule_versions',
        'DROP TABLE IF EXISTS rules',
        'DROP TABLE IF EXISTS action_items',
        'DROP TABLE IF EXISTS votes',
        'DROP TABLE IF EXISTS resolutions',
        'DROP TABLE IF EXISTS meeting_minutes',
        'DROP TABLE IF EXISTS meeting_agendas',
        'DROP TABLE IF EXISTS meeting_attendance',
        'DROP TABLE IF EXISTS meetings',
    ],
];
