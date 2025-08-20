<?php
// account/orderview.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_helpers.php';

require_user();
$userId = (int)$_SESSION['user_id'];

$idOrNum = $_GET['order'] ?? ($_GET['id'] ?? '');
if ($idOrNum === '') {
  header('Location: ' . base_url('account/dashboard.php?tab=orders'));
  exit;
}

$order = find_user_order($pdo, $userId, $idOrNum);
if (!$order) {
  header('Location: ' . base_url('account/dashboard.php?tab=orders'));
  exit;
}

$items    = load_order_items($pdo, (int)$order['id']);
$history  = load_order_history($pdo, (int)$order['id']);   // uses order_events
$shipment = load_order_shipment($pdo, (int)$order['id']);  // best-effort
[$total, $cur] = order_total_and_currency($order);

/* ---- progress helpers (same semantics as tracking) ----------------------- */

if (!function_exists('jhema_status_flow')) {
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
}

if (!function_exists('jhema_progress_from_history')) {
  /**
   * Compute progress strictly from timeline; fall back to current status if needed.
   * Returns: ['flow','index','percent','is_terminal','terminal_key']
   */
  function jhema_progress_from_history(array $history, string $currentStatus): array {
    $flow     = jhema_status_flow();
    $indexMap = array_flip($flow);
    $idx      = 0;
    $terminal = false;
    $terminalHit = null;

    if ($history) {
      foreach ($history as $ev) {
        $s = strtolower(trim((string)($ev['status'] ?? '')));
        if (isset($indexMap[$s])) {
          $idx = max($idx, (int)$indexMap[$s]);
        }
        if (in_array($s, ['cancelled','failed','refunded'], true)) {
          $terminal    = true;
          $terminalHit = $s;
        }
      }
    } else {
      $s = strtolower(trim($currentStatus));
      if (isset($indexMap[$s])) $idx = (int)$indexMap[$s];
      if (in_array($s, ['cancelled','failed','refunded'], true)) {
        $terminal    = true;
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
      'terminal_key' => $terminalHit,
    ];
  }
}

/* ---- derive display values ------------------------------------------------ */

$publicNum  = order_public_number($order);
$statusKey  = (string)$order['status'];
$statusLbl  = order_status_human($statusKey);
$statusMsg  = order_status_message($statusKey);

$progress   = jhema_progress_from_history($history, $statusKey);
$flow       = $progress['flow'];
$idx        = $progress['index'];
$pct        = $progress['percent'];
$isTerminal = $progress['is_terminal'];
$terminalKey= $progress['terminal_key'];

include("../includes/head.php");
include("../includes/svg.php");
include("../includes/mobile-header.php");
include("../includes/header.php");
?>
<main class="position-relative">
  <?php include("../scroll_categories.php"); ?>
  <div class="mb-4 pb-4"></div>

  <section class="container">

    <style>
      /* Match the tracking page look */
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

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h3 class="mb-0">Order <?= htmlspecialchars($publicNum) ?></h3>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('account/dashboard.php') ?>?tab=orders">Back to Orders</a>
        <a class="btn btn-outline-info btn-sm" href="<?= base_url('account/ordertracking.php') ?>">Track</a>
      </div>
    </div>

    <!-- Brand message + current status (aligned with tracking) -->
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
        <h5 class="mb-0">Order <span class="text-primary"><?= htmlspecialchars($publicNum) ?></span></h5>
        <div class="text-muted small">Placed on: <?= htmlspecialchars($order['created_at']) ?></div>
      </div>
      <ul class="jhema-kv">
        <li><span>Status</span><strong><?= htmlspecialchars($statusLbl) ?></strong></li>
        <li><span>Total</span><strong><?= money_disp($total, $cur) ?></strong></li>
        <li><span>Currency</span><strong><?= htmlspecialchars($cur) ?></strong></li>
      </ul>
    </div>

    <!-- Progress (computed from timeline) -->
    <div class="jhema-card p-3 p-md-4 mb-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0">Progress</h6>
        <span class="small text-muted"><?= $isTerminal ? 'Order ended' : ($pct . '%') ?></span>
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

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-3">
          <div class="card-body">
            <h6 class="mb-3">Items</h6>
            <?php if (!$items): ?>
              <div class="text-muted">No items recorded.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead class="table-light">
                    <tr><th>Product</th><th class="text-end">Price</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($items as $it): ?>
                      <tr>
                        <td><?= htmlspecialchars($it['name']) ?></td>
                        <td class="text-end"><?= ($cur==='NGN'?'₦':'') . number_format((float)$it['price'],2) ?></td>
                        <td class="text-center"><?= (int)$it['quantity'] ?></td>
                        <td class="text-end"><?= ($cur==='NGN'?'₦':'') . number_format((float)$it['subtotal'],2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card shadow-sm border-0">
          <div class="card-body">
            <h6 class="mb-3">Timeline</h6>
            <?php if (!$history): ?>
              <div class="text-muted">No status events yet.</div>
            <?php else: ?>
              <ul class="list-unstyled m-0">
                <?php foreach ($history as $h): ?>
                  <li class="mb-2">
                    <div><strong><?= htmlspecialchars(order_status_human((string)$h['status'])) ?></strong></div>
                    <div class="small text-muted">
                      <?= htmlspecialchars((string)$h['created_at']) ?>
                      <?= !empty($h['note']) ? ' · '.htmlspecialchars((string)$h['note']) : '' ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
          <div class="card-body">
            <h6 class="mb-3">Summary</h6>
            <ul class="list-unstyled small mb-0">
              <li class="d-flex justify-content-between py-1">
                <span>Subtotal</span>
                <strong><?= ($cur==='NGN'?'₦':'') . number_format((float)($order['subtotal'] ?? 0),2) ?></strong>
              </li>
              <li class="d-flex justify-content-between py-1">
                <span>Shipping</span>
                <strong><?= ($cur==='NGN'?'₦':'') . number_format((float)($order['shipping'] ?? 0),2) ?></strong>
              </li>
              <li class="d-flex justify-content-between py-1 border-top mt-2 pt-2">
                <span>Total</span>
                <strong><?= ($cur==='NGN'?'₦':'') . number_format($total,2) ?> <?= $cur!=='NGN'?htmlspecialchars($cur):'' ?></strong>
              </li>
            </ul>
          </div>
        </div>

        <!-- <div class="card shadow-sm border-0">
          <div class="card-body">
            <h6 class="mb-3">Shipment</h6>
            <?php if (!$shipment || (!$shipment['tracking_code'] && !$shipment['carrier'])): ?>
              <div class="text-muted">Not assigned yet.</div>
            <?php else: ?>
              <div class="small">
                <div><strong>Carrier:</strong> <?= htmlspecialchars($shipment['carrier'] ?: '—') ?></div>
                <div><strong>Tracking #:</strong> <?= htmlspecialchars($shipment['tracking_code'] ?: '—') ?></div>
                <?php if (!empty($shipment['tracking_url'])): ?>
                  <div class="mt-2"><a class="btn btn-outline-primary btn-sm" target="_blank" href="<?= htmlspecialchars($shipment['tracking_url']) ?>">Open Tracking</a></div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div> -->
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
