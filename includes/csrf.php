<?php
// includes/csrf.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

/**
 * One-time token per $key. Store in $_SESSION['csrf'][$key].
 */
if (!function_exists('csrf_token')) {
  function csrf_token(string $key = 'default'): string {
    if (!isset($_SESSION['csrf']) || !is_array($_SESSION['csrf'])) {
      $_SESSION['csrf'] = [];
    }
    if (empty($_SESSION['csrf'][$key])) {
      $_SESSION['csrf'][$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'][$key];
  }
}

if (!function_exists('csrf_check')) {
  function csrf_check(?string $token, string $key = 'default'): bool {
    if (!is_string($token) || $token === '') return false;
    if (!isset($_SESSION['csrf'][$key]))    return false;
    $ok = hash_equals($_SESSION['csrf'][$key], $token);
    if ($ok) unset($_SESSION['csrf'][$key]); // one-time use
    return $ok;
  }
}

/** Convenience: echo a hidden input with the CSRF token for $key. */
if (!function_exists('csrf_input')) {
  function csrf_input(string $key = 'default'): string {
    $t = htmlspecialchars(csrf_token($key), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="'.$t.'">';
  }
}
