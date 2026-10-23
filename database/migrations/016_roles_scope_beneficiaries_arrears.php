<?php

declare(strict_types=1);

/**
 * Role scope (platform vs user), beneficiary approval metadata,
 * and nullable fee version so historical arrears can be captured without a fee structure.
 */
return [
    'up' => [
        "ALTER TABLE roles
            ADD COLUMN scope VARCHAR(32) NOT NULL DEFAULT 'user'
            AFTER is_system",
        "UPDATE roles SET scope = 'user' WHERE stokvel_id IS NULL",
        "UPDATE roles SET scope = 'platform'
         WHERE stokvel_id IS NULL AND code LIKE 'platform_%'",
        "ALTER TABLE beneficiaries
            ADD COLUMN approval_status VARCHAR(32) NOT NULL DEFAULT 'approved'
            AFTER status",
        "ALTER TABLE beneficiaries
            ADD COLUMN source VARCHAR(32) NOT NULL DEFAULT 'committee'
            AFTER approval_status",
        "ALTER TABLE member_charges
            MODIFY fee_structure_version_id BIGINT UNSIGNED NULL",
    ],
    'down' => [
        'ALTER TABLE member_charges
            MODIFY fee_structure_version_id BIGINT UNSIGNED NOT NULL',
        'ALTER TABLE beneficiaries DROP COLUMN source',
        'ALTER TABLE beneficiaries DROP COLUMN approval_status',
        'ALTER TABLE roles DROP COLUMN scope',
    ],
];
