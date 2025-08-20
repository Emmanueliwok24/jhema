<?php
// account/dashboard.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/userfunctions.php';
require_once __DIR__ . '/../includes/order_helpers.php'; // order_status_human, order_status_message, money_disp, order_public_number, load_order_history

require_user();

$userId    = (int)$_SESSION['user_id'];
// Default to Orders so it “naturally” shows the list first time.
$activeTab = $_GET['tab'] ?? 'orders';
$success   = $error = null;

/* ---------- Load user ---------- */
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, created_at, last_login_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  logout_user();
  header('Location: ' . base_url('account/auth.php') . '?tab=login');
  exit;
}

/* ---------- Shared tracking helpers (same as tracking page) ---------- */
if (!function_exists('jhema_status_flow')) {
  function jhema_status_flow(): array {
    return ['pending','awaiting_payment','paid','processing','shipped','in_transit','delivered','completed'];
  }
}
if (!function_exists('jhema_progress_from_history')) {
  /**
   * Compute progress strictly from timeline; fall back to current status if needed.
   * Returns: ['flow','index','percent','is_terminal','terminal_key']
   */
  function jhema_progress_from_history(array $history, string $currentStatus): array {
    $flow     = jhema_status_flow();
    $indexMap = array_flip($flow);
    $idx = 0; $terminal = false; $terminalHit = null;

    if ($history) {
      foreach ($history as $ev) {
        $s = strtolower(trim((string)($ev['status'] ?? '')));
        if (isset($indexMap[$s])) $idx = max($idx, (int)$indexMap[$s]);
        if (in_array($s, ['cancelled','failed','refunded'], true)) { $terminal = true; $terminalHit = $s; }
      }
    } else {
      $s = strtolower(trim($currentStatus));
      if (isset($indexMap[$s])) $idx = (int)$indexMap[$s];
      if (in_array($s, ['cancelled','failed','refunded'], true)) { $terminal = true; $terminalHit = $s; }
    }

    $steps = max(1, count($flow));
    $pct   = ($steps > 1) ? (int)round(($idx / ($steps - 1)) * 100) : 0;

    return ['flow'=>$flow,'index'=>$idx,'percent'=>$pct,'is_terminal'=>$terminal,'terminal_key'=>$terminalHit];
  }
}

/* ---------- Orders (load regardless so content is ready) ---------- */
$orders = [];
try {
  // Simple and reliable: list orders owned by the user.
  $q = $pdo->prepare("
    SELECT o.id, o.order_number, o.status, o.total_amount,
           COALESCE(o.currency,'NGN') AS currency, o.created_at, o.updated_at
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 100
  ");
  $q->execute([$userId]);
  $orders = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e2) {
  $error = 'Unable to load your orders at the moment.';
}

/* Enrich each order with timeline-driven progress + derived status label */
foreach ($orders as &$o) {
  $hist = load_order_history($pdo, (int)$o['id']); // [['status','created_at','note'], ...]
  $prog = jhema_progress_from_history($hist, (string)$o['status']);

  // Derive a status key from the last timeline event if orders.status is empty
  $derivedStatusKey = (string)($o['status'] ?? '');
  if ($derivedStatusKey === '') {
    if ($hist) {
      $last = end($hist);
      $derivedStatusKey = (string)($last['status'] ?? '');
    }
  }
  $o['_status_key']       = $derivedStatusKey !== '' ? $derivedStatusKey : (string)$o['status'];
  $o['_status_label']     = order_status_human($o['_status_key'] ?: (string)$o['status']);
  $o['_status_message']   = order_status_message($o['_status_key'] ?: (string)$o['status']);

  $o['_progress_index']   = $prog['index'];      // NEW: expose index for monotonic badge
  $o['_progress_percent'] = $prog['percent'];
  $o['_is_terminal']      = $prog['is_terminal'];
  $o['_terminal_key']     = $prog['terminal_key'];
}
unset($o);

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    http_response_code(400); exit('Invalid CSRF token');
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
      if ($q->fetch()) { $error = 'That email is already in use by another account.'; }
      else {
        $upd = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, updated_at=NOW() WHERE id=?");
        $upd->execute([$first, $last, mb_strtolower($email), $userId]);
        $success = 'Profile updated successfully.';
        $stmt->execute([$userId]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
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
    $q->execute([$userId]); $row = $q->fetch(PDO::FETCH_ASSOC);

    if (!$row || !verify_password($current, $row['password_hash'])) $error = 'Your current password is incorrect.';
    elseif (strlen($new) < 8) $error = 'New password must be at least 8 characters.';
    elseif ($new !== $confirm) $error = 'New passwords do not match.';
    else { update_user_password($pdo, $userId, $new); $success = 'Password changed successfully.'; }
  }

  if ($action === 'logout') {
    logout_user(); header('Location: ' . base_url('account/auth.php') . '?tab=login'); exit;
  }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');

function tabActive($name, $active) { return $name === $active ? 'active' : ''; }
function tabShow($name, $active)   { return $name === $active ? 'show active' : ''; }

function order_status_badge_from_key(string $statusKey): string {
  $s = strtolower(trim($statusKey));
  $cls = 'secondary';
  if (in_array($s, ['pending','awaiting_payment'])) $cls='warning';
  elseif (in_array($s, ['paid','processing']))      $cls='info';
  elseif (in_array($s, ['shipped','in_transit']))   $cls='primary';
  elseif (in_array($s, ['delivered','completed']))  $cls='success';
  elseif (in_array($s, ['cancelled','failed','refunded'])) $cls='danger';
  return '<span class="badge text-bg-'.$cls.'">'.htmlspecialchars(order_status_human($s)).'</span>';
}

/* Money helper (preserve ₦) */
function money_disp2($amt, $currency='NGN'): string {
  $prefix = ($currency === 'NGN' || $currency === '₦') ? '₦' : '';
  return $prefix . number_format((float)$amt, 2);
}
?>
<?php include("../includes/head.php"); ?>
<?php include("../includes/svg.php"); ?>
<?php include("../includes/mobile-header.php"); ?>
<?php include("../includes/header.php"); ?>

<main class="position-relative">
  <?php include("../scroll_categories.php"); ?>

  <div class="mb-4 pb-4"></div>

  <section class="container">

    <!-- Nude / B&W tweaks just for this page -->
    <style>
      .btn-nude-dark{background:#111;color:#fff;border:1px solid #000;}
      .btn-nude-dark:hover{filter:brightness(1.05);}
      .btn-nude-outline{background:#fff;color:#000;border:1px solid #000;}
      .btn-nude-outline:hover{background:#000;color:#fff;}
      .table thead th{border-bottom:1px solid #eee;}
      .progress{background:#eee;}
      .progress-bar{background:#000;}
      .card{border:1px solid #eee;}
    </style>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card shadow-sm border-0">
          <div class="card-body">
            <div class="d-flex align-items-center mb-3">
              <div class="rounded-circle bg-black text-white d-flex align-items-center justify-content-center" style="width:64px;height:64px;font-size:1.5rem;">
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
              <button class="btn btn-nude-outline w-100">Logout</button>
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
          <li class="nav-item"><button class="nav-link <?= tabActive('orders',$activeTab) ?>" data-bs-toggle="tab" data-bs-target="#tab-orders" type="button">Orders</button></li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade <?= tabShow('overview',$activeTab) ?>" id="tab-overview">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h5 class="mb-3">Account Summary</h5>
                <div class="d-flex flex-wrap gap-2">
                  <a class="btn btn-nude-dark" href="<?= base_url('account/dashboard.php') ?>?tab=orders">View Orders</a>
                  <a class="btn btn-nude-outline" href="<?= base_url('account/dashboard.php') ?>?tab=profile">Edit Profile</a>
                  <a class="btn btn-nude-outline" href="<?= base_url('account/dashboard.php') ?>?tab=security">Change Password</a>
                  <a class="btn btn-nude-outline" href="<?= base_url('account/ordertracking.php') ?>">Track an Order</a>
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
                  <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($user['first_name']) ?>"></div>
                  <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name"  class="form-control" required value="<?= htmlspecialchars($user['last_name']) ?>"></div>
                  <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>"></div>
                  <div class="col-12"><button class="btn btn-nude-dark">Save Changes</button> <a class="btn btn-nude-outline" href="<?= base_url('account/dashboard.php') ?>">Cancel</a></div>
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
                  <div class="col-12"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                  <div class="col-md-6"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" minlength="8" required></div>
                  <div class="col-md-6"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" minlength="8" required></div>
                  <div class="col-12"><button class="btn btn-nude-dark">Update Password</button></div>
                </form>
              </div>
            </div>
          </div>

          <!-- Orders tab (timeline-driven status + progress) -->
          <div class="tab-pane fade <?= tabShow('orders',$activeTab) ?>" id="tab-orders">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h5 class="mb-0">Your Orders</h5>
                  <div class="d-flex gap-2">
                    <a class="btn btn-nude-outline btn-sm" href="<?= base_url('account/ordertracking.php') ?>">Track Order</a>
                  </div>
                </div>

                <?php if (!$orders): ?>
                  <div class="alert alert-light border">
                    You have not placed any orders yet.
                    <div class="mt-2">
                      <a class="btn btn-nude-dark btn-sm" href="<?= base_url('shop.php') ?>">Start Shopping</a>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover align-middle" id="orders-table">
                      <thead class="table-light">
                        <tr>
                          <th style="min-width:140px;">Order #</th>
                          <th style="min-width:150px;">Placed</th>
                          <th>Status</th>
                          <th style="min-width:180px;">Progress</th>
                          <th class="text-end" style="min-width:120px;">Total</th>
                          <th class="text-end" style="min-width:220px;">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($orders as $o): ?>
                          <?php
                            $publicNum   = order_public_number($o);
                            $param       = ($o['order_number'] ?? '') !== '' ? (string)$o['order_number'] : (string)$o['id'];
                            $progressPct = (int)($o['_progress_percent'] ?? 0);
                            $progressIdx = (int)($o['_progress_index'] ?? 0);
                            $statusBadge = order_status_badge_from_key((string)($o['_status_key'] ?? $o['status'] ?? ''));
                          ?>
                          <tr data-order-code="<?= htmlspecialchars($param) ?>"
                              data-email="<?= htmlspecialchars($user['email']) ?>"
                              data-step="<?= $progressIdx ?>">
                            <td><strong><?= htmlspecialchars($publicNum) ?></strong></td>
                            <td>
                              <?= htmlspecialchars(date('M j, Y H:i', strtotime($o['created_at']))) ?>
                              <?php if (!empty($o['updated_at'])): ?>
                                <div class="small text-muted">Updated: <?= htmlspecialchars(date('M j, Y H:i', strtotime($o['updated_at']))) ?></div>
                              <?php endif; ?>
                            </td>
                            <td class="order-status-cell"><?= $statusBadge ?></td>
                            <td style="min-width:180px;">
                              <div class="progress" style="height:8px;">
                                <div class="progress-bar" role="progressbar"
                                     style="width: <?= $progressPct ?>%;"
                                     aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                              </div>
                            </td>
                            <td class="text-end"><?= money_disp2($o['total_amount'] ?? 0, $o['currency'] ?? 'NGN') ?></td>
                            <td class="text-end">
                              <div class="btn-group">
                                <a class="btn btn-sm btn-nude-dark"    href="<?= base_url('account/orderview.php').'?order='.urlencode($param) ?>">View</a>
                                <a class="btn btn-sm btn-nude-outline" href="<?= base_url('account/ordertracking.php') ?>">Track</a>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>

                <hr class="my-4">
                <p class="small text-muted mb-0">
                  Status & progress here use the same timeline logic as the tracking page. Open
                  <a href="<?= base_url('account/ordertracking.php') ?>">Order Tracking</a> to see full details.
                </p>
              </div>
            </div>
          </div>
          <!-- /Orders tab -->
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

<?php if ($activeTab === 'orders' && $orders): ?>
<script>
(function(){
  const API_URL   = <?= json_encode(rtrim(BASE_URL, '/').'/api/orders/track.php') ?>;
  const POLL_MS   = 15000; // 0 to disable polling
  const ORDER_FLOW = ['pending','awaiting_payment','paid','processing','shipped','in_transit','delivered','completed'];

  function badgeClassForStatus(s){
    s = String(s||'').toLowerCase().trim();
    if (['pending','awaiting_payment'].includes(s)) return 'warning';
    if (['paid','processing'].includes(s))          return 'info';
    if (['shipped','in_transit'].includes(s))       return 'primary';
    if (['delivered','completed'].includes(s))      return 'success';
    if (['cancelled','failed','refunded'].includes(s)) return 'danger';
    return 'secondary';
  }
  function humanizeStatus(s){
    return String(s||'').replace(/_/g,' ').replace(/\b\w/g,m=>m.toUpperCase());
  }

  async function refreshRow(tr){
    const code  = tr.getAttribute('data-order-code') || '';
    const email = tr.getAttribute('data-email') || '';
    if (!code || !email) return;

    try{
      const res = await fetch(API_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ order_code: code, email })
      });
      const j = await res.json();
      if (!j || !j.success) return;

      // --- Determine next index from timeline; never regress
      const curIdx  = parseInt(tr.dataset.step || '0', 10);
      let nextIdx   = (j.progress && typeof j.progress.index === 'number') ? j.progress.index : curIdx;
      if (isNaN(nextIdx)) nextIdx = curIdx;
      if (nextIdx < curIdx) nextIdx = curIdx; // monotonic
      tr.dataset.step = String(nextIdx);

      // --- Compose status key from index, with terminal override
      let statusKey = ORDER_FLOW[Math.min(nextIdx, ORDER_FLOW.length-1)];
      if (j.progress && j.progress.is_terminal && j.order && j.order.status) {
        const k = String(j.order.status).toLowerCase();
        if (['cancelled','failed','refunded'].includes(k)) statusKey = k;
      }

      // --- Update status badge (non-regressing)
      const tdStatus = tr.querySelector('.order-status-cell');
      if (tdStatus){
        const klass = badgeClassForStatus(statusKey);
        tdStatus.innerHTML = '<span class="badge text-bg-'+klass+'">'+ humanizeStatus(statusKey) +'</span>';
      }

      // --- Update progress bar (also monotonic)
      const pb = tr.querySelector('.progress-bar');
      if (pb && j.progress){
        const current = parseInt(pb.getAttribute('aria-valuenow') || '0', 10);
        let next = j.progress.is_terminal ? 100 :
                   (typeof j.progress.percent === 'number'
                      ? Math.max(0, Math.min(100, Math.round(j.progress.percent)))
                      : current);
        if (isNaN(next)) next = current;
        if (next < current) next = current; // do not regress
        pb.style.width = next + '%';
        pb.setAttribute('aria-valuenow', next);
      }
    }catch(e){ /* ignore transient errors */ }
  }

  function refreshAll(){
    document.querySelectorAll('#orders-table tbody tr[data-order-code]').forEach(refreshRow);
  }

  refreshAll();
  if (POLL_MS > 0) setInterval(refreshAll, POLL_MS);
})();
</script>
<?php endif; ?>
