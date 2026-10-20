<?php

declare(strict_types=1);

/**
 * Migration 008 — Meeting acknowledgements and bank reconciliation.
 *
 * Approvals use approval_requests from migration 003 (not a separate approvals table).
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS meeting_acknowledgements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    meeting_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    acknowledged_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_meeting_ack_meeting_user (meeting_id, user_id),
    KEY idx_meeting_ack_stokvel (stokvel_id),
    CONSTRAINT fk_meeting_ack_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_ack_meeting FOREIGN KEY (meeting_id) REFERENCES meetings (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_ack_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS bank_reconciliations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    bank_account_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    statement_opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    statement_closing_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    book_opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    book_closing_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    unmatched_difference DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    started_by BIGINT UNSIGNED NULL,
    completed_by BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_bank_recon_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_bank_recon_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_bank_recon_account FOREIGN KEY (bank_account_id) REFERENCES chart_of_accounts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_bank_recon_started_by FOREIGN KEY (started_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_bank_recon_completed_by FOREIGN KEY (completed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS reconciliation_statement_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    reconciliation_id BIGINT UNSIGNED NOT NULL,
    statement_date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    item_type VARCHAR(32) NOT NULL DEFAULT 'debit',
    external_ref VARCHAR(128) NULL,
    is_matched TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_recon_items_recon (reconciliation_id, is_matched),
    CONSTRAINT fk_recon_items_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_recon_items_recon FOREIGN KEY (reconciliation_id) REFERENCES bank_reconciliations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS reconciliation_matches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    reconciliation_id BIGINT UNSIGNED NOT NULL,
    statement_item_id BIGINT UNSIGNED NOT NULL,
    journal_entry_id BIGINT UNSIGNED NULL,
    payment_transaction_id BIGINT UNSIGNED NULL,
    match_amount DECIMAL(15,2) NOT NULL,
    matched_by BIGINT UNSIGNED NULL,
    matched_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_recon_match_item (statement_item_id),
    KEY idx_recon_matches_recon (reconciliation_id),
    CONSTRAINT fk_recon_matches_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_recon_matches_recon FOREIGN KEY (reconciliation_id) REFERENCES bank_reconciliations (id) ON DELETE CASCADE,
    CONSTRAINT fk_recon_matches_item FOREIGN KEY (statement_item_id) REFERENCES reconciliation_statement_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_recon_matches_journal FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id) ON DELETE SET NULL,
    CONSTRAINT fk_recon_matches_payment FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions (id) ON DELETE SET NULL,
    CONSTRAINT fk_recon_matches_user FOREIGN KEY (matched_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS reconciliation_matches',
        'DROP TABLE IF EXISTS reconciliation_statement_items',
        'DROP TABLE IF EXISTS bank_reconciliations',
        'DROP TABLE IF EXISTS meeting_acknowledgements',
    ],
];
