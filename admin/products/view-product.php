<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/**
 * Trim trailing zeros for weight display.
 * "1.00" -> "1", "1.20" -> "1.2", "0.50" -> "0.5"
 * Never pad with extra zeros.
 */
function weight_display($v): string {
    $s = (string)$v;
    $s = str_replace(',', '.', $s);
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
}

// Pagination
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage= 20;
$offset = ($page - 1) * $perPage;

// Optional search
$q      = trim((string)($_GET['q'] ?? ''));
$where  = '';
$params = [];
if ($q !== '') {
    $where = "WHERE p.name LIKE :q OR p.sku LIKE :q";
    $params[':q'] = "%{$q}%";
}

// Count
$stc = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
$stc->execute($params);
$total = (int)$stc->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

// Fetch
$sql = "
  SELECT p.id, p.name, p.sku, p.slug, p.base_currency_code, p.base_price, p.weight_kg,
         p.image_path, p.created_at, c.name AS category
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  $where
  ORDER BY p.created_at DESC, p.id DESC
  LIMIT :lim OFFSET :off
";
$stm = $pdo->prepare($sql);
foreach ($params as $k => $v) $stm->bindValue($k, $v, PDO::PARAM_STR);
$stm->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stm->bindValue(':off', $offset, PDO::PARAM_INT);
$stm->execute();
$rows = $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];

include __DIR__ . '/../partials/head.php';
?>
<style>
  .card{padding:1.25rem 1.5rem;border-radius:.75rem;margin-bottom:1.5rem;background:#fff;border:1px solid rgba(0,0,0,.08);box-shadow:0 1px 3px rgba(0,0,0,.02)}
  .thumb{width:56px;height:56px;object-fit:cover;border-radius:.5rem;border:1px solid #eee;background:#f9f9f9}
  .table td, .table th{vertical-align:middle}
  .pagination .page-link{color:#333}
  .pagination .active .page-link{background:#333;border-color:#333}
</style>

<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php include __DIR__ . '/../partials/page-header.php'; ?>
  <div class="page-body-wrapper">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="page-body">
      <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
          <div>
            <h1 class="fw-bold mb-0">Products</h1>
            <div class="text-muted small">Total: <?= (int)$total ?></div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <form class="d-flex" method="get">
              <input type="search" class="form-control" placeholder="Search name or SKU" name="q" value="<?= $esc($q) ?>">
              <button class="btn btn-outline-dark ms-2" type="submit">Search</button>
            </form>
            <a href="<?= $esc(base_url('admin/products/add-product.php')) ?>" class="btn btn-dark">Add Product</a>
          </div>
        </div>

        <div class="card">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Image</th>
                  <th>Name</th>
                  <th>SKU</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Weight</th>
                  <th>Created</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr><td colspan="9" class="text-center text-muted py-4">No products found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td><?= (int)$r['id'] ?></td>
                      <td>
                        <?php $img = product_image_url($r['image_path'] ?? null); ?>
                        <?php if ($img): ?>
                          <img src="<?= $esc($img) ?>" class="thumb" alt="">
                        <?php else: ?>
                          <span class="text-secondary small">—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="fw-semibold"><?= $esc($r['name']) ?></div>
                        <div class="small text-muted"><?= $esc($r['slug']) ?></div>
                      </td>
                      <td><?= $esc($r['sku']) ?></td>
                      <td><?= $esc($r['category'] ?? '—') ?></td>
                      <td><?= $esc($r['base_currency_code']) ?> <?= number_format((float)$r['base_price'], 2) ?></td>
                      <td><?= $esc(weight_display($r['weight_kg'])) ?> kg</td>
                      <td class="small text-muted"><?= $esc($r['created_at']) ?></td>
                      <td class="text-end">
                        <div class="btn-group">
                          <a class="btn btn-sm btn-outline-dark" href="<?= $esc(base_url('admin/products/view-product.php?id='.(int)$r['id'])) ?>">View</a>
                          <a class="btn btn-sm btn-dark" href="<?= $esc(base_url('admin/products/edit-product.php?id='.(int)$r['id'])) ?>">Edit</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($pages > 1): ?>
            <nav class="mt-3">
              <ul class="pagination">
                <?php for ($i=1; $i<=$pages; $i++): ?>
                  <li class="page-item <?= $i===$page?'active':'' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
              </ul>
            </nav>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/logout.php'; ?>
<?php include __DIR__ . '/../partials/script-js.php'; ?>
