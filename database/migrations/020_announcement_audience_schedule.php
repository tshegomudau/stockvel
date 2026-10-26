<?php

declare(strict_types=1);

/**
 * Migration 020 — Widen announcement audience for role targeting; support scheduling.
 */
return [
    'up' => [
        "ALTER TABLE announcements MODIFY audience VARCHAR(512) NOT NULL DEFAULT 'all'",
        // status already free-form VARCHAR; scheduled is application-level
    ],
    'down' => [
        "ALTER TABLE announcements MODIFY audience VARCHAR(32) NOT NULL DEFAULT 'all'",
    ],
];
