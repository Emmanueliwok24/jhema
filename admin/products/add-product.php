<?php
declare(strict_types=1);

// ---------- BOOTSTRAP ----------
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

$errors  = [];
$success = false;

// Upload directory (public)
$uploadDir = defined('PRODUCT_UPLOAD_DIR') ? PRODUCT_UPLOAD_DIR : (__DIR__ . '/../../public/images/products');
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

// ---------- LOGGING ----------
define('PRODUCT_ERR_LOG', __DIR__ . '/error.log');
@ini_set('log_errors', '1');
@ini_set('error_log', PRODUCT_ERR_LOG);

set_error_handler(function($severity, $message, $file, $line) {
  error_log(sprintf("[%s] PHP %s: %s in %s:%d", date('c'), $severity, $message, $file, $line));
  return false;
});
set_exception_handler(function(Throwable $ex) {
  error_log(sprintf("[%s] EXCEPTION: %s in %s:%d\nTrace: %s",
    date('c'), $ex->getMessage(), $ex->getFile(), $ex->getLine(), $ex->getTraceAsString()));
});

// ---------- HELPERS ----------
function fetch_categories(PDO $pdo): array {
  return $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_currencies(PDO $pdo): array {
  try {
    $rows = $pdo->query("SELECT code, is_base FROM currencies ORDER BY code")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $base = 'NGN';
    foreach ($rows as $r) if (!empty($r['is_base'])) { $base = $r['code']; break; }
    if (!$rows) $rows = [['code'=>'NGN','is_base'=>1]];
    return [$rows, $base];
  } catch (Throwable $e) {
    return [[['code'=>'NGN','is_base'=>1]], 'NGN'];
  }
}

function slugify_strict(string $text): string {
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  $converted = @iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  if ($converted !== false) $text = $converted;
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, '-');
  $text = preg_replace('~-+~', '-', $text);
  $text = strtolower($text);
  return $text ?: 'product';
}

function handle_file_upload(array $file): ?string {
  if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload error: '.$file['error']);

  if (!class_exists('finfo')) throw new RuntimeException('Fileinfo extension not available.');
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($file['tmp_name']);
  $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
  if (!isset($allowed[$mime])) throw new RuntimeException('Invalid image type.');
  if (($file['size'] ?? 0) > 8 * 1024 * 1024) throw new RuntimeException('Image too large (max 8MB).');

  if (!is_dir(PRODUCT_UPLOAD_DIR)) {
    if (!mkdir(PRODUCT_UPLOAD_DIR, 0775, true) && !is_dir(PRODUCT_UPLOAD_DIR)) {
      throw new RuntimeException('Failed to create upload dir.');
    }
  }

  $name = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
  $dest = rtrim(PRODUCT_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $name;

  if (!is_uploaded_file($file['tmp_name'])) throw new RuntimeException('Invalid upload source.');
  if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Failed to move upload.');

  // public path relative to web root
  $rel = PRODUCT_UPLOAD_BASE_REL ?: 'public/images/products';
  return rtrim($rel, '/\\') . '/' . $name;
}

// ---------- PAGE DATA ----------
$categories = fetch_categories($pdo);
[$currencies, $baseCurrency] = get_currencies($pdo);

// ---------- SUBMIT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Required
  $required = [
    'name'        => 'Product Name',
    'sku'         => 'SKU',
    'category_id' => 'Category',
    'base_price'  => 'Base Price',
    'weight_kg'   => 'Weight (kg)',
    'product_type'=> 'Product Type',
  ];
  foreach ($required as $field => $label) {
    if (!isset($_POST[$field]) || trim((string)$_POST[$field]) === '') {
      $errors[] = "$label is required.";
    }
  }

  $name      = trim((string)($_POST['name'] ?? ''));
  $sku       = trim((string)($_POST['sku'] ?? ''));
  $desc      = (string)($_POST['description'] ?? '');
  $catId     = (int)($_POST['category_id'] ?? 0);
  $productType = $_POST['product_type'] ?? 'single';

  $basePrice = (string)($_POST['base_price'] ?? '0');
  if (!is_numeric($basePrice)) $errors[] = 'Base Price must be numeric.';
  $priceNum = (float)$basePrice;
  if ($priceNum < 0) $errors[] = 'Base Price cannot be negative.';

  // EXACT-AS-TYPED WEIGHT
  $weightTyped = str_replace(',', '.', trim((string)($_POST['weight_kg'] ?? '')));
  if ($weightTyped === '') {
    $errors[] = 'Weight (kg) is required.';
  } elseif (!preg_match('/^\d+(?:\.\d+)?$/', $weightTyped)) {
    $errors[] = 'Weight must be a number in kilograms (e.g., 0.5, 1, 2.25).';
  } elseif ((float)$weightTyped <= 0) {
    $errors[] = 'Weight must be greater than 0.';
  }
  // Numeric for math; DB weight_kg is DECIMAL(10,2) so DB will clamp to 2dp as needed.
  $weightNum = (float)$weightTyped;

  if (empty($errors)) {
    try {
      $pdo->beginTransaction();

      // Unique slug (name + sku)
      $baseSlug = slugify_strict($name) . '-' . slugify_strict($sku);
      if ($baseSlug === '-') $baseSlug = 'product';
      $slug = $baseSlug;
      $i = 1;
      while (true) {
        $q = $pdo->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
        $q->execute([$slug]);
        if (!$q->fetchColumn()) break;
        $slug = $baseSlug . '-' . $i++;
      }

      // Main image required
      if (empty($_FILES['image_file']) || ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Main product image is required.');
      }
      $imagePath = handle_file_upload($_FILES['image_file']);

      // Insert product (NOTE: we store both numeric and exact typed)
      $st = $pdo->prepare("
        INSERT INTO products
          (category_id, name, sku, slug, description,
           base_price, base_currency_code,
           weight_kg, weight_kg_tmp,
           image_path, created_at, updated_at, is_active)
        VALUES
          (:category_id, :name, :sku, :slug, :description,
           :base_price, :base_currency_code,
           :weight_kg, :weight_kg_tmp,
           :image_path, NOW(), NOW(), 1)
      ");
      $st->execute([
        ':category_id'        => $catId,
        ':name'               => $name,
        ':sku'                => $sku,
        ':slug'               => $slug,
        ':description'        => $desc,
        ':base_price'         => $priceNum,
        ':base_currency_code' => (string)($_POST['base_currency_code'] ?? $baseCurrency),
        ':weight_kg'          => $weightNum,
        ':weight_kg_tmp'      => $weightTyped,     // EXACT typed string saved
        ':image_path'         => $imagePath,
      ]);
      $productId = (int)$pdo->lastInsertId();

      // Save "main" image into product_images as is_main=1
      $pdo->prepare("
        INSERT INTO product_images (product_id, image_path, is_main, sort_order)
        VALUES (?, ?, 1, 0)
      ")->execute([$productId, $imagePath]);

      // Optional gallery
      if (!empty($_FILES['gallery_files']) && is_array($_FILES['gallery_files']['tmp_name'])) {
        $count = count($_FILES['gallery_files']['tmp_name']);
        for ($idx=0; $idx<$count; $idx++) {
          if (!empty($_FILES['gallery_files']['tmp_name'][$idx])) {
            $galleryPath = handle_file_upload([
              'name'     => $_FILES['gallery_files']['name'][$idx],
              'type'     => $_FILES['gallery_files']['type'][$idx],
              'tmp_name' => $_FILES['gallery_files']['tmp_name'][$idx],
              'error'    => $_FILES['gallery_files']['error'][$idx],
              'size'     => $_FILES['gallery_files']['size'][$idx],
            ]);
            if ($galleryPath) {
              $pdo->prepare("
                INSERT INTO product_images (product_id, image_path, is_main, sort_order)
                VALUES (?, ?, 0, ?)
              ")->execute([$productId, $galleryPath, ($idx+1)]);
            }
          }
        }
      }

      // Attributes (AJAX-fed; still read posted IDs)
      $attrIds = array_merge($_POST['occasion_ids'] ?? [], $_POST['length_ids'] ?? [], $_POST['style_ids'] ?? []);
      if ($attrIds) {
        $attrIds = array_unique(array_map('intval', $attrIds));
        $ins = $pdo->prepare("INSERT INTO product_attributes (product_id, attribute_id) VALUES (?, ?)");
        foreach ($attrIds as $aid) $ins->execute([$productId, $aid]);
      }

      // Variants (SIZES ONLY if product_type == variant_size)
      $featuredVariantId = null;
      if (($productType === 'variant_size') && !empty($_POST['size_rows'])) {
        foreach ($_POST['size_rows'] as $idx => $row) {
          $vSize  = trim((string)($row['size'] ?? ''));
          $vPrice = (isset($row['price']) && $row['price'] !== '') ? (float)$row['price'] : null;
          $vStock = isset($row['stock']) ? (int)$row['stock'] : 0;

          $vImagePath = null;
          if (!empty($_FILES['size_rows']['tmp_name'][$idx]['image'])) {
            $file = [
              'name'     => $_FILES['size_rows']['name'][$idx]['image'],
              'type'     => $_FILES['size_rows']['type'][$idx]['image'],
              'tmp_name' => $_FILES['size_rows']['tmp_name'][$idx]['image'],
              'error'    => $_FILES['size_rows']['error'][$idx]['image'],
              'size'     => $_FILES['size_rows']['size'][$idx]['image'],
            ];
            $vImagePath = handle_file_upload($file);
          }

          $pdo->prepare("
            INSERT INTO product_variants (product_id, type, size, color, price, stock, image_path, featured)
            VALUES (:pid, 'size', :size, NULL, :price, :stock, :image_path, 0)
          ")->execute([
            ':pid'        => $productId,
            ':size'       => $vSize ?: null,
            ':price'      => $vPrice,
            ':stock'      => $vStock,
            ':image_path' => $vImagePath
          ]);

          $thisVariantId = (int)$pdo->lastInsertId();
          if (isset($_POST['featured_choice']) && $_POST['featured_choice'] === "variant-new:size:{$idx}") {
            $featuredVariantId = $thisVariantId;
          }
        }
      }

      // Featured image selection
      if (isset($_POST['featured_choice'])) {
        $choice = (string)$_POST['featured_choice'];
        if ($choice === 'main') {
          $imgId = $pdo->prepare("SELECT id FROM product_images WHERE product_id=? AND is_main=1 LIMIT 1");
          $imgId->execute([$productId]);
          $id = $imgId->fetchColumn();
          if ($id) {
            $pdo->prepare("UPDATE products SET featured_image_id = ?, featured_variant_id = NULL WHERE id = ?")
                ->execute([(int)$id, $productId]);
          }
        } elseif (preg_match('/^gallery:(\d+)$/', $choice, $m)) {
          $pos = (int)$m[1];
          $imgIdStmt = $pdo->prepare("
            SELECT id FROM product_images
            WHERE product_id = ? AND is_main = 0
            ORDER BY sort_order ASC, id ASC
            LIMIT 1 OFFSET ?
          ");
          $imgIdStmt->execute([$productId, $pos]);
          $id = $imgIdStmt->fetchColumn();
          if ($id) {
            $pdo->prepare("UPDATE products SET featured_image_id = ?, featured_variant_id = NULL WHERE id = ?")
                ->execute([(int)$id, $productId]);
          }
        } elseif (!empty($featuredVariantId)) {
          $pdo->prepare("UPDATE products SET featured_variant_id = ?, featured_image_id = NULL WHERE id = ?")
              ->execute([$featuredVariantId, $productId]);
        }
      }

      $pdo->commit();
      $success = true;
      header("Location: manage-products.php?created={$productId}");
      exit;
    } catch (Throwable $e) {
      $pdo->rollBack();
      $errors[] = "Error saving product: " . $e->getMessage();
      error_log("Product creation error: " . $e->getMessage());
    }
  }
}

$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
include __DIR__ . '/../partials/head.php';
?>
<style>
  .card{padding:1.25rem 1.5rem;border-radius:.75rem;margin-bottom:1.5rem;background:#fff;border:1px solid rgba(0,0,0,.08);box-shadow:0 1px 3px rgba(0,0,0,.02)}
  .card-header{padding:0;margin-bottom:1.25rem;background:transparent;border-bottom:none}
  .card h2{font-size:1.25rem;font-weight:600;color:#333;margin-bottom:1rem}
  .form-label{font-weight:500;margin-bottom:.5rem;color:#444}
  .form-control,.form-select{border-radius:.5rem;border:1px solid #ddd;padding:.75rem 1rem;font-size:.9375rem}
  .form-control:focus,.form-select:focus{border-color:#999;box-shadow:0 0 0 .2rem rgba(0,0,0,.05)}
  textarea.form-control{min-height:120px}
  .required-field::after{content:"*";color:#dc3545;margin-left:.25rem}
  .variant-table{width:100%}
  .variant-table th{font-weight:500;color:#666;font-size:.875rem;text-transform:uppercase;letter-spacing:.5px}
  .variant-table td{vertical-align:middle;padding:.75rem .5rem}
  .thumb{width:60px;height:60px;object-fit:cover;border-radius:.5rem;border:1px solid #eee;background:#f9f9f9}
  .btn-primary{background-color:#333;border-color:#333;padding:.75rem 1.5rem;font-weight:500}
  .btn-primary:hover{background-color:#222;border-color:#222}
  .btn-outline-dark{border-color:#ddd;color:#666}
  @media (max-width: 992px){
    .variant-table thead{display:none}
    .variant-table tr{display:block;margin-bottom:1rem;border:1px solid #eee;border-radius:.75rem;padding:.75rem}
    .variant-table td{display:grid;grid-template-columns:120px 1fr;gap:.75rem;padding:.5rem 0}
    .variant-table td::before{content:attr(data-label);font-weight:500;color:#666}
    .variant-table td.text-end{grid-template-columns:1fr;justify-content:end}
  }
</style>

<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php include __DIR__ . '/../partials/page-header.php'; ?>
  <div class="page-body-wrapper">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="page-body">
      <div class="container">

        <div class="page-header mb-4">
          <h1 class="fw-bold">Add New Product</h1>
          <p class="text-muted">Fill in the product details below</p>
        </div>

        <?php if ($success): ?>
          <div class="alert alert-success mb-4">Product created successfully!</div>
        <?php elseif (!empty($errors)): ?>
          <div class="alert alert-danger mb-4">
            <strong>Please fix these issues:</strong>
            <ul class="mb-0">
              <?php foreach ($errors as $error): ?>
                <li><?= $esc($error) ?></li>
              <?php endforeach; ?>
            </ul>
            <div class="small mt-2">
              Error log: <code><?= $esc(PRODUCT_ERR_LOG) ?></code>
            </div>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
          <div class="row">
            <!-- LEFT -->
            <div class="col-lg-8">
              <div class="card mb-4">
                <div class="card-header"><h2>Product Information</h2></div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label required-field">Product Name</label>
                      <input type="text" name="name" class="form-control" value="<?= $esc($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label required-field">SKU</label>
                      <input type="text" name="sku" class="form-control" value="<?= $esc($_POST['sku'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Description</label>
                      <textarea name="description" class="form-control" rows="3"><?= $esc($_POST['description'] ?? '') ?></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-header"><h2>Pricing & Inventory</h2></div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label required-field">Base Price</label>
                      <div class="input-group">
                        <span class="input-group-text"><?= $esc($baseCurrency) ?></span>
                        <input type="number" name="base_price" step="0.01" class="form-control"
                               value="<?= $esc($_POST['base_price'] ?? '') ?>" required>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label required-field">Currency</label>
                      <select name="base_currency_code" class="form-select" required>
                        <?php foreach ($currencies as $currency): ?>
                          <option value="<?= $esc($currency['code']) ?>"
                            <?= ($_POST['base_currency_code'] ?? $baseCurrency) === $currency['code'] ? 'selected' : '' ?>>
                            <?= $esc($currency['code']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label required-field">Weight (kg)</label>
                      <input type="text" name="weight_kg" inputmode="decimal" class="form-control"
                             value="<?= $esc($_POST['weight_kg'] ?? '') ?>" required
                             placeholder="e.g. 0.5 or 1">
                      <div class="form-text">Shown exactly as typed (used for display). Numeric copy is stored for math.</div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card mb-4">
                <div class="card-header"><h2>Classification</h2></div>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label required-field">Category</label>
                      <select name="category_id" id="category_id" class="form-select" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                          <option value="<?= (int)$category['id'] ?>"
                            <?= ((int)($_POST['category_id'] ?? 0) === (int)$category['id']) ? 'selected' : '' ?>>
                            <?= $esc($category['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Occasions</label>
                      <select name="occasion_ids[]" id="occasion_ids" class="form-select" multiple size="3"></select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Lengths</label>
                      <select name="length_ids[]" id="length_ids" class="form-select" multiple size="3"></select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Styles</label>
                      <select name="style_ids[]" id="style_ids" class="form-select" multiple size="3"></select>
                    </div>
                  </div>
                </div>
              </div>

              <!-- PRODUCT TYPE -->
              <div class="card mb-4">
                <div class="card-header"><h2>Product Type</h2></div>
                <div class="card-body">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" id="ptype_single" name="product_type" value="single"
                      <?= ($_POST['product_type'] ?? 'single') === 'single' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ptype_single">Single product</label>
                  </div>
                  <div class="form-check mt-2">
                    <input class="form-check-input" type="radio" id="ptype_variant" name="product_type" value="variant_size"
                      <?= ($_POST['product_type'] ?? '') === 'variant_size' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ptype_variant">Variant product (sizes with own price)</label>
                  </div>
                </div>
              </div>

              <!-- VARIANTS (Sizes only) -->
              <div class="card" id="variantSizeBox" style="display:none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h2>Size Variants</h2>
                  <button type="button" class="btn btn-sm btn-outline-dark" onclick="addSizeRow()">
                    <i class="fas fa-plus me-1"></i> Add Size
                  </button>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table variant-table" id="sizeTable">
                      <thead>
                        <tr>
                          <th>Size</th><th>Price</th><th>Stock</th><th>Image</th><th>Featured</th><th class="text-end"></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!empty($_POST['size_rows'])): ?>
                          <?php foreach ($_POST['size_rows'] as $index => $row): ?>
                            <tr>
                              <td data-label="Size"><input type="text" name="size_rows[<?= $index ?>][size]" class="form-control form-control-sm" value="<?= $esc($row['size'] ?? '') ?>" required></td>
                              <td data-label="Price"><input type="number" step="0.01" name="size_rows[<?= $index ?>][price]" class="form-control form-control-sm" value="<?= $esc($row['price'] ?? '') ?>"></td>
                              <td data-label="Stock"><input type="number" name="size_rows[<?= $index ?>][stock]" class="form-control form-control-sm" min="0" value="<?= $esc($row['stock'] ?? 0) ?>"></td>
                              <td data-label="Image">
                                <div class="d-flex align-items-center gap-2">
                                  <img src="#" class="thumb" id="sizePreview<?= $index ?>" style="display:none;">
                                  <input type="file" name="size_rows[<?= $index ?>][image]" class="form-control form-control-sm" accept="image/*">
                                </div>
                              </td>
                              <td data-label="Featured"><input type="radio" name="featured_choice" value="variant-new:size:<?= $index ?>"></td>
                              <td class="text-end"><button type="button" class="btn btn-sm btn-outline-dark" onclick="this.closest('tr').remove()"><i class="fas fa-trash-alt"></i></button></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-between mt-4">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i> Save Product</button>
              </div>
            </div>

            <!-- RIGHT -->
            <div class="col-lg-4">
              <div class="card mb-4">
                <div class="card-header"><h2>Media</h2></div>
                <div class="card-body">
                  <div class="mb-4">
                    <label class="form-label required-field">Main Product Image</label>
                    <div class="mb-3">
                      <img id="preview" class="img-fluid rounded border" style="display:none;">
                    </div>
                    <input type="file" name="image_file" class="form-control" accept="image/*" onchange="previewMain(this)" required>
                    <div class="form-text">Primary display image</div>
                  </div>

                  <div class="mb-4">
                    <label class="form-label">Additional Images</label>
                    <input type="file" name="gallery_files[]" id="gallery_files" class="form-control" accept="image/*" multiple>
                    <div id="galleryPreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                  </div>

                  <div>
                    <label class="form-label">Featured Image</label>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="featured_choice" id="feat_main" value="main" checked>
                      <label class="form-check-label" for="feat_main">Use Main Image</label>
                    </div>
                    <div id="featuredGalleryRadios" class="mt-2"></div>
                    <div class="form-text">Or pick a size-variant image when in Variant mode.</div>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header"><h2>Quick Tips</h2></div>
                <div class="card-body">
                  <ul class="list-unstyled small">
                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Use high-quality images (≥ 800×800)</li>
                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Add size variants only if price differs</li>
                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Accurate stock helps on PDP</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i> Weight displays exactly as you type</li>
                  </ul>
                </div>
              </div>
            </div>
          </div> <!-- /row -->
        </form>

      </div>
    </div>
  </div>
</div>

<script>
let sizeIdx = <?= !empty($_POST['size_rows']) ? count($_POST['size_rows']) : 0 ?>;

function previewMain(input){
  const img = document.getElementById('preview');
  if (input?.files?.[0]) { img.src = URL.createObjectURL(input.files[0]); img.style.display='block'; }
  else { img.removeAttribute('src'); img.style.display='none'; }
}
function thumbPreview(input, img){ if (input?.files?.[0]) { img.src = URL.createObjectURL(input.files[0]); img.style.display='inline-block'; } }

function makeRemoveBtn(){ const b=document.createElement('button'); b.type='button'; b.className='btn btn-sm btn-outline-dark'; b.innerHTML='<i class="fas fa-trash-alt"></i>'; b.onclick=()=>b.closest('tr').remove(); return b; }
function createInput(props){ const i=document.createElement('input'); i.className='form-control form-control-sm'; Object.assign(i, props); return i; }
function td(el,label){ const td=document.createElement('td'); td.appendChild(el); if(label) td.setAttribute('data-label',label); return td; }

function addSizeRow(){
  const tbody = document.querySelector('#sizeTable tbody'); const tr = document.createElement('tr');
  const sizeInput  = createInput({name:`size_rows[${sizeIdx}][size]`, required:true, placeholder:'e.g. M'});
  const priceInput = createInput({name:`size_rows[${sizeIdx}][price]`, type:'number', step:'0.01', placeholder:'0.00'});
  const stockInput = createInput({name:`size_rows[${sizeIdx}][stock]`, type:'number', min:'0', value:'0'});

  const img = document.createElement('img'); img.className='thumb me-2'; img.style.display='none';
  const file = createInput({type:'file', name:`size_rows[${sizeIdx}][image]`, accept:'image/*'}); file.onchange=()=>thumbPreview(file,img);
  const wrap = document.createElement('div'); wrap.className='d-flex align-items-center gap-2'; wrap.appendChild(img); wrap.appendChild(file);

  const feat = document.createElement('input'); feat.type='radio'; feat.name='featured_choice'; feat.value=`variant-new:size:${sizeIdx}`;
  const actions = document.createElement('td'); actions.className='text-end'; actions.appendChild(makeRemoveBtn());

  tr.appendChild(td(sizeInput,'Size'));
  tr.appendChild(td(priceInput,'Price'));
  tr.appendChild(td(stockInput,'Stock'));
  tr.appendChild(td(wrap,'Image'));
  const ftd=document.createElement('td'); ftd.setAttribute('data-label','Featured'); ftd.appendChild(feat); tr.appendChild(ftd);
  tr.appendChild(actions);
  tbody.appendChild(tr);
  sizeIdx++;
}

// Gallery previews + featured radios
document.getElementById('gallery_files')?.addEventListener('change', function(){
  const previewWrap = document.getElementById('galleryPreview');
  const radiosWrap  = document.getElementById('featuredGalleryRadios');
  previewWrap.innerHTML = ''; radiosWrap.innerHTML = '';
  const files = this.files || [];
  Array.from(files).forEach((file, idx) => {
    const url = URL.createObjectURL(file);
    const img = document.createElement('img');
    img.src = url; img.style.width='72px'; img.style.height='72px'; img.style.objectFit='cover';
    img.className = 'rounded border me-2 mb-2';
    previewWrap.appendChild(img);
    const radioId = `feat_gallery_${idx}`;
    const div = document.createElement('div'); div.className = 'form-check mt-2';
    const radio = document.createElement('input'); radio.type='radio'; radio.name='featured_choice'; radio.id=radioId; radio.value=`gallery:${idx}`; radio.className='form-check-input';
    const label = document.createElement('label'); label.className='form-check-label'; label.setAttribute('for', radioId); label.textContent = `Use Gallery Image ${idx+1}`;
    div.appendChild(radio); div.appendChild(label);
    radiosWrap.appendChild(div);
  });
});

// Show/hide variants by product type
function toggleVariantBox(){
  const box = document.getElementById('variantSizeBox');
  const type = document.querySelector('input[name="product_type"]:checked')?.value || 'single';
  box.style.display = type === 'variant_size' ? '' : 'none';
}
document.getElementById('ptype_single')?.addEventListener('change', toggleVariantBox);
document.getElementById('ptype_variant')?.addEventListener('change', toggleVariantBox);

// AJAX load attributes when category changes (no form reload)
async function loadAttributesForCategory(catId){
  const occSel = document.getElementById('occasion_ids');
  const lenSel = document.getElementById('length_ids');
  const stySel = document.getElementById('style_ids');
  [occSel,lenSel,stySel].forEach(sel => sel.innerHTML='');
  if (!catId) return;

  try{
    const r = await fetch('attributes_api.php?category_id='+encodeURIComponent(catId), {credentials:'same-origin'});
    const j = await r.json();
    const addOpts = (sel, list)=>{
      (list||[]).forEach(o=>{
        const opt = document.createElement('option');
        opt.value = String(o.id); opt.textContent = o.value;
        sel.appendChild(opt);
      });
    };
    addOpts(occSel, j.occasion);
    addOpts(lenSel, j.length);
    addOpts(stySel, j.style);
  }catch(e){ console.error(e); }
}
document.getElementById('category_id')?.addEventListener('change', (e)=> loadAttributesForCategory(e.target.value));

document.addEventListener('DOMContentLoaded', () => {
  toggleVariantBox();
  const catSel = document.getElementById('category_id');
  if (catSel?.value) loadAttributesForCategory(catSel.value);

  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(f => {
    f.addEventListener('submit', e => { if (!f.checkValidity()) { e.preventDefault(); e.stopPropagation(); } f.classList.add('was-validated'); }, false);
  });

  // seed one empty row if variant mode and none exist
  if (document.querySelector('input[name="product_type"]:checked')?.value === 'variant_size') {
    if (document.querySelectorAll('#sizeTable tbody tr').length === 0) addSizeRow();
  }
});
</script>

<?php include __DIR__ . '/../partials/logout.php'; ?>
<?php include __DIR__ . '/../partials/script-js.php'; ?>
