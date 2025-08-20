<?php
// includes/auth_user.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

/**
 * Thin wrapper that guarantees auth helpers are available.
 * Prefer using this in pages that only need "require_user()" protection.
 * It simply loads auth.php (which already loads config + userfunctions).
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

require_once __DIR__ . '/auth.php'; // brings in require_user(), base_url(), etc.
// Nothing else is needed here. Use require_user() where appropriate.
