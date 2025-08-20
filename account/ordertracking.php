<?php
// account/ordertracking.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***


require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/order_helpers.php';

$order    = null;
$error    = null;
$history  = [];
$shipment = null;

/** Status flow used to compute progress */
function jhema_status_flow(): array {
  return [
    'pending',
    'awaiting_payment',
    'paid',
    'processing',
    'shipped',
    'in_transit',
    'delivered',
    'completed',
  ];
}

/** Compute progress strictly from timeline; fall back to current status if needed */
function jhema_progress_from_history(array $history, string $currentStatus): array {
  $flow     = jhema_status_flow();
  $indexMap = array_flip($flow); // 'pending' => 0, ...
  $idx      = 0;
  $terminal = false;
  $terminalHit = null;

  if ($history) {
    foreach ($history as $ev) {
      $s = strtolower(trim((string)($ev['status'] ?? '')));
      if (isset($indexMap[$s])) {
        // Track the furthest step reached
        $idx = max($idx, (int)$indexMap[$s]);
      }
      if (in_array($s, ['cancelled','failed','refunded'], true)) {
        $terminal = true;
        $terminalHit = $s;
      }
    }
  } else {
    // No events — fall back to current status
    $s = strtolower(trim($currentStatus));
    if (isset($indexMap[$s])) $idx = (int)$indexMap[$s];
    if (in_array($s, ['cancelled','failed','refunded'], true)) {
      $terminal = true;
      $terminalHit = $s;
    }
  }

  $steps = max(1, count($flow));
  $pct   = ($steps > 1) ? (int)round(($idx / ($steps - 1)) * 100) : 0;

  return [
    'flow'         => $flow,
    'index'        => $idx,
    'percent'      => $pct,
    'is_terminal'  => $terminal,
    'terminal_key' => $terminalHit, // may be null
  ];
}

/* ---------- Handle form submit ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code  = trim((string)($_POST['order_code'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));

  if ($code === '' || $email === '') {
    $error = 'Please enter both your Order Code and email.';
  } else {
    // Lookup by order_number (case-insensitive) + email
    $sql = "SELECT o.id, o.order_number, o.status,
                   o.total_amount, COALESCE(o.currency,'NGN') AS currency, o.created_at
            FROM orders o
            JOIN users u ON u.id = o.user_id
            WHERE LOWER(o.order_number) = LOWER(?) AND u.email = ?
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$code, $email]);
    $order = $st->fetch(PDO::FETCH_ASSOC);

    // Optional fallback: numeric ID typed instead of code
    if (!$order && ctype_digit($code)) {
      $st = $pdo->prepare("SELECT o.id, o.order_number, o.status,
                                  o.total_amount, COALESCE(o.currency,'NGN') AS currency, o.created_at
                           FROM orders o
                           JOIN users u ON u.id = o.user_id
                           WHERE o.id = ? AND u.email = ?
                           LIMIT 1");
      $st->execute([(int)$code, $email]);
      $order = $st->fetch(PDO::FETCH_ASSOC);
    }

    if (!$order) {
      $error = 'No matching order was found. Please check the code and email.';
    } else {
      // Timeline + shipping details
      $history  = load_order_history($pdo, (int)$order['id']);   // uses order_events
      $shipment = load_order_shipment($pdo, (int)$order['id']);  // best-effort
    }
  }
}

/* ---------- Page ---------- */
include("../includes/head.php");
include("../includes/svg.php");
include("../includes/mobile-header.php");
include("../includes/header.php");
?>
<main class="position-relative">
  <?php include("../scroll_categories.php"); ?>
  <div class="mb-4 pb-4"></div>

  <section class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8 col-xl-7">

        <style>
          /* Clean, professional look */
          .jhema-card{background:#fff;border:1px solid #eee;box-shadow:0 1px 2px rgba(0,0,0,.03);border-radius:10px;}
          .jhema-kv{list-style:none;padding:0;margin:0;}
          .jhema-kv li{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px dashed #eee;}
          .jhema-kv li:last-child{border-bottom:0;}
          .jhema-progress{height:8px;background:#f0f1f3;border-radius:999px;overflow:hidden;}
          .jhema-progress > div{height:100%;background:#1b479e;width:0;transition:width .4s ease;}
          .jhema-steps{display:flex;justify-content:space-between;gap:8px;}
          .jhema-step{flex:1;text-align:center;}
          .jhema-dot{width:10px;height:10px;border-radius:50%;display:inline-block;background:#d9dce3;margin-bottom:6px;}
          .jhema-step.active .jhema-dot{background:#1b479e;}
          .badge-soft{background:#f5f7fb;border:1px solid #e7ebf3;color:#1b2a4a;border-radius:999px;padding:.35rem .6rem;font-weight:600;}
        </style>

        <div class="mb-4">
          <h2 class="page-title mb-1">Order Tracking</h2>
          <p class="text-muted mb-0">Enter your <strong>Order Code</strong> (e.g. <code>JHEMA-000123</code>) and billing email to see the latest status.</p>
        </div>

        <form method="post" class="needs-validation mb-4" novalidate>
          <?php if ($error): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <div class="form-floating my-3">
            <input type="text" class="form-control" id="order_tracking_code" name="order_code" placeholder="Order Code *" required
                   value="<?= isset($_POST['order_code'])?htmlspecialchars($_POST['order_code']):'' ?>">
            <label for="order_tracking_code">Order Code *</label>
          </div>
          <div class="form-floating my-3">
            <input type="email" class="form-control" id="order_tracking_email" name="email" placeholder="Billing email *" required
                   value="<?= isset($_POST['email'])?htmlspecialchars($_POST['email']):'' ?>">
            <label for="order_tracking_email">Billing email *</label>
          </div>
          <button type="submit" class="btn btn-primary w-100">Track Order</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order && !$error): ?>
          <?php
            $code       = $order['order_number'] ?: ('#'.$order['id']);
            $statusKey  = (string)$order['status'];
            $statusLbl  = order_status_human($statusKey);
            $statusMsg  = order_status_message($statusKey);

            // >>> Progress entirely from timeline <<<
            $progress   = jhema_progress_from_history($history, $statusKey);
            $flow       = $progress['flow'];
            $idx        = $progress['index'];
            $pct        = $progress['percent'];
            $isTerminal = $progress['is_terminal'];
            $terminalKey= $progress['terminal_key'];
          ?>

          <!-- Brand message -->
          <div class="jhema-card p-3 p-md-4 mb-3">
            <div class="d-flex align-items-center justify-content-between">
              <h5 class="mb-1">Thank you for shopping with <span class="fw-semibold">Jhema</span>!</h5>
              <span class="badge-soft"><?= htmlspecialchars($statusLbl) ?></span>
            </div>
            <p class="text-muted mb-0"><?= htmlspecialchars($statusMsg) ?></p>
          </div>

          <!-- Order summary -->
          <div class="jhema-card p-3 p-md-4 mb-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <h5 class="mb-0">Order <span class="text-primary"><?= htmlspecialchars($code) ?></span></h5>
              <div class="text-muted small">Placed on: <?= htmlspecialchars($order['created_at']) ?></div>
            </div>
            <ul class="jhema-kv">
              <li><span>Status</span><strong><?= htmlspecialchars($statusLbl) ?></strong></li>
              <li><span>Total</span><strong><?= money_disp($order['total_amount'] ?? 0, $order['currency'] ?? 'NGN') ?></strong></li>
              <li><span>Currency</span><strong><?= htmlspecialchars($order['currency'] ?? 'NGN') ?></strong></li>
            </ul>
          </div>

          <!-- Progress (from timeline) -->
          <div class="jhema-card p-3 p-md-4 mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="mb-0">Progress</h6>
              <span class="small text-muted">
                <?= $isTerminal ? 'Order ended' : ($pct . '%') ?>
              </span>
            </div>
            <div class="jhema-progress mb-2" aria-hidden="true">
              <div style="width: <?= $isTerminal ? '100' : (int)$pct ?>%"></div>
            </div>

            <div class="jhema-steps small text-muted">
              <?php foreach ($flow as $i=>$k): ?>
                <div class="jhema-step <?= $i <= $idx ? 'active' : '' ?>">
                  <span class="jhema-dot"></span>
                  <div><?= htmlspecialchars(order_status_human($k)) ?></div>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if ($isTerminal): ?>
              <div class="alert alert-light border mt-3 mb-0">
                This order is marked as <strong><?= htmlspecialchars(order_status_human($terminalKey ?? $statusKey)) ?></strong>.
                If you think this is a mistake, please contact support.
              </div>
            <?php endif; ?>
          </div>

          <!-- Timeline -->
          <div class="jhema-card p-3 p-md-4 mb-4">
            <h6 class="mb-3">Timeline</h6>
            <?php if (!$history): ?>
              <div class="text-muted">No status events yet.</div>
            <?php else: ?>
              <ul class="list-unstyled mb-0">
                <?php foreach ($history as $h): ?>
                  <li class="mb-2">
                    <div><strong><?= htmlspecialchars(order_status_human((string)$h['status'])) ?></strong></div>
                    <div class="small text-muted"><?= htmlspecialchars((string)$h['created_at']) ?>
                      <?php if (!empty($h['note'])): ?> · <?= htmlspecialchars((string)$h['note']) ?><?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <!-- Brand footer/help -->
          <div class="text-center text-muted small mb-5">
            Need help? Our team is happy to assist. Please reply to your order email
            or reach out through the <a href="<?= htmlspecialchars(rtrim(BASE_URL,'/').'/contact.php') ?>">Contact page</a>.
          </div>
        <?php endif; ?>

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
