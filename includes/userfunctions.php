<?php
// includes/userfunctions.php
if (!defined('PASSWORD_ARGON2ID') && !defined('PASSWORD_ARGON2I')) {
    // Fallback if Argon2 unavailable; use bcrypt
    define('USE_BCRYPT', true);
}

function hash_password(string $plain): string {
    if (defined('USE_BCRYPT')) {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    return password_hash($plain, PASSWORD_ARGON2ID);
}

function verify_password(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}

function generate_token(int $len = 32): string {
    return bin2hex(random_bytes($len));
}

function find_user_by_email(PDO $pdo, string $email): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([trim(mb_strtolower($email))]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function create_user(PDO $pdo, string $first, string $last, string $email, string $password): int {
    $email = trim(mb_strtolower($email));
    $hash = hash_password($password);
    $stmt = $pdo->prepare("INSERT INTO users (first_name,last_name,email,password_hash) VALUES (?,?,?,?)");
    $stmt->execute([$first, $last, $email, $hash]);
    return (int)$pdo->lastInsertId();
}

function login_user(array $user): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'].' '.$user['last_name'];
}

function logout_user(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function record_login_time(PDO $pdo, int $userId): void {
    $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$userId]);
}

/** Password Reset Token flows */
function create_password_reset(PDO $pdo, int $userId, int $minutesValid = 60): string {
    $token = generate_token(32); // 64 hex chars
    $expires = (new DateTimeImmutable("+{$minutesValid} minutes"))->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)");
    $stmt->execute([$userId, $token, $expires]);
    return $token;
}

function get_valid_reset(PDO $pdo, string $token): ?array {
    $stmt = $pdo->prepare("SELECT pr.*, u.email, u.first_name, u.id AS user_id
                           FROM password_resets pr
                           JOIN users u ON u.id = pr.user_id
                           WHERE pr.token = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mark_reset_used(PDO $pdo, int $id): void {
    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$id]);
}

function update_user_password(PDO $pdo, int $userId, string $newPassword): void {
    $hash = hash_password($newPassword);
    $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")->execute([$hash, $userId]);
}
