<?php
// account/reset_password.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';          // csrf_token(), csrf_check()
require_once __DIR__ . '/../includes/userfunctions.php';
require_once __DIR__ . '/../includes/mail.php';

$errors = [];
$token  = $_GET['token'] ?? '';
$reset  = $token ? get_valid_reset($pdo, $token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }

    $token   = $_POST['token'] ?? '';
    $reset   = $token ? get_valid_reset($pdo, $token) : null;
    $new     = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (!$reset)                      $errors[] = 'Invalid or expired reset link.';
    if (strlen($new) < 8)             $errors[] = 'Password must be at least 8 characters.';
    if ($new !== $confirm)            $errors[] = 'Passwords do not match.';

    if (!$errors) {
        update_user_password($pdo, (int)$reset['user_id'], $new);
        mark_reset_used($pdo, (int)$reset['id']);
        header('Location: ' . base_url('account/auth.php') . '?tab=login&reset=1');
        exit;
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<?php include __DIR__ . '/../includes/head.php'; ?>
<?php include __DIR__ . '/../includes/svg.php'; ?>
<?php include __DIR__ . '/../includes/mobile-header.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="position-relative">
  <?php include __DIR__ . '/../scroll_categories.php'; ?>

  <section class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <h3 class="mb-3 text-center">Choose a new password</h3>

            <?php if (!empty($errors)): ?>
              <div class="alert alert-danger">
                <ul class="mb-0">
                  <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <?php if (!$reset): ?>
              <div class="alert alert-warning">This reset link is invalid or has expired. Please request a new one.</div>
              <div class="text-center">
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(base_url('account/request_reset.php')) ?>">Request new link</a>
              </div>
            <?php else: ?>
              <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="mb-3">
                  <label class="form-label" for="password">New password</label>
                  <div class="input-group">
                    <input
                      id="password"
                      name="password"
                      type="password"
                      class="form-control"
                      minlength="8"
                      placeholder="At least 8 characters"
                      required
                      aria-describedby="pwdHelp">
                    <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                      <i class="fas fa-eye"></i>
                    </button>
                  </div>
                  <div id="pwdHelp" class="form-text">Make it strong: use letters, numbers, and symbols.</div>
                  <div class="invalid-feedback">Please enter a password of at least 8 characters.</div>
                </div>

                <div class="mb-3">
                  <label class="form-label" for="password_confirm">Confirm new password</label>
                  <div class="input-group">
                    <input
                      id="password_confirm"
                      name="password_confirm"
                      type="password"
                      class="form-control"
                      minlength="8"
                      placeholder="Re-type your password"
                      required>
                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirm">
                      <i class="fas fa-eye"></i>
                    </button>
                  </div>
                  <div class="form-text" id="matchHint"></div>
                  <div class="invalid-feedback">Please confirm your password.</div>
                </div>

                <button class="btn btn-primary w-100">Update Password</button>
              </form>

              <p class="text-center mt-3 mb-0">
                <a href="<?= htmlspecialchars(base_url('account/auth.php')) ?>?tab=login">Back to Login</a>
              </p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/mobile-footer.php'; ?>
<?php include __DIR__ . '/../includes/aside-form.php'; ?>
<?php include __DIR__ . '/../includes/cart-aside.php'; ?>
<?php include __DIR__ . '/../includes/sitemap-nav.php'; ?>
<?php include __DIR__ . '/../includes/scroll.php'; ?>
<?php include __DIR__ . '/../includes/script-footer.php'; ?>

<script>
// Bootstrap client-side validation
(() => {
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', (e) => {
      if (!form.checkValidity()) {
        e.preventDefault(); e.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();

// Show/Hide password toggles
function toggleVisibility(inputId, btnId) {
  const input = document.getElementById(inputId);
  const btn   = document.getElementById(btnId);
  if (!input || !btn) return;

  btn.addEventListener('click', () => {
    const isPwd = input.type === 'password';
    input.type = isPwd ? 'text' : 'password';
    const icon = btn.querySelector('i');
    if (icon) {
      icon.classList.toggle('fa-eye', !isPwd);
      icon.classList.toggle('fa-eye-slash', isPwd);
    }
    input.focus();
  });
}
toggleVisibility('password', 'togglePwd');
toggleVisibility('password_confirm', 'toggleConfirm');

// Live match hint
const pwd = document.getElementById('password');
const confirm = document.getElementById('password_confirm');
const hint = document.getElementById('matchHint');

function updateMatchHint() {
  if (!pwd || !confirm || !hint) return;
  if (!confirm.value) { hint.textContent = ''; return; }
  if (pwd.value === confirm.value) {
    hint.textContent = 'Passwords match.';
    hint.classList.remove('text-danger');
    hint.classList.add('text-success');
  } else {
    hint.textContent = 'Passwords do not match.';
    hint.classList.remove('text-success');
    hint.classList.add('text-danger');
  }
}
pwd?.addEventListener('input', updateMatchHint);
confirm?.addEventListener('input', updateMatchHint);
</script>
