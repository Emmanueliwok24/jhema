<?php
// admin/partials/functions.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

// Load global config (ROOT/includes/config.php)
require_once __DIR__ . '/../../includes/config.php';

/** Start session safely (idempotent) */
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/** Base URL helper (uses BASE_URL if defined in config.php) */
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $path = ltrim($path, '/');
        return $path ? ($base . '/' . $path) : $base . '/';
    }
}

/** Simple redirect + exit */
if (!function_exists('redirect')) {
    function redirect(string $to): void {
        header('Location: ' . $to);
        exit;
    }
}

/** HTML escape */
if (!function_exists('e')) {
    function e(?string $s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

/** CSRF token (generate + get) */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/** CSRF verify */
if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token): bool {
        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
