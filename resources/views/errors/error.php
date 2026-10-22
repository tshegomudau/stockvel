<?php
/**
 * Legacy path — application views live under app/Views/errors/error.php.
 * Kept in sync so accidental base-path changes still get production-safe pages.
 */
require dirname(__DIR__, 3) . '/app/Views/errors/error.php';
