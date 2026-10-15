<?php

declare(strict_types=1);

/**
 * Loans, guarantors, repayments, and rotational payout tables.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE loan_products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    min_amount DECIMAL(15,2) NOT NULL,
    max_amount DECIMAL(15,2) NOT NULL,
    interest_rate_percent DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    max_term_months TINYINT UNSIGNED NOT NULL,
    requires_guarantor TINYINT(1) NOT NULL DEFAULT 1,
    terms_json JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_loan_products_stokvel_code (stokvel_id, code),
    KEY idx_loan_products_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_loan_products_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE loan_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    loan_product_id BIGINT UNSIGNED NOT NULL,
    application_ref VARCHAR(64) NOT NULL,
    requested_amount DECIMAL(15,2) NOT NULL,
    term_months TINYINT UNSIGNED NOT NULL,
    purpose TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'submitted',
    submitted_at DATETIME NOT NULL,
    decided_at DATETIME NULL,
    decided_by_user_id BIGINT UNSIGNED NULL,
    decision_notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_loan_applications_stokvel_ref (stokvel_id, application_ref),
    KEY idx_loan_applications_stokvel_status (stokvel_id, status),
    KEY idx_loan_applications_stokvel_membership (stokvel_id, membership_id),
    CONSTRAINT fk_loan_applications_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_applications_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_applications_product FOREIGN KEY (loan_product_id) REFERENCES loan_products (id) ON DELETE RESTRICT,
    CONSTRAINT fk_loan_applications_decided_by FOREIGN KEY (decided_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE loans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    loan_application_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    loan_product_id BIGINT UNSIGNED NOT NULL,
    loan_ref VARCHAR(64) NOT NULL,
    principal_amount DECIMAL(15,2) NOT NULL,
    interest_rate_percent DECIMAL(8,4) NOT NULL,
    term_months TINYINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    disbursed_at DATETIME NULL,
    disbursed_amount DECIMAL(15,2) NULL,
    outstanding_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_loans_stokvel_ref (stokvel_id, loan_ref),
    UNIQUE KEY uk_loans_application (loan_application_id),
    KEY idx_loans_stokvel_status (stokvel_id, status),
    KEY idx_loans_stokvel_membership (stokvel_id, membership_id),
    CONSTRAINT fk_loans_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_loans_application FOREIGN KEY (loan_application_id) REFERENCES loan_applications (id) ON DELETE RESTRICT,
    CONSTRAINT fk_loans_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_loans_product FOREIGN KEY (loan_product_id) REFERENCES loan_products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE loan_guarantors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    loan_application_id BIGINT UNSIGNED NOT NULL,
    guarantor_membership_id BIGINT UNSIGNED NOT NULL,
    guaranteed_amount DECIMAL(15,2) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    accepted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_loan_guarantors_stokvel_application (stokvel_id, loan_application_id),
    KEY idx_loan_guarantors_guarantor (stokvel_id, guarantor_membership_id),
    CONSTRAINT fk_loan_guarantors_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_guarantors_application FOREIGN KEY (loan_application_id) REFERENCES loan_applications (id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_guarantors_membership FOREIGN KEY (guarantor_membership_id) REFERENCES memberships (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE loan_schedules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    loan_id BIGINT UNSIGNED NOT NULL,
    instalment_number TINYINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    principal_due DECIMAL(15,2) NOT NULL,
    interest_due DECIMAL(15,2) NOT NULL,
    total_due DECIMAL(15,2) NOT NULL,
    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_loan_schedules_loan_instalment (loan_id, instalment_number),
    KEY idx_loan_schedules_stokvel_due (stokvel_id, due_date),
    KEY idx_loan_schedules_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_loan_schedules_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_schedules_loan FOREIGN KEY (loan_id) REFERENCES loans (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE loan_repayments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    loan_id BIGINT UNSIGNED NOT NULL,
    loan_schedule_id BIGINT UNSIGNED NULL,
    repayment_ref VARCHAR(64) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(32) NOT NULL,
    paid_at DATETIME NOT NULL,
    recorded_by_user_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_loan_repayments_stokvel_ref (stokvel_id, repayment_ref),
    KEY idx_loan_repayments_stokvel_loan (stokvel_id, loan_id),
    KEY idx_loan_repayments_schedule (loan_schedule_id),
    CONSTRAINT fk_loan_repayments_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_repayments_loan FOREIGN KEY (loan_id) REFERENCES loans (id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_repayments_schedule FOREIGN KEY (loan_schedule_id) REFERENCES loan_schedules (id) ON DELETE SET NULL,
    CONSTRAINT fk_loan_repayments_recorded_by FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE payout_cycles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    cycle_code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'planning',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    payout_amount DECIMAL(15,2) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_payout_cycles_stokvel_code (stokvel_id, cycle_code),
    KEY idx_payout_cycles_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_payout_cycles_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE payout_schedule (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    payout_cycle_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    sequence_number INT UNSIGNED NOT NULL,
    scheduled_date DATE NOT NULL,
    payout_amount DECIMAL(15,2) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'scheduled',
    paid_at DATETIME NULL,
    notes VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_payout_schedule_cycle_sequence (payout_cycle_id, sequence_number),
    KEY idx_payout_schedule_stokvel_date (stokvel_id, scheduled_date),
    KEY idx_payout_schedule_stokvel_membership (stokvel_id, membership_id),
    CONSTRAINT fk_payout_schedule_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_payout_schedule_cycle FOREIGN KEY (payout_cycle_id) REFERENCES payout_cycles (id) ON DELETE CASCADE,
    CONSTRAINT fk_payout_schedule_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE distributions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    payout_cycle_id BIGINT UNSIGNED NOT NULL,
    payout_schedule_id BIGINT UNSIGNED NOT NULL,
    distribution_ref VARCHAR(64) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    approved_by_user_id BIGINT UNSIGNED NULL,
    paid_at DATETIME NULL,
    payment_reference VARCHAR(128) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_distributions_stokvel_ref (stokvel_id, distribution_ref),
    UNIQUE KEY uk_distributions_schedule (payout_schedule_id),
    KEY idx_distributions_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_distributions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_distributions_cycle FOREIGN KEY (payout_cycle_id) REFERENCES payout_cycles (id) ON DELETE CASCADE,
    CONSTRAINT fk_distributions_schedule FOREIGN KEY (payout_schedule_id) REFERENCES payout_schedule (id) ON DELETE CASCADE,
    CONSTRAINT fk_distributions_approved_by FOREIGN KEY (approved_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS distributions',
        'DROP TABLE IF EXISTS payout_schedule',
        'DROP TABLE IF EXISTS payout_cycles',
        'DROP TABLE IF EXISTS loan_repayments',
        'DROP TABLE IF EXISTS loan_schedules',
        'DROP TABLE IF EXISTS loan_guarantors',
        'DROP TABLE IF EXISTS loans',
        'DROP TABLE IF EXISTS loan_applications',
        'DROP TABLE IF EXISTS loan_products',
    ],
];
