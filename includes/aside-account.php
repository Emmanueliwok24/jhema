<?php
// includes/aside-account.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$userName  = $_SESSION['user_name']  ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$initials  = '';
if ($userName) {
  $parts = preg_split('/\s+/', trim($userName));
  $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr(end($parts) ?: '', 0, 1));
}
?>
<!-- Account Sideform -->
<div class="aside aside_right overflow-hidden customer-forms" id="accountAside">
  <div class="customer-forms__wrapper d-flex position-relative">
    <div class="customer__login w-100">
      <div class="aside-header d-flex align-items-center">
        <h3 class="text-uppercase fs-6 mb-0">My Account</h3>
        <button class="btn-close-lg js-close-aside ms-auto"></button>
      </div>
      <!-- /.aside-header -->

      <div class="aside-content">
        <!-- Profile summary -->
        <div class="d-flex align-items-center mb-4">
          <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
            <span class="fw-semibold"><?= htmlspecialchars($initials ?: 'ME') ?></span>
          </div>
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($userName ?: 'Account') ?></div>
            <?php if ($userEmail): ?>
              <div class="small text-secondary"><?= htmlspecialchars($userEmail) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Primary action -->
        <a href="<?= htmlspecialchars(base_url('account/dashboard.php')) ?>" class="btn btn-primary w-100 text-uppercase mb-3">
          Go to Dashboard
        </a>

        <!-- Quick links -->
        <div class="list-group mb-4">
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="<?= htmlspecialchars(base_url('account/dashboard.php?tab=profile')) ?>">
            Edit Profile
            <span class="ms-2">&rsaquo;</span>
          </a>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="<?= htmlspecialchars(base_url('account/dashboard.php?tab=security')) ?>">
            Change Password
            <span class="ms-2">&rsaquo;</span>
          </a>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="<?= htmlspecialchars(base_url('account/account_wishlist.php')) ?>">
            Wishlist
            <span class="ms-2">&rsaquo;</span>
          </a>
          <!-- Add other links (orders, addresses) here when available -->
        </div>

        <!-- Logout -->
        <form method="post" action="<?= htmlspecialchars(base_url('account/dashboard.php')) ?>" class="mt-3">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="logout">
          <button class="btn btn-outline-danger w-100">Logout</button>
        </form>
      </div>
      <!-- /.aside-content -->
    </div>
  </div>
  <!-- /.customer-forms__wrapper -->
</div>
<!-- /.aside -->
