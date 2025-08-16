<?php
// account/auth.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/userfunctions.php';

requireGuest(); // if already logged in, send them to dashboard

// Keep $baseUrl (with trailing slash) from config.php
$baseUrl = rtrim($baseUrl ?? '', '/') . '/';

// Set active tab from query (?tab=login|register), default login
$activeTab = ($_GET['tab'] ?? 'login') === 'register' ? 'register' : 'login';

$loginErrors = $registerErrors = [];
$flashSuccess = null;

// Show success message if redirected after registration (?registered=1)
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $activeTab = 'login';
    $flashSuccess = 'Registration successful — please log in to your dashboard.';
}

/** Build a safe redirect target using $baseUrl */
function build_redirect_target(string $raw, string $baseUrl): string {
    if (preg_match('#^https?://#i', $raw)) return $raw;      // full URL
    $raw = '/' . ltrim($raw, '/');                           // normalize
    return rtrim($baseUrl, '/') . $raw;                      // prefix
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }

    $which = $_POST['form'] ?? '';
    $redirectParam = isset($_GET['redirect']) ? ('&redirect=' . urlencode($_GET['redirect'])) : '';

    if ($which === 'login') {
        $activeTab = 'login';
        $email = trim($_POST['login_email'] ?? '');
        $pass  = $_POST['login_password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $loginErrors[] = 'Enter a valid email.';
        if ($pass === '') $loginErrors[] = 'Password is required.';

        if (!$loginErrors) {
            $user = find_user_by_email($pdo, $email);
            if ($user && verify_password($pass, $user['password_hash'])) {
                login_user($user);
                record_login_time($pdo, (int)$user['id']);

                $rawTo = $_GET['redirect'] ?? ($baseUrl);
                $to = preg_match('#^https?://#i', $rawTo) ? $rawTo : build_redirect_target($rawTo, $baseUrl);
                header("Location: {$to}");
                exit;
            }
            $loginErrors[] = 'Invalid email or password.';
        }
    } elseif ($which === 'register') {
        $activeTab = 'register';
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['register_email'] ?? '');
        $pass  = $_POST['register_password'] ?? '';

        if ($first === '' || $last === '') $registerErrors[] = 'First and last name are required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $registerErrors[] = 'Enter a valid email.';
        if (strlen($pass) < 8) $registerErrors[] = 'Password must be at least 8 characters.';

        if (!$registerErrors) {
            if (find_user_by_email($pdo, $email)) {
                $registerErrors[] = 'That email is already registered.';
            } else {
                $userId = create_user($pdo, $first, $last, $email, $pass);
                header('Location: ' . $baseUrl . 'account/auth.php?tab=login&registered=1' . $redirectParam);
                exit;
            }
        }
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');

function isActive($tab, $active) { return $tab === $active ? 'active' : ''; }
function isShow($tab, $active)   { return $tab === $active ? 'show active' : ''; }
?>

<?php include("../includes/head.php"); ?>
<?php include("../includes/svg.php"); ?>
<?php include("../includes/mobile-header.php"); ?>
<?php include("../includes/header.php"); ?>

<main class="position-relative">
  <?php include("../scroll_categories.php"); ?>

  <div class="mb-4 pb-4"></div>

  <section class="login-register container">
    <h2 class="d-none">Login & Register</h2>

    <ul class="nav nav-tabs mb-5" id="login_register" role="tablist">
      <li class="nav-item" role="presentation">
        <a class="nav-link nav-link_underscore <?= isActive('login', $activeTab) ?>" id="login-tab" data-bs-toggle="tab" href="#tab-item-login" role="tab" aria-controls="tab-item-login" aria-selected="<?= $activeTab==='login'?'true':'false' ?>">Login</a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link nav-link_underscore <?= isActive('register', $activeTab) ?>" id="register-tab" data-bs-toggle="tab" href="#tab-item-register" role="tab" aria-controls="tab-item-register" aria-selected="<?= $activeTab==='register'?'true':'false' ?>">Register</a>
      </li>
    </ul>

    <div class="tab-content pt-2" id="login_register_tab_content">
      <!-- LOGIN TAB -->
      <div class="tab-pane fade <?= isShow('login', $activeTab) ?>" id="tab-item-login" role="tabpanel" aria-labelledby="login-tab">
        <div class="login-form">
          <?php if ($flashSuccess && $activeTab === 'login'): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
          <?php endif; ?>
          <?php if ($loginErrors): ?>
            <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $loginErrors)); ?></div>
          <?php endif; ?>

          <form name="login-form" method="post" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="form" value="login">

            <div class="form-floating mb-3">
              <input name="login_email" type="email" class="form-control form-control_gray" id="customerEmailLoginInput" placeholder="Email address *" required>
              <label for="customerEmailLoginInput">Email address *</label>
            </div>

            <div class="pb-3"></div>

            <div class="form-floating mb-3">
              <input name="login_password" type="password" class="form-control form-control_gray" id="customerPasswordLoginInput" placeholder="Password *" required>
              <label for="customerPasswordLoginInput">Password *</label>
            </div>

            <div class="d-flex align-items-center mb-3 pb-2">
              <div class="form-check mb-0">
                <input name="remember" class="form-check-input form-check-input_fill" type="checkbox" value="1" id="flexCheckRemember">
                <label class="form-check-label text-secondary" for="flexCheckRemember">Remember me</label>
              </div>
              <a href="<?= $baseUrl ?>account/request_reset.php" class="btn-text ms-auto">Lost password?</a>
            </div>

            <button class="btn btn-primary w-100 text-uppercase" type="submit">Log In</button>

            <div class="customer-option mt-4 text-center">
              <span class="text-secondary">No account yet?</span>
              <a href="#register-tab" class="btn-text" data-bs-toggle="tab" onclick="document.getElementById('register-tab').click();">Create Account</a>
            </div>
          </form>
        </div>
      </div>

      <!-- REGISTER TAB -->
      <div class="tab-pane fade <?= isShow('register', $activeTab) ?>" id="tab-item-register" role="tabpanel" aria-labelledby="register-tab">
        <div class="register-form">
          <?php if ($registerErrors): ?>
            <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $registerErrors)); ?></div>
          <?php endif; ?>
          <form name="register-form" method="post" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="form" value="register">

            <div class="form-floating mb-3">
              <input name="first_name" type="text" class="form-control form-control_gray" id="firstNameRegisterInput" placeholder="First Name" required>
              <label for="firstNameRegisterInput">First Name</label>
            </div>

            <div class="pb-3"></div>

            <div class="form-floating mb-3">
              <input name="last_name" type="text" class="form-control form-control_gray" id="lastNameRegisterInput" placeholder="Last Name" required>
              <label for="lastNameRegisterInput">Last Name</label>
            </div>

            <div class="pb-3"></div>

            <div class="form-floating mb-3">
              <input name="register_email" type="email" class="form-control form-control_gray" id="customerEmailRegisterInput" placeholder="Email address *" required>
              <label for="customerEmailRegisterInput">Email address *</label>
            </div>

            <div class="pb-3"></div>

            <div class="form-floating mb-3">
              <input name="register_password" type="password" class="form-control form-control_gray" id="customerPasswordRegisterInput" placeholder="Password *" required>
              <label for="customerPasswordRegisterInput">Password *</label>
            </div>

            <div class="d-flex align-items-center mb-3 pb-2">
              <p class="m-0">Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our privacy policy.</p>
            </div>

            <button class="btn btn-primary w-100 text-uppercase" type="submit">Register</button>
          </form>
        </div>
      </div>
    </div>
  </section>
</main>

<div class="mb-5 pb-xl-5"></div>

<?php include("../includes/footer.php"); ?>
<?php include("../includes/mobile-footer.php"); ?>
<?php include("../includes/aside-form.php"); ?>
<?php include("../includes/cart-aside.php"); ?>
<?php include("../includes/sitemap-nav.php"); ?>
<?php include("../includes/scroll.php"); ?>
<?php include("../includes/script-footer.php"); ?>
