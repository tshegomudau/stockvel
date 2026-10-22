<?php

declare(strict_types=1);

/**
 * Migration 010 — SA market modules: burial society claims and grocery cycles.
 * Competitive parity with local platforms (Sisonke, CommunityFlow, Stok-Vel).
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS burial_claims (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    claim_ref VARCHAR(64) NOT NULL,
    deceased_name VARCHAR(200) NOT NULL,
    relationship VARCHAR(64) NOT NULL DEFAULT 'member',
    date_of_death DATE NULL,
    claimed_amount DECIMAL(15,2) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'submitted',
    notes TEXT NULL,
    submitted_by_user_id BIGINT UNSIGNED NOT NULL,
    decided_by_user_id BIGINT UNSIGNED NULL,
    decided_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_burial_claims_stokvel_ref (stokvel_id, claim_ref),
    KEY idx_burial_claims_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_burial_claims_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_burial_claims_membership FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE,
    CONSTRAINT fk_burial_claims_submitter FOREIGN KEY (submitted_by_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_burial_claims_decider FOREIGN KEY (decided_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS grocery_cycles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    cycle_ref VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    target_amount DECIMAL(15,2) NULL,
    distribution_date DATE NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_grocery_cycles_stokvel_ref (stokvel_id, cycle_ref),
    KEY idx_grocery_cycles_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_grocery_cycles_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_grocery_cycles_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS grocery_cycle_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    grocery_cycle_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    unit VARCHAR(32) NULL,
    estimated_cost DECIMAL(15,2) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_grocery_items_cycle (grocery_cycle_id),
    CONSTRAINT fk_grocery_items_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_grocery_items_cycle FOREIGN KEY (grocery_cycle_id) REFERENCES grocery_cycles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS grocery_cycle_items',
        'DROP TABLE IF EXISTS grocery_cycles',
        'DROP TABLE IF EXISTS burial_claims',
    ],
];
