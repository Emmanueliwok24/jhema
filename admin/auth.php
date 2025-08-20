<?php
// admin/auth.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/partials/functions.php';
require_once __DIR__ . '/partials/auth.php';

$errors = [];
$success = null;

// Already logged in? Go to dashboard
if (admin_logged_in()) {
    redirect(base_url('admin/index.php'));
}

// Handle POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!csrf_verify($token)) {
        $errors[] = 'Security check failed. Please refresh and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        [$ok, $err] = admin_login($username, $password);
        if ($ok) {
            // OLD (example): $dest = $_SESSION['admin_redirect_after_login'] ?? base_url('admin/index.php');
// NEW:
            $dest = $_SESSION['admin_redirect_after_login'] ?? base_url(admin_home_path());
            unset($_SESSION['admin_redirect_after_login']);
            redirect($dest);

        } else {
            $errors[] = $err ?: 'Login failed.';
        }
    }
}

// Page HTML (you can style with your theme partials if desired)
?>
<?php include __DIR__ . '/partials/head.php'; ?>

<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php
  // If you have a special minimal header for login, include it here.
  // For a clean login screen, we usually omit sidebar/page header.
  ?>

  <div class="page-body-wrapper" style="min-height:100vh; display:flex; align-items:center; justify-content:center;">
    <div class="container" style="max-width:420px; width:100%;">
      <div class="card o-hidden card-hover">
        <div class="card-header border-0">
          <h4 class="mb-0">Admin Login</h4>
        </div>
        <div class="card-body">
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <?php foreach ($errors as $e) echo '<div>'.e($e).'</div>'; ?>
            </div>
          <?php endif; ?>

          <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?php echo e(csrf_token()); ?>" />
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input name="username" type="text" class="form-control" placeholder="Enter username" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input name="password" type="password" class="form-control" placeholder="Enter password" required>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Sign In</button>
            </div>
          </form>

          <div class="mt-3 small text-muted">
            Tip: use <code>JhemaAdmin</code> / <code>@@bespoke@@</code>.
            it will be removed before hosting
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/script-js.php'; ?>
