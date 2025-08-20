<?php
// admin/orders/index.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/order_helpers.php';

$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$allowed = array_keys(order_status_labels());

$where = ''; $bind = [];
if ($statusFilter !== '' && in_array($statusFilter, $allowed, true)) {
  $where = "WHERE o.status = ?";
  $bind[] = $statusFilter;
}

$sql = "
  SELECT o.*, u.email
    FROM orders o
    JOIN users u ON u.id = o.user_id
  $where
  ORDER BY o.created_at DESC
  LIMIT 200
";
$st = $pdo->prepare($sql);
$st->execute($bind);
$orders = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

include __DIR__ . "/../partials/head.php";
?>
<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php include __DIR__ . "/../partials/page-header.php"; ?>
  <div class="page-body-wrapper">
    <?php include __DIR__ . "/../partials/sidebar.php"; ?>

    <div class="page-body">
      <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h3 class="mb-0">Orders</h3>
          <form class="d-flex gap-2" method="get">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="">All statuses</option>
              <?php foreach (order_status_labels() as $k=>$label): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $statusFilter===$k?'selected':'' ?>>
                  <?= htmlspecialchars($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($statusFilter!==''): ?><a class="btn btn-sm btn-outline-secondary" href="?">Clear</a><?php endif; ?>
          </form>
        </div>

        <div class="card o-hidden card-hover">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Placed</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $o): ?>
                    <?php [$total, $cur] = order_total_and_currency($o); ?>
                    <tr>
                      <td><?= htmlspecialchars(order_public_number($o)) ?></td>
                      <td><?= htmlspecialchars($o['email']) ?></td>
                      <td><?= htmlspecialchars(order_status_human((string)$o['status'])) ?></td>
                      <td><?= money_disp($total, $cur) ?><?= $cur!=='NGN'?' '.htmlspecialchars($cur):'' ?></td>
                      <td><?= htmlspecialchars($o['created_at']) ?></td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$o['id'] ?>">Open</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$orders): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No orders yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . "/../partials/logout.php"; ?>
<?php include __DIR__ . "/../partials/script-js.php"; ?>
