<?php

declare(strict_types=1);

/**
 * Multi-approver decisions + policies, saving goals, approval request amount/threshold snapshot.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS approval_policies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    request_type VARCHAR(64) NULL COMMENT 'NULL = default for all types',
    min_approvals INT UNSIGNED NOT NULL DEFAULT 1,
    amount_threshold DECIMAL(15,2) NULL COMMENT 'When amount >= threshold, this policy applies',
    required_role_codes JSON NULL COMMENT 'Optional role codes that must appear among approvers',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_approval_policies_stokvel (stokvel_id, is_active),
    KEY idx_approval_policies_type (stokvel_id, request_type, amount_threshold),
    CONSTRAINT fk_approval_policies_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS approval_decisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    approval_request_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(16) NOT NULL COMMENT 'approved|rejected',
    reason TEXT NULL,
    decided_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_approval_decisions_request_user (approval_request_id, user_id),
    KEY idx_approval_decisions_stokvel (stokvel_id, approval_request_id),
    CONSTRAINT fk_approval_decisions_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_approval_decisions_request FOREIGN KEY (approval_request_id) REFERENCES approval_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_approval_decisions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        <<<'SQL'
ALTER TABLE approval_requests
    ADD COLUMN amount DECIMAL(15,2) NULL AFTER status,
    ADD COLUMN required_approvals INT UNSIGNED NOT NULL DEFAULT 1 AFTER amount,
    ADD COLUMN approval_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER required_approvals
SQL,
        <<<'SQL'
ALTER TABLE stokvel_settings
    ADD COLUMN default_min_approvals INT UNSIGNED NOT NULL DEFAULT 1 AFTER require_dual_approval
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS saving_goals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    fund_id BIGINT UNSIGNED NULL,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(512) NULL,
    target_amount DECIMAL(15,2) NOT NULL,
    target_date DATE NULL,
    progress_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_saving_goals_stokvel_status (stokvel_id, status),
    CONSTRAINT fk_saving_goals_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_saving_goals_fund FOREIGN KEY (fund_id) REFERENCES stokvel_funds (id) ON DELETE SET NULL,
    CONSTRAINT fk_saving_goals_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        // Sync default_min_approvals from legacy dual-approval flag
        <<<'SQL'
UPDATE stokvel_settings
SET default_min_approvals = IF(require_dual_approval = 1, 2, 1)
WHERE default_min_approvals = 1 AND require_dual_approval = 1
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS saving_goals',
        'ALTER TABLE stokvel_settings DROP COLUMN default_min_approvals',
        'ALTER TABLE approval_requests DROP COLUMN approval_count',
        'ALTER TABLE approval_requests DROP COLUMN required_approvals',
        'ALTER TABLE approval_requests DROP COLUMN amount',
        'DROP TABLE IF EXISTS approval_decisions',
        'DROP TABLE IF EXISTS approval_policies',
    ],
];
