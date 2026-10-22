<?php

declare(strict_types=1);

/**
 * Per-member minimum contribution override (falls back to stokvel_settings.minimum_contribution).
 */
return [
    'up' => [
        'ALTER TABLE memberships
            ADD COLUMN minimum_contribution DECIMAL(15,2) NULL
            AFTER notes',
    ],
    'down' => [
        'ALTER TABLE memberships DROP COLUMN minimum_contribution',
    ],
];
