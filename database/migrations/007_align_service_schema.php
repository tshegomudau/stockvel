<?php

declare(strict_types=1);

/**
 * Align schema with service-layer expectations without breaking seeded data.
 */
return [
    'up' => [
        "ALTER TABLE chart_of_accounts
            ADD COLUMN sub_type VARCHAR(64) NULL AFTER account_type,
            ADD KEY idx_coa_stokvel_subtype (stokvel_id, sub_type)",

        "UPDATE chart_of_accounts SET sub_type = CASE code
            WHEN '1000' THEN 'cash'
            WHEN '1010' THEN 'bank'
            WHEN '1100' THEN 'receivable'
            WHEN '1200' THEN 'loan_receivable'
            WHEN '2000' THEN 'member_credit'
            WHEN '2100' THEN 'payable'
            WHEN '2200' THEN 'payout_payable'
            WHEN '3000' THEN 'fund_balance'
            WHEN '3100' THEN 'retained'
            WHEN '4000' THEN 'contributions'
            WHEN '4100' THEN 'penalties'
            WHEN '4200' THEN 'interest'
            WHEN '4300' THEN 'other_income'
            WHEN '5000' THEN 'admin'
            WHEN '5100' THEN 'meeting'
            WHEN '5200' THEN 'bank_charges'
            WHEN '5300' THEN 'loan_loss'
            WHEN '5400' THEN 'payout'
            WHEN '5500' THEN 'other_expense'
            ELSE LOWER(REPLACE(name, ' ', '_'))
        END
        WHERE sub_type IS NULL",

        "ALTER TABLE fee_structures
            ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER status,
            ADD COLUMN current_version_id BIGINT UNSIGNED NULL AFTER is_active",

        "UPDATE fee_structures SET is_active = CASE WHEN status = 'active' THEN 1 ELSE 0 END",

        "UPDATE fee_structures fs
         SET current_version_id = (
            SELECT fsv.id FROM fee_structure_versions fsv
            WHERE fsv.fee_structure_id = fs.id AND fsv.stokvel_id = fs.stokvel_id
            ORDER BY fsv.version_number DESC LIMIT 1
         )
         WHERE current_version_id IS NULL",

        "ALTER TABLE fee_structure_versions
            ADD COLUMN calculation_type VARCHAR(32) NOT NULL DEFAULT 'fixed' AFTER amount,
            ADD COLUMN percentage_rate DECIMAL(8,4) NULL AFTER calculation_type,
            ADD COLUMN is_mandatory TINYINT(1) NOT NULL DEFAULT 1 AFTER percentage_rate,
            ADD COLUMN grace_period_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER grace_days,
            ADD COLUMN penalty_rate_percent DECIMAL(8,4) NULL AFTER grace_period_days,
            ADD COLUMN penalty_type VARCHAR(32) NULL AFTER penalty_rate_percent,
            ADD COLUMN due_day TINYINT UNSIGNED NULL AFTER penalty_type",

        "UPDATE fee_structure_versions SET grace_period_days = grace_days WHERE grace_period_days = 0 AND grace_days > 0",

        "ALTER TABLE loan_products
            ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER status,
            ADD COLUMN interest_type VARCHAR(32) NOT NULL DEFAULT 'reducing' AFTER interest_rate_percent,
            ADD COLUMN penalty_rate_percent DECIMAL(8,4) NULL AFTER interest_type,
            ADD COLUMN requires_guarantors TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_guarantor,
            ADD COLUMN min_guarantors INT UNSIGNED NOT NULL DEFAULT 0 AFTER requires_guarantors",

        "UPDATE loan_products SET is_active = CASE WHEN status = 'active' THEN 1 ELSE 0 END,
            requires_guarantors = requires_guarantor",

        "ALTER TABLE expense_categories
            ADD COLUMN account_id BIGINT UNSIGNED NULL AFTER name,
            ADD COLUMN budget_amount DECIMAL(15,2) NULL AFTER account_id",
    ],
    'down' => [
        "ALTER TABLE expense_categories DROP COLUMN budget_amount, DROP COLUMN account_id",
        "ALTER TABLE loan_products
            DROP COLUMN min_guarantors,
            DROP COLUMN requires_guarantors,
            DROP COLUMN penalty_rate_percent,
            DROP COLUMN interest_type,
            DROP COLUMN is_active",
        "ALTER TABLE fee_structure_versions
            DROP COLUMN due_day,
            DROP COLUMN penalty_type,
            DROP COLUMN penalty_rate_percent,
            DROP COLUMN grace_period_days,
            DROP COLUMN is_mandatory,
            DROP COLUMN percentage_rate,
            DROP COLUMN calculation_type",
        "ALTER TABLE fee_structures DROP COLUMN current_version_id, DROP COLUMN is_active",
        "ALTER TABLE chart_of_accounts DROP INDEX idx_coa_stokvel_subtype, DROP COLUMN sub_type",
    ],
];
