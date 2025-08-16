<?php
// account/request_reset.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';          // gives base_url(), csrf_*
require_once __DIR__ . '/../includes/userfunctions.php';
require_once __DIR__ . '/../includes/mail.php';

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }

    $email = trim($_POST['email'] ?? '');
    $msg   = 'If that email exists, a reset link has been sent. Please check your inbox.';

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $user = find_user_by_email($pdo, $email);
        if ($user) {
            $token = create_password_reset($pdo, (int)$user['id']);

            // Build absolute link using base_url() helper
            $link = base_url('account/reset_password.php') . '?token=' . urlencode($token);

            $html = "<p>Hello " . htmlspecialchars($user['first_name']) . ",</p>
                     <p>Click the button below to reset your password:</p>
                     <p><a href='{$link}' style='background:#1b479e;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none'>Reset Password</a></p>
                     <p>Or copy this link: " . htmlspecialchars($link) . "</p>
                     <p>This link expires in 60 minutes.</p>";

            send_mail($user['email'], 'Password Reset', $html);
        }
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
?>

<?php include("../includes/head.php"); ?>
<?php include("../includes/svg.php"); ?>
<?php include("../includes/mobile-header.php"); ?>
<?php include("../includes/header.php"); ?>

<main class="position-relative">
  <?php include("../scroll_categories.php"); ?>

  <section class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <h3 class="mb-3 text-center">Reset your password</h3>

            <form method="post" class="mb-3">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <div class="mb-3">
                <input name="email" type="email" class="form-control" placeholder="Your account email" required>
              </div>
              <button class="btn btn-primary w-100">Send Reset Link</button>
            </form>

            <?php if ($msg !== null): ?>
              <p class="alert alert-info text-center"><?= htmlspecialchars($msg) ?></p>
            <?php endif; ?>

            <p class="text-center mb-0">
              <a href="<?= base_url('account/auth.php') ?>?tab=login">Back to Login</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include("../includes/footer.php"); ?>
<?php include("../includes/mobile-footer.php"); ?>
<?php include("../includes/aside-form.php"); ?>
<?php include("../includes/cart-aside.php"); ?>
<?php include("../includes/sitemap-nav.php"); ?>
<?php include("../includes/scroll.php"); ?>
<?php include("../includes/script-footer.php"); ?>
