<?php

declare(strict_types=1);

/**
 * Staging table for contribution edits that require maker-checker approval.
 */
return [
    'up' => [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS contribution_edit_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stokvel_id BIGINT UNSIGNED NOT NULL,
    payment_transaction_id BIGINT UNSIGNED NOT NULL,
    requested_by_user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    payload_json JSON NOT NULL,
    decision_notes TEXT NULL,
    decided_at DATETIME NULL,
    decided_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cer_stokvel_status (stokvel_id, status),
    KEY idx_cer_payment_status (payment_transaction_id, status),
    CONSTRAINT fk_cer_stokvel FOREIGN KEY (stokvel_id) REFERENCES stokvels (id) ON DELETE CASCADE,
    CONSTRAINT fk_cer_payment FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions (id) ON DELETE CASCADE,
    CONSTRAINT fk_cer_requester FOREIGN KEY (requested_by_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_cer_decider FOREIGN KEY (decided_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TABLE IF EXISTS contribution_edit_requests',
    ],
];
