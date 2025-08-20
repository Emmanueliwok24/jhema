<?php
// admin/orders/view.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/order_helpers.php';

require_admin();

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) redirect(base_url('admin/orders/index.php'));

$order = get_order($pdo, $orderId);
if (!$order) {
  include __DIR__ . '/../partials/head.php';
  echo '<div class="page-body"><div class="container-fluid"><div class="alert alert-danger">Order not found.</div></div></div>';
  include __DIR__ . '/../partials/script-js.php';
  exit;
}

$items  = get_order_items($pdo, $orderId);
$events = get_order_events($pdo, $orderId);

$ok   = isset($_GET['ok']);
$err  = $_GET['err'] ?? '';

include __DIR__ . '/../partials/head.php';
?>
<style>
/* Nude-ish, clean admin look for this page only */
.page-body{background:#fafafa;}
.card{background:#fff;border:1px solid #eee;box-shadow:none;}
.card-header{background:#fff;border-bottom:1px solid #eee;}
.table> :not(caption)>*>*{background:transparent;}
.badge.text-bg-secondary{background:#f1f1f1;color:#333;}
.form-control, .form-select, textarea{background:#fff;border:1px solid #ddd;}
</style>

<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php include __DIR__ . '/../partials/page-header.php'; ?>
  <div class="page-body-wrapper">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="page-body">
      <div class="container-fluid">

        <?php if ($ok): ?>
          <div class="alert alert-success">Order status updated.</div>
        <?php endif; ?>
        <?php if ($err): ?>
          <div class="alert alert-danger">
            <?php
              echo match ($err) {
                'bad_id'        => 'Invalid order id.',
                'bad_status'    => 'Invalid status.',
                'update_failed' => 'Update failed.',
                'exception'     => 'An unexpected error occurred.',
                default         => 'An error occurred.',
              };
            ?>
          </div>
        <?php endif; ?>

        <div class="card o-hidden card-hover">
          <div class="card-header border-0 d-flex align-items-center justify-content-between">
            <div>
              <h4 class="mb-0">Order <?= htmlspecialchars($order['order_number'] ?: ('#'.$order['id'])) ?></h4>
              <div class="text-muted small">
                Placed: <?= htmlspecialchars($order['created_at']) ?><?= !empty($order['updated_at']) ? ' · Updated: '.htmlspecialchars($order['updated_at']) : '' ?>
              </div>
            </div>
            <div>
              <span class="badge text-bg-secondary"><?= htmlspecialchars(order_status_human((string)$order['status'])) ?></span>
            </div>
          </div>

          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="p-3 bg-light rounded">
                  <div class="small text-muted">Customer</div>
                  <div class="fw-semibold"><?= htmlspecialchars(trim(($order['user_first_name'] ?? '').' '.($order['user_last_name'] ?? '')) ?: '—') ?></div>
                  <div class="small"><?= htmlspecialchars($order['user_email'] ?? '—') ?></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="p-3 bg-light rounded">
                  <div class="small text-muted">Ship To</div>
                  <div><?= htmlspecialchars($order['address_line1'] ?? '') ?></div>
                  <div><?= htmlspecialchars($order['address_line2'] ?? '') ?></div>
                  <div><?= htmlspecialchars(trim(($order['city']??'').' '.($order['state']??''))) ?></div>
                  <div><?= htmlspecialchars(($order['zipcode']??'').' '.($order['country_code']??'')) ?></div>
                  <div class="small text-muted mt-1">Phone: <?= htmlspecialchars($order['phone'] ?? '—') ?></div>
                </div>
              </div>
            </div>

            <hr>

            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Item</th>
                    <th class="text-end">Price</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td><?= htmlspecialchars($it['name']) ?></td>
                    <td class="text-end"><?= money_disp($it['price'], $order['currency'] ?? 'NGN') ?></td>
                    <td class="text-center"><?= (int)$it['quantity'] ?></td>
                    <td class="text-end"><?= money_disp($it['subtotal'], $order['currency'] ?? 'NGN') ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="3" class="text-end">Subtotal</th>
                    <th class="text-end"><?= money_disp($order['subtotal'] ?? 0, $order['currency'] ?? 'NGN') ?></th>
                  </tr>
                  <tr>
                    <th colspan="3" class="text-end">Shipping</th>
                    <th class="text-end"><?= money_disp($order['shipping'] ?? 0, $order['currency'] ?? 'NGN') ?></th>
                  </tr>
                  <tr>
                    <th colspan="3" class="text-end">Total</th>
                    <th class="text-end"><?= money_disp($order['total_amount'] ?? ($order['total'] ?? 0), $order['currency'] ?? 'NGN') ?></th>
                  </tr>
                </tfoot>
              </table>
            </div>

            <hr>

            <div class="row g-3">
              <div class="col-lg-6">
                <h6>Status History</h6>
                <?php if (!$events): ?>
                  <div class="alert alert-light border">No events yet.</div>
                <?php else: ?>
                  <ul class="list-group">
                    <?php foreach ($events as $ev): ?>
                      <li class="list-group-item">
                        <div class="small text-muted"><?= htmlspecialchars($ev['created_at']) ?></div>
                        <div><strong><?= htmlspecialchars(order_status_human((string)$ev['to_status'])) ?></strong>
                          <?php if (!empty($ev['from_status'])): ?>
                            <span class="text-muted">from <?= htmlspecialchars(order_status_human((string)$ev['from_status'])) ?></span>
                          <?php endif; ?>
                        </div>
                        <?php if (!empty($ev['note'])): ?>
                          <div class="small"><?= nl2br(htmlspecialchars($ev['note'])) ?></div>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>

              <div class="col-lg-6">
                <h6>Update Status</h6>
                <!-- NO CSRF FIELD (as requested) -->
                <form method="post" action="<?= htmlspecialchars(base_url('admin/orders/update_status.php'), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                  <div class="mb-2">
                    <label class="form-label">New Status</label>
                    <select name="status" class="form-select" required>
                      <?php foreach (array_keys(order_status_labels()) as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= strtolower((string)$order['status'])===strtolower($s)?'selected':'' ?>>
                          <?= htmlspecialchars(order_status_human($s)) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Admin Note (optional)</label>
                    <textarea name="note" rows="3" class="form-control" placeholder="Internal note (optional)"></textarea>
                  </div>

                  <div class="d-flex gap-2">
                    <button class="btn btn-primary">Save</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(base_url('admin/orders/index.php'), ENT_QUOTES, 'UTF-8') ?>">Back</a>
                  </div>
                </form>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../partials/script-js.php'; ?>
