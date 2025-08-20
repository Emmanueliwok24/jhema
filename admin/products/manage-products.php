<?php
declare(strict_types=1);

// Admin > Products > Manage
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();


$errors = [];
$okMsg  = null;

/** Small helpers */
$esc  = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = fn(string $p='') => htmlspecialchars(base_url($p), ENT_QUOTES, 'UTF-8');

/** Delete handler (POST via modal) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pid = (int)($_POST['product_id'] ?? 0);
    if ($pid > 0) {
        try {
            // Fetch product (main image) + gallery + variants for file cleanup
            $product = $pdo->prepare("SELECT id, image_path, slug FROM products WHERE id = ?");
            $product->execute([$pid]);
            $prod = $product->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                $errors[] = 'Product not found.';
            } else {
                $gstmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
                $gstmt->execute([$pid]);
                $galleryPaths = $gstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                $vstmt = $pdo->prepare("SELECT image_path FROM product_variants WHERE product_id = ?");
                $vstmt->execute([$pid]);
                $variantPaths = $vstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                // Delete DB rows in a transaction (children first)
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM product_images     WHERE product_id = ?")->execute([$pid]);
                $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?")->execute([$pid]);
                $pdo->prepare("DELETE FROM product_variants   WHERE product_id = ?")->execute([$pid]);
                $pdo->prepare("DELETE FROM products           WHERE id = ?")->execute([$pid]);
                $pdo->commit();

                // File cleanup (best-effort). Use BASE_URL to unlink local files.
                $paths = [];
                if (!empty($prod['image_path'])) $paths[] = $prod['image_path'];
                foreach ($galleryPaths as $p) if (!empty($p)) $paths[] = $p;
                foreach ($variantPaths as $p) if (!empty($p)) $paths[] = $p;

                foreach ($paths as $rel) {
                    $abs = rtrim(BASE_URL, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                    if (is_file($abs)) @unlink($abs);
                }

                $okMsg = 'Product deleted.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Delete failed: ' . $e->getMessage();
        }
    } else {
        $errors[] = 'Invalid product id.';
    }
}

/** List / search / pagination */
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(p.name LIKE :q OR p.sku LIKE :q OR c.name LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
$wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id $wsql";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$listSql = "
SELECT
  p.id, p.name, p.slug, p.sku, p.base_currency_code, p.base_price, p.weight_kg, p.created_at,
  c.name AS cat_name,
  (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
$wsql
ORDER BY p.created_at DESC, p.id DESC
LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($listSql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pages = (int)ceil($total / $perPage);

include __DIR__ . '/../partials/head.php';
?>
<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php include __DIR__ . '/../partials/page-header.php'; ?>
  <div class="page-body-wrapper">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="page-body">
      <header class="container d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <div>
          <h1 class="display-6 fw-bold mb-1">Products</h1>
          <p class="text-secondary mb-0">Manage, search, add, edit, view, or delete products.</p>
        </div>
        <a href="<?= $base('admin/products/add-product.php') ?>" class="btn bg-primary px-4">+ Add Product</a>
      </header>

      <?php if ($okMsg): ?>
        <div class="alert container bg-success border-0 shadow-sm rounded-3 mt-3">
          <?= $esc($okMsg) ?>
        </div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert container bg-danger border-0 shadow-sm rounded-3 mt-3">
          <strong>Action failed:</strong>
          <ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.$esc($e).'</li>'; ?></ul>
        </div>
      <?php endif; ?>

      <section class="container mt-3">
        <form class="row g-2 align-items-center" method="get">
          <div class="col-sm-8 col-md-6 col-lg-4">
            <input type="text" name="q" class="form-control" placeholder="Search by name / SKU / category" value="<?= $esc($q) ?>">
          </div>
          <div class="col-auto">
            <button class="btn btn-outline-dark">Search</button>
          </div>
          <?php if ($q !== ''): ?>
          <div class="col-auto">
            <a class="btn btn-ghost" href="<?= $base('admin/products/manage-products.php') ?>">Clear</a>
          </div>
          <?php endif; ?>
        </form>

        <div class="card mt-3">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name / SKU</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Weight</th>
                  <th>Variants</th>
                  <th>Created</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($rows): foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td>
                    <div class="fw-semibold"><?= $esc($r['name']) ?></div>
                    <div class="text-secondary small"><?= $esc($r['sku']) ?></div>
                  </td>
                  <td><?= $esc($r['cat_name'] ?? '—') ?></td>
                  <td><?= $esc($r['base_currency_code']) ?> <?= number_format((float)$r['base_price'], 2) ?></td>
                  <td><?= number_format((float)$r['weight_kg'], 3) ?> kg</td>
                  <td><?= (int)$r['variant_count'] ?></td>
                  <td class="text-nowrap"><?= $esc($r['created_at']) ?></td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-ghost" href="<?= $base('admin/products/view-product.php?id=' . (int)$r['id']) ?>">View (Admin)</a>
                    <a class="btn btn-sm btn-ghost" target="_blank" href="<?= $base('product.php?slug=' . rawurlencode((string)$r['slug'])) ?>">Preview</a>
                    <a class="btn btn-sm btn-outline-dark" href="<?= $base('admin/products/edit-product.php?id=' . (int)$r['id']) ?>">Edit</a>

                    <!-- Delete trigger (modal) -->
                    <button
                      type="button"
                      class="btn btn-sm btn-danger"
                      data-bs-toggle="modal"
                      data-bs-target="#deleteModal"
                      data-product-id="<?= (int)$r['id'] ?>"
                      data-product-name="<?= $esc($r['name']) ?>"
                    >Delete</button>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center text-secondary py-4">No products found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($pages > 1): ?>
        <nav class="mt-3">
          <ul class="pagination">
            <?php for ($p=1; $p<=$pages; $p++): ?>
              <?php
              $qs = http_build_query(array_filter(['q'=>$q, 'page'=>$p]));
              $href = $base('admin/products/manage-products.php' . ($qs ? ('?'.$qs) : ''));
              ?>
              <li class="page-item <?= $p===$page ? 'active' : '' ?>">
                <a class="page-link" href="<?= $href ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="product_id" id="delete_product_id" value="">
      <div class="modal-header">
        <h5 class="modal-title">Delete Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Are you sure you want to delete <strong id="delete_product_name">this product</strong>? This cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger">Delete</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/logout.php'; ?>
<?php include __DIR__ . '/../partials/script-js.php'; ?>

<script>
// Fill modal with row data
const deleteModal = document.getElementById('deleteModal');
if (deleteModal) {
  deleteModal.addEventListener('show.bs.modal', event => {
    const btn = event.relatedTarget;
    const id   = btn?.getAttribute('data-product-id') || '';
    const name = btn?.getAttribute('data-product-name') || '';
    document.getElementById('delete_product_id').value = id;
    document.getElementById('delete_product_name').textContent = name || 'this product';
  });
}
</script>
