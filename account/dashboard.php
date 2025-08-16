<?php
// account/dashboard.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/userfunctions.php';

requireAuth();

$userId = (int)$_SESSION['user_id'];
$activeTab = $_GET['tab'] ?? 'overview';
$success = $error = null;

// Load user
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, created_at, last_login_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  logout_user();
  header('Location: ' . base_url('account/auth.php') . '?tab=login');
  exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $activeTab = 'profile';
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($first === '' || $last === '') {
            $error = 'First and last name are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } else {
            $q = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $q->execute([mb_strtolower($email), $userId]);
            if ($q->fetch()) {
                $error = 'That email is already in use by another account.';
            } else {
                $upd = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, updated_at=NOW() WHERE id=?");
                $upd->execute([$first, $last, mb_strtolower($email), $userId]);
                $success = 'Profile updated successfully.';
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name']  = $user['first_name'].' '.$user['last_name'];
            }
        }
    }

    if ($action === 'change_password') {
        $activeTab = 'security';
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $q = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $q->execute([$userId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if (!$row || !verify_password($current, $row['password_hash'])) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            update_user_password($pdo, $userId, $new);
            $success = 'Password changed successfully.';
        }
    }

    if ($action === 'logout') {
        logout_user();
        header('Location: ' . base_url('account/auth.php') . '?tab=login');
        exit;
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');

function tabActive($name, $active) { return $name === $active ? 'active' : ''; }
function tabShow($name, $active)   { return $name === $active ? 'show active' : ''; }
?>

<?php include("../includes/head.php"); ?>
<?php include("../includes/svg.php"); ?>
<?php include("../includes/mobile-header.php"); ?>
<?php include("../includes/header.php"); ?>

<main class="position-relative">
  <?php include("../scroll_categories.php"); ?>

  <div class="mb-4 pb-4"></div>

  <section class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card shadow-sm border-0">
          <div class="card-body">
            <div class="d-flex align-items-center mb-3">
              <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:64px;height:64px;font-size:1.5rem;">
                <?= htmlspecialchars(strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1))) ?>
              </div>
              <div class="ms-3">
                <h5 class="mb-1"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></h5>
                <div class="text-muted small"><?= htmlspecialchars($user['email']) ?></div>
              </div>
            </div>

            <hr>

            <ul class="list-unstyled small mb-0">
              <li class="d-flex justify-content-between py-2">
                <span class="text-muted">Member since</span>
                <strong><?= htmlspecialchars(date('M j, Y', strtotime($user['created_at']))) ?></strong>
              </li>
              <li class="d-flex justify-content-between py-2">
                <span class="text-muted">Last login</span>
                <strong><?= $user['last_login_at'] ? htmlspecialchars(date('M j, Y H:i', strtotime($user['last_login_at']))) : '—' ?></strong>
              </li>
            </ul>

            <form method="post" class="mt-3">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="logout">
              <button class="btn btn-outline-danger w-100">Logout</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <ul class="nav nav-tabs mb-4" role="tablist">
          <li class="nav-item"><button class="nav-link <?= tabActive('overview',$activeTab) ?>" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">Overview</button></li>
          <li class="nav-item"><button class="nav-link <?= tabActive('profile',$activeTab) ?>" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button">Edit Profile</button></li>
          <li class="nav-item"><button class="nav-link <?= tabActive('security',$activeTab) ?>" data-bs-toggle="tab" data-bs-target="#tab-security" type="button">Change Password</button></li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade <?= tabShow('overview',$activeTab) ?>" id="tab-overview">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h5 class="mb-3">Account Summary</h5>
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                      <div class="text-muted small">Full Name</div>
                      <div class="fw-semibold"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                      <div class="text-muted small">Email</div>
                      <div class="fw-semibold"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                      <div class="text-muted small">Member Since</div>
                      <div class="fw-semibold"><?= htmlspecialchars(date('M j, Y', strtotime($user['created_at']))) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                      <div class="text-muted small">Last Login</div>
                      <div class="fw-semibold"><?= $user['last_login_at'] ? htmlspecialchars(date('M j, Y H:i', strtotime($user['last_login_at']))) : '—' ?></div>
                    </div>
                  </div>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                  <a class="btn btn-primary" href="<?= base_url('account/dashboard.php') ?>?tab=profile">Edit Profile</a>
                  <a class="btn btn-outline-secondary" href="<?= base_url('account/dashboard.php') ?>?tab=security">Change Password</a>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade <?= tabShow('profile',$activeTab) ?>" id="tab-profile">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h5 class="mb-3">Profile Details</h5>
                <form method="post" class="row g-3">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="update_profile">

                  <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($user['first_name']) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($user['last_name']) ?>">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
                    <div class="form-text">Changing your email will be used for future logins and notifications.</div>
                  </div>

                  <div class="col-12">
                    <button class="btn btn-primary">Save Changes</button>
                    <a class="btn btn-outline-secondary" href="<?= base_url('account/dashboard.php') ?>">Cancel</a>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <div class="tab-pane fade <?= tabShow('security',$activeTab) ?>" id="tab-security">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h5 class="mb-3">Change Password</h5>
                <form method="post" class="row g-3">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="change_password">

                  <div class="col-12">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                  </div>

                  <div class="col-12">
                    <button class="btn btn-primary">Update Password</button>
                  </div>
                </form>

                <hr class="my-4">
                <p class="small text-muted mb-0">
                  Tip: use at least 8 characters with a mix of letters, numbers, and symbols.
                </p>
              </div>
            </div>
          </div>
        </div> <!-- /tab-content -->
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
