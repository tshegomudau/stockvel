<?php

declare(strict_types=1);

/**
 * Fees, payments, ledger, expenses, and idempotency tables.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE fee_structures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    structure_type VARCHAR(32) NOT NULL DEFAULT 'contribution',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    description VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fee_structures_stokvel_code (stokvel_id, code),
    KEY idx_fee_structures_stokvel_id_status (stokvel_id, status),
    CONSTRAINT fk_fee_structures_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE fee_structure_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    fee_structure_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    frequency VARCHAR(32) NOT NULL DEFAULT 'monthly',
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    grace_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
    rules_json JSON NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fee_structure_versions_structure_version (fee_structure_id, version_number),
    KEY idx_fee_structure_versions_stokvel_effective (stokvel_id, effective_from),
    CONSTRAINT fk_fee_structure_versions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_fee_structure_versions_structure FOREIGN KEY (fee_structure_id) REFERENCES fee_structures (id) ON DELETE CASCADE,
    CONSTRAINT fk_fee_structure_versions_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE member_charges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    fee_structure_version_id BIGINT UNSIGNED NOT NULL,
    charge_code VARCHAR(64) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    due_date DATE NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    period_start DATE NULL,
    period_end DATE NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_member_charges_stokvel_status_due (stokvel_id, status, due_date),
    KEY idx_member_charges_stokvel_membership (stokvel_id, membership_id),
    KEY idx_member_charges_fee_version (fee_structure_version_id),
    CONSTRAINT fk_member_charges_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_member_charges_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_member_charges_fee_version FOREIGN KEY (fee_structure_version_id) REFERENCES fee_structure_versions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE payment_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    transaction_ref VARCHAR(64) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(32) NOT NULL,
    external_reference VARCHAR(128) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    recorded_by_user_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    idempotency_key VARCHAR(128) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_payment_transactions_stokvel_ref (stokvel_id, transaction_ref),
    KEY idx_payment_transactions_stokvel_status (stokvel_id, status),
    KEY idx_payment_transactions_stokvel_membership (stokvel_id, membership_id),
    KEY idx_payment_transactions_idempotency (stokvel_id, idempotency_key),
    CONSTRAINT fk_payment_transactions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_transactions_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_transactions_recorded_by FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE payment_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    payment_transaction_id BIGINT UNSIGNED NOT NULL,
    member_charge_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_payment_allocations_stokvel_payment (stokvel_id, payment_transaction_id),
    KEY idx_payment_allocations_stokvel_charge (stokvel_id, member_charge_id),
    CONSTRAINT fk_payment_allocations_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_allocations_payment FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions (id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_allocations_charge FOREIGN KEY (member_charge_id) REFERENCES member_charges (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE chart_of_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(128) NOT NULL,
    account_type VARCHAR(32) NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_chart_of_accounts_stokvel_code (stokvel_id, code),
    KEY idx_chart_of_accounts_stokvel_type (stokvel_id, account_type),
    KEY idx_chart_of_accounts_parent (parent_id),
    CONSTRAINT fk_chart_of_accounts_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_chart_of_accounts_parent FOREIGN KEY (parent_id) REFERENCES chart_of_accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE journal_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    entry_number VARCHAR(32) NOT NULL,
    entry_date DATE NOT NULL,
    description VARCHAR(512) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    source_type VARCHAR(64) NULL,
    source_id BIGINT UNSIGNED NULL,
    posted_at DATETIME NULL,
    posted_by_user_id BIGINT UNSIGNED NULL,
    reversed_entry_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_journal_entries_stokvel_number (stokvel_id, entry_number),
    KEY idx_journal_entries_stokvel_date (stokvel_id, entry_date),
    KEY idx_journal_entries_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_journal_entries_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_journal_entries_posted_by FOREIGN KEY (posted_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_journal_entries_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_journal_entries_reversed FOREIGN KEY (reversed_entry_id) REFERENCES journal_entries (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE journal_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    journal_entry_id BIGINT UNSIGNED NOT NULL,
    chart_of_account_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NULL,
    line_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
    description VARCHAR(255) NULL,
    debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_journal_lines_stokvel_entry (stokvel_id, journal_entry_id),
    KEY idx_journal_lines_stokvel_account (stokvel_id, chart_of_account_id),
    KEY idx_journal_lines_membership (membership_id),
    CONSTRAINT fk_journal_lines_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_journal_lines_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_journal_lines_account FOREIGN KEY (chart_of_account_id) REFERENCES chart_of_accounts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_journal_lines_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE approval_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    request_type VARCHAR(64) NOT NULL,
    reference_type VARCHAR(64) NOT NULL,
    reference_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    requested_by_user_id BIGINT UNSIGNED NOT NULL,
    approved_by_user_id BIGINT UNSIGNED NULL,
    rejected_by_user_id BIGINT UNSIGNED NULL,
    decision_notes TEXT NULL,
    requested_at DATETIME NOT NULL,
    decided_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_approval_requests_stokvel_status (stokvel_id, status),
    KEY idx_approval_requests_stokvel_reference (stokvel_id, reference_type, reference_id),
    CONSTRAINT fk_approval_requests_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_approval_requests_requested_by FOREIGN KEY (requested_by_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_approval_requests_approved_by FOREIGN KEY (approved_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_approval_requests_rejected_by FOREIGN KEY (rejected_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE expense_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(128) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_expense_categories_stokvel_code (stokvel_id, code),
    KEY idx_expense_categories_stokvel_id (stokvel_id),
    CONSTRAINT fk_expense_categories_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE expenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    expense_category_id BIGINT UNSIGNED NOT NULL,
    expense_ref VARCHAR(64) NOT NULL,
    description VARCHAR(512) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    incurred_at DATE NOT NULL,
    paid_at DATETIME NULL,
    vendor_name VARCHAR(255) NULL,
    recorded_by_user_id BIGINT UNSIGNED NULL,
    approved_by_user_id BIGINT UNSIGNED NULL,
    journal_entry_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_expenses_stokvel_ref (stokvel_id, expense_ref),
    KEY idx_expenses_stokvel_status (stokvel_id, status),
    KEY idx_expenses_stokvel_category (stokvel_id, expense_category_id),
    CONSTRAINT fk_expenses_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_expenses_category FOREIGN KEY (expense_category_id) REFERENCES expense_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_expenses_recorded_by FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_expenses_approved_by FOREIGN KEY (approved_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_expenses_journal_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE budgets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    expense_category_id BIGINT UNSIGNED NOT NULL,
    fiscal_year SMALLINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    notes VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_budgets_stokvel_category_year (stokvel_id, expense_category_id, fiscal_year),
    KEY idx_budgets_stokvel_year (stokvel_id, fiscal_year),
    CONSTRAINT fk_budgets_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_budgets_category FOREIGN KEY (expense_category_id) REFERENCES expense_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE idempotency_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    request_method VARCHAR(16) NOT NULL,
    request_path VARCHAR(255) NOT NULL,
    request_hash CHAR(64) NULL,
    response_status SMALLINT UNSIGNED NULL,
    response_body_hash CHAR(64) NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_idempotency_keys_stokvel_key (stokvel_id, idempotency_key),
    KEY idx_idempotency_keys_stokvel_expires (stokvel_id, expires_at),
    CONSTRAINT fk_idempotency_keys_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NULL,
    provider VARCHAR(64) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    external_id VARCHAR(128) NULL,
    payload_json JSON NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'received',
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_webhook_events_stokvel_status (stokvel_id, status),
    KEY idx_webhook_events_provider_external (provider, external_id),
    CONSTRAINT fk_webhook_events_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS webhook_events',
        'DROP TABLE IF EXISTS idempotency_keys',
        'DROP TABLE IF EXISTS budgets',
        'DROP TABLE IF EXISTS expenses',
        'DROP TABLE IF EXISTS expense_categories',
        'DROP TABLE IF EXISTS approval_requests',
        'DROP TABLE IF EXISTS journal_lines',
        'DROP TABLE IF EXISTS journal_entries',
        'DROP TABLE IF EXISTS chart_of_accounts',
        'DROP TABLE IF EXISTS payment_allocations',
        'DROP TABLE IF EXISTS payment_transactions',
        'DROP TABLE IF EXISTS member_charges',
        'DROP TABLE IF EXISTS fee_structure_versions',
        'DROP TABLE IF EXISTS fee_structures',
    ],
];
