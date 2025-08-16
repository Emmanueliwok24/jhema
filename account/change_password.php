<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/userfunctions.php';

requireAuth();

$notice = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        // load user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !verify_password($current, $user['password_hash'])) {
            $error = 'Your current password is incorrect.';
        } else {
            update_user_password($pdo, (int)$user['id'], $new);
            $notice = 'Password updated successfully.';
        }
    }
}
?>
<!DOCTYPE html><html><body>
<h3>Change Password</h3>
<form method="post">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  <input name="current_password" type="password" placeholder="Current password" required>
  <input name="new_password" type="password" placeholder="New password (min 8)" required>
  <input name="confirm_password" type="password" placeholder="Confirm new password" required>
  <button>Change Password</button>
</form>
<?php
if ($notice) echo "<p style='color:green'>".htmlspecialchars($notice)."</p>";
if ($error)  echo "<p style='color:red'>".htmlspecialchars($error)."</p>";
?>
</body></html>
