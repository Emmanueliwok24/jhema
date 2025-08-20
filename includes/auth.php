<?php
// includes/auth.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

declare(strict_types=1);

/**
 * Session + web auth helpers used across the app.
 * - Starts session
 * - Loads config for BASE_URL and $pdo
 * - Provides base_url(), CSRF, session/user helpers
 * - Gatekeepers require_user()/require_guest()
 * - Login/logout helpers
 * - SAFE fallbacks for password utilities (wrapped with function_exists)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

require_once __DIR__ . '/config.php';           // provides BASE_URL (optional) and $pdo (PDO)
require_once __DIR__ . '/userfunctions.php';    // preferred source of hash/verify helpers

/* ---------------- base_url helper ---------------- */
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
<<<<<<< HEAD
        $base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '/';
        return $base . ltrim($path, '/');
=======
        $base = defined('BASE_URL') ? BASE_URL : ($GLOBALS['baseUrl'] ?? '');
        $base = rtrim((string)$base, '/');   // no trailing slash
        $path = ltrim($path, '/');           // no leading slash
        return $path === '' ? $base . '/' : $base . '/' . $path;
        
>>>>>>> 401487e218495406067ed3b23b85daf781e40d01
    }
}

/* ---------------- CSRF ---------------- */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}
if (!function_exists('csrf_check')) {
    function csrf_check(?string $token): bool {
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }
}

/* ---------------- Session-level user helpers ---------------- */
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        return !empty($_SESSION['user_id']);
    }
}
if (!function_exists('current_user_id')) {
    function current_user_id(): int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    }
}
if (!function_exists('current_user_email')) {
    function current_user_email(): ?string {
        return $_SESSION['user_email'] ?? null;
    }
}
if (!function_exists('current_user_name')) {
    function current_user_name(): ?string {
        return $_SESSION['user_name'] ?? null;
    }
}

/* ---------------- Gatekeepers (PRIMARY + shims) ---------------- */
if (!function_exists('require_user')) {
    function require_user(): void {
        if (empty($_SESSION['user_id'])) {
            $next = $_SERVER['REQUEST_URI'] ?? base_url('account/dashboard.php');
            header('Location: ' . base_url('account/auth.php?tab=login&redirect=' . urlencode($next)));
            exit;
        }
    }
}
if (!function_exists('require_guest')) {
    function require_guest(): void {
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . base_url('account/dashboard.php'));
            exit;
        }
    }
}
/* Back-compat alias names, if some pages still call them */
if (!function_exists('requireAuth'))  { function requireAuth(): void  { require_user(); } }
if (!function_exists('requireGuest')) { function requireGuest(): void { require_guest(); } }

/* ---------------- Login / Logout helpers ---------------- */
if (!function_exists('login_user')) {
    /**
     * Set the standard session keys used across the app.
     * Accepts either a full user row or minimal fields.
     */
    function login_user(array $user): void {
        $_SESSION['user_id']    = (int)($user['id'] ?? 0);
        $_SESSION['user_email'] = (string)($user['email'] ?? '');
        $first = trim((string)($user['first_name'] ?? ''));
        $last  = trim((string)($user['last_name']  ?? ''));
        $_SESSION['user_name']  = trim($first . ' ' . $last) ?: (string)($user['name'] ?? '');

        // Initialize counters used by UI
        if (!isset($_SESSION['cart_count'])) $_SESSION['cart_count'] = 0;
    }
}

if (!function_exists('logout_user')) {
    function logout_user(): void {
        $keep = ['_csrf' => $_SESSION['_csrf'] ?? null];
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        @session_destroy();
        @session_start();
        foreach ($keep as $k => $v) {
            if ($v !== null) $_SESSION[$k] = $v;
        }
    }
}

/* ---------------- SAFE fallbacks (only if userfunctions.php didn't define) ---------------- */
if (!function_exists('verify_password')) {
    function verify_password(string $plain, string $hash): bool {
        return password_verify($plain, $hash);
    }
}
if (!function_exists('hash_password')) {
    function hash_password(string $plain): string {
        // PASSWORD_DEFAULT maps to a secure algo (may be Argon or bcrypt depending on PHP)
        return password_hash($plain, PASSWORD_DEFAULT);
    }
}
if (!function_exists('record_login_time')) {
    function record_login_time(PDO $pdo, int $userId): void {
        try {
            $q = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $q->execute([$userId]);
        } catch (Throwable $e) {
            // don't block login on logging failure
            error_log('[record_login_time] ' . $e->getMessage());
        }
    }
}

/* ---------------- Optional: cart counter sync (for header badges) ---------------- */
if (!function_exists('sync_cart_count')) {
    function sync_cart_count(PDO $pdo): int {
        $uid = current_user_id();
        if ($uid <= 0) {
            $_SESSION['cart_count'] = 0;
            return 0;
        }
        try {
            $st = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ?");
            $st->execute([$uid]);
            $count = (int)$st->fetchColumn();
            $_SESSION['cart_count'] = $count;
            return $count;
        } catch (Throwable $e) {
            // fall back to existing session value if DB unavailable
            return (int)($_SESSION['cart_count'] ?? 0);
        }
    }
}
