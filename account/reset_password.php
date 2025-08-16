<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/userfunctions.php';

$token = $_GET['token'] ?? '';
$reset = $token ? get_valid_reset($pdo, $token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }
    $token = $_POST['token'] ?? '';
    $reset = $token ? get_valid_reset($pdo, $token) : null;

    $new = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $errors = [];

    if (!$reset) $errors[] = 'Invalid or expired reset link.';
    if (strlen($new) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($new !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        update_user_password($pdo, (int)$reset['user_id'], $new);
        mark_reset_used($pdo, (int)$reset['id']);
        header('Location: /auth/login.php?reset=1');
        exit;
    }
}
?>
<!DOCTYPE html><html><body>
<h3>Choose a new password</h3>
<?php if (!$reset): ?>
  <p>Invalid or expired reset link.</p>
<?php else: ?>
<form method="post">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
  <input name="password" type="password" placeholder="New password (min 8)" required>
  <input name="password_confirm" type="password" placeholder="Confirm new password" required>
  <button>Update Password</button>
</form>
<?php endif; ?>
<?php if (!empty($errors)) echo "<div style='color:red'>".implode('<br>', array_map('htmlspecialchars',$errors))."</div>"; ?>
</body></html>
