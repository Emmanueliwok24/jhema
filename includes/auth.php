<?php
// includes/auth.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php'; // provides $baseUrl and (optionally) BASE_URL + base_url()

/**
 * Ensure we always have a base_url() helper available.
 * Uses BASE_URL constant if defined, otherwise falls back to $baseUrl global.
 */
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $base = defined('BASE_URL') ? BASE_URL : ($GLOBALS['baseUrl'] ?? '');
        $base = rtrim((string)$base, '/');   // no trailing slash
        $path = ltrim($path, '/');           // no leading slash
        return $path === '' ? $base . '/' : $base . '/' . $path;
    }
}

/**
 * Tiny redirect helper. Accepts absolute URLs or relative paths.
 */
if (!function_exists('redirect')) {
    function redirect(string $pathOrUrl): void {
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            header('Location: ' . $pathOrUrl);
        } else {
            header('Location: ' . base_url($pathOrUrl));
        }
        exit;
    }
}

/**
 * Is the user logged in?
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        return !empty($_SESSION['user_id']);
    }
}

/**
 * Current user id (or null).
 */
if (!function_exists('current_user_id')) {
    function current_user_id(): ?int {
        return !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}

/**
 * Require the user to be authenticated. If not, send them to login with a return URL.
 *
 * @param string $redirectTo Relative path to your login page with optional query (no leading slash needed).
 *                           Default: 'account/auth.php?tab=login'
 */
if (!function_exists('requireAuth')) {
    function requireAuth(string $redirectTo = 'account/auth.php?tab=login'): void {
        if (!is_logged_in()) {
            $return = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            // If $redirectTo already has a query string, append with &; else with ?
            $sep = (strpos($redirectTo, '?') !== false) ? '&' : '?';
            redirect($redirectTo . $sep . 'redirect=' . $return);
        }
    }
}

/**
 * If the user is already authenticated, redirect them away (e.g., from /login to /dashboard).
 *
 * @param string $redirectTo Relative path to your dashboard/home.
 *                           Default: 'account/dashboard.php'
 */
if (!function_exists('requireGuest')) {
    function requireGuest(string $redirectTo = 'account/dashboard.php'): void {
        if (is_logged_in()) {
            redirect($redirectTo);
        }
    }
}

/**
 * CSRF token helpers
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(?string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}
