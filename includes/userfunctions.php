<?php
// includes/userfunctions.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

/**
 * Pure user utilities (no session management here).
 * Provides strong password hashing, user lookups/creation,
 * and password-reset helpers.
 */

declare(strict_types=1);

if (!defined('PASSWORD_ARGON2ID') && !defined('PASSWORD_ARGON2I')) {
    // Fallback if Argon2 is unavailable; bcrypt remains strong with a sane cost.
    if (!defined('USE_BCRYPT')) define('USE_BCRYPT', true);
}

/** Hash a password using Argon2id when available, otherwise bcrypt(cost=12). */
if (!function_exists('hash_password')) {
    function hash_password(string $plain): string {
        if (defined('USE_BCRYPT')) {
            return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
        }
        return password_hash($plain, PASSWORD_ARGON2ID);
    }
}

/** Verify a password */
if (!function_exists('verify_password')) {
    function verify_password(string $plain, string $hash): bool {
        return password_verify($plain, $hash);
    }
}

/** Generate a cryptographically secure token (hex) */
if (!function_exists('generate_token')) {
    function generate_token(int $len = 32): string {
        return bin2hex(random_bytes($len));
    }
}

/** Fetch a user row by email (lowercased, trimmed) */
if (!function_exists('find_user_by_email')) {
    function find_user_by_email(PDO $pdo, string $email): ?array {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([trim(mb_strtolower($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}

/** Create a user and return new user id */
if (!function_exists('create_user')) {
    function create_user(PDO $pdo, string $first, string $last, string $email, string $password): int {
        $email = trim(mb_strtolower($email));
        $hash  = hash_password($password);
        $stmt  = $pdo->prepare(
            "INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?,?,?,?)"
        );
        $stmt->execute([$first, $last, $email, $hash]);
        return (int)$pdo->lastInsertId();
    }
}

/** Update last_login_at (called after successful login) */
if (!function_exists('record_login_time')) {
    function record_login_time(PDO $pdo, int $userId): void {
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$userId]);
    }
}

/** --- Password Reset Token Flows --- */

/** Create password reset token (valid for N minutes) and return token */
if (!function_exists('create_password_reset')) {
    function create_password_reset(PDO $pdo, int $userId, int $minutesValid = 60): string {
        $token   = generate_token(32); // 64 hex chars
        $expires = (new DateTimeImmutable("+{$minutesValid} minutes"))->format('Y-m-d H:i:s');
        $stmt    = $pdo->prepare(
            "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)"
        );
        $stmt->execute([$userId, $token, $expires]);
        return $token;
    }
}

/** Get a valid (unused, unexpired) reset by token, joined with user info */
if (!function_exists('get_valid_reset')) {
    function get_valid_reset(PDO $pdo, string $token): ?array {
        $stmt = $pdo->prepare(
            "SELECT pr.*, u.email, u.first_name, u.id AS user_id
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token = ?
               AND pr.used_at IS NULL
               AND pr.expires_at > NOW()"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

/** Mark a reset row as used */
if (!function_exists('mark_reset_used')) {
    function mark_reset_used(PDO $pdo, int $id): void {
        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$id]);
    }
}

/** Update a user's password */
if (!function_exists('update_user_password')) {
    function update_user_password(PDO $pdo, int $userId, string $newPassword): void {
        $hash = hash_password($newPassword);
        $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$hash, $userId]);
    }
}
