<?php
declare(strict_types=1);

// ---------- BOOTSTRAP ----------
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

$errors = [];
$success = false;

// Upload directory
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

function fetch_attributes_by_category(PDO $pdo, int $category_id): array {
    $stmt = $pdo->prepare("
        SELECT a.id, a.value, t.code AS type_code
        FROM attributes a
        JOIN attribute_types t ON t.id = a.type_id
        JOIN category_attribute_allowed caa ON caa.attribute_id = a.id
        WHERE caa.category_id = ?
        ORDER BY t.code, a.value
    ");
    $stmt->execute([$category_id]);
    $attributes = ['occasion' => [], 'length' => [], 'style' => []];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = strtolower($row['type_code']);
        if (!isset($attributes[$type])) $attributes[$type] = [];
        $attributes[$type][] = ['id' => (int)$row['id'], 'value' => $row['value']];
    }
    return $attributes;
}

function get_currencies(PDO $pdo): array {
    try {
        $currencies = $pdo->query("SELECT code, is_base FROM currencies ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
        $baseCurrency = 'NGN';
        foreach ($currencies as $currency) {
            if (!empty($currency['is_base'])) { $baseCurrency = $currency['code']; break; }
        }
        return [$currencies, $baseCurrency];
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

    $rel = PRODUCT_UPLOAD_BASE_REL ?: 'public/images/products';
    return rtrim($rel, '/\\') . '/' . $name;
}

// ---------- AJAX: load attributes for a category (no page reload) ----------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'attrs') {
    header('Content-Type: application/json');
    $catId = (int)($_GET['category_id'] ?? 0);
    $out = $catId ? fetch_attributes_by_category($pdo, $catId) : ['occasion'=>[], 'length'=>[], 'style'=>[]];
    echo json_encode(['success'=>true, 'data'=>$out]);
    exit;
}

// ---------- LOAD PRODUCT ----------
$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    header('Location: manage-products.php');
    exit;
}

$prodStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
$prodStmt->execute([$productId]);
$product = $prodStmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    header('Location: manage-products.php');
    exit;
}

$categories = fetch_categories($pdo);
[$currencies, $baseCurrency] = get_currencies($pdo);

// Attributes selected
$selAttrStmt = $pdo->prepare("
  SELECT a.id
  FROM product_attributes pa
  JOIN attributes a ON a.id = pa.attribute_id
  WHERE pa.product_id = ?
");
$selAttrStmt->execute([$productId]);
$selectedAttrIds = array_map('intval', array_column($selAttrStmt->fetchAll(PDO::FETCH_ASSOC),'id'));

// Allowed attributes for current category
$currentCatId = (int)($product['category_id'] ?? 0);
$allowedAttrs = $currentCatId ? fetch_attributes_by_category($pdo, $currentCatId)
                              : ['occasion' => [], 'length' => [], 'style' => []];

// Images
$imgMainStmt = $pdo->prepare("SELECT id, image_path FROM product_images WHERE product_id = ? AND is_main = 1 LIMIT 1");
$imgMainStmt->execute([$productId]);
$mainImage = $imgMainStmt->fetch(PDO::FETCH_ASSOC);

$galleryStmt = $pdo->prepare("SELECT id, image_path FROM product_images WHERE product_id = ? AND is_main = 0 ORDER BY sort_order ASC, id ASC");
$galleryStmt->execute([$productId]);
$gallery = $galleryStmt->fetchAll(PDO::FETCH_ASSOC);

// Variants (sizes only for editing purpose)
$varStmt = $pdo->prepare("SELECT id, size, price, stock, image_path, featured FROM product_variants WHERE product_id = ? AND (type = 'size' OR type IS NULL) ORDER BY id ASC");
$varStmt->execute([$productId]);
$sizeVariants = $varStmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- POST: UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Required
    $required = [
        'name'        => 'Product Name',
        'sku'         => 'SKU',
        'category_id' => 'Category',
        'base_price'  => 'Base Price',
        'weight_kg'   => 'Weight'
    ];
    foreach ($required as $field => $label) {
        if (!isset($_POST[$field]) || trim((string)$_POST[$field]) === '') {
            $errors[] = "$label is required.";
        }
    }

    $basePrice = isset($_POST['base_price']) ? (float)$_POST['base_price'] : 0.0;
    if ($basePrice < 0) $errors[] = 'Base Price cannot be negative.';

    // Exact-as-typed WEIGHT (truncate to 2dp, no rounding)
    $weightKg = null;
    $weightRaw = (string)($_POST['weight_kg'] ?? '');
    $weightRaw = str_replace(',', '.', trim($weightRaw));
    if ($weightRaw === '') {
        $errors[] = 'Weight (kg) is required.';
    } elseif (!preg_match('/^\d+(?:\.\d+)?$/', $weightRaw)) {
        $errors[] = 'Weight must be a number in kilograms (e.g., 0.5, 1, 2.25).';
    } else {
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $weightRaw, $m)) {
            $int = $m[1];
            $dec = isset($m[2]) ? substr($m[2], 0, 2) : '';
            $weightKg = $int . ($dec !== '' ? '.' . $dec : '');
        } else {
            $weightKg = $weightRaw;
        }
        if ((float)$weightKg <= 0.0) $errors[] = 'Weight must be greater than 0.';
    }

    // MAIN IMAGE (optional replace)
    $replaceMain = !empty($_FILES['image_file']) && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $newMainPath = null;
    if ($replaceMain) {
        try {
            $newMainPath = handle_file_upload($_FILES['image_file']);
        } catch (Throwable $e) {
            $errors[] = 'Main image: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Adjust slug only if name or sku changed (keep stable if possible)
            $name = trim((string)$_POST['name']);
            $sku  = trim((string)$_POST['sku']);
            $slug = $product['slug'];
            if ($name !== $product['name'] || $sku !== $product['sku']) {
                $baseSlug = slugify_strict($name) . '-' . slugify_strict($sku);
                if ($baseSlug === '-') $baseSlug = 'product';
                $slug = $baseSlug;
                $i = 1;
                while (true) {
                    $q = $pdo->prepare("SELECT id FROM products WHERE slug = ? AND id <> ? LIMIT 1");
                    $q->execute([$slug, $productId]);
                    if (!$q->fetchColumn()) break;
                    $slug = $baseSlug . '-' . $i++;
                }
            }

            // Update core product
            $upd = $pdo->prepare("
              UPDATE products SET
                category_id = :category_id,
                name        = :name,
                sku         = :sku,
                slug        = :slug,
                description = :description,
                base_price  = :base_price,
                base_currency_code = :base_currency_code,
                weight_kg   = :weight_kg,
                weight_kg_tmp = :weight_kg_tmp,
                updated_at  = NOW()
              WHERE id = :id
            ");
            $upd->execute([
              'category_id'        => (int)$_POST['category_id'],
              'name'               => $name,
              'sku'                => $sku,
              'slug'               => $slug,
              'description'        => trim((string)($_POST['description'] ?? '')),
              'base_price'         => $basePrice,
              'base_currency_code' => (string)($_POST['base_currency_code'] ?? $baseCurrency),
              'weight_kg'          => $weightKg,
              'weight_kg_tmp'      => (string)$_POST['weight_kg'], // as typed
              'id'                 => $productId
            ]);

            // Main image replace
            if ($newMainPath) {
                // set or insert main image row
                if ($mainImage) {
                    $pdo->prepare("UPDATE product_images SET image_path = ? WHERE id = ?")->execute([$newMainPath, (int)$mainImage['id']]);
                } else {
                    $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main, sort_order) VALUES (?, ?, 1, 0)")
                        ->execute([$productId, $newMainPath]);
                }
                // also put image_path on products for fallback
                $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?")->execute([$newMainPath, $productId]);
            }

            // Add any new gallery images
            if (!empty($_FILES['gallery_files']) && is_array($_FILES['gallery_files']['tmp_name'])) {
                $count = count($_FILES['gallery_files']['tmp_name']);
                for ($idx = 0; $idx < $count; $idx++) {
                    if (!empty($_FILES['gallery_files']['tmp_name'][$idx])) {
                        $galleryPath = handle_file_upload([
                            'name'     => $_FILES['gallery_files']['name'][$idx],
                            'type'     => $_FILES['gallery_files']['type'][$idx],
                            'tmp_name' => $_FILES['gallery_files']['tmp_name'][$idx],
                            'error'    => $_FILES['gallery_files']['error'][$idx],
                            'size'     => $_FILES['gallery_files']['size'][$idx],
                        ]);
                        if ($galleryPath) {
                            $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main, sort_order) VALUES (?, ?, 0, ?)")
                                ->execute([$productId, $galleryPath, ($idx + 1)]);
                        }
                    }
                }
            }

            // Remove gallery items if requested
            if (!empty($_POST['remove_image_ids']) && is_array($_POST['remove_image_ids'])) {
                $ids = array_map('intval', $_POST['remove_image_ids']);
                if ($ids) {
                    $in  = implode(',', array_fill(0, count($ids), '?'));
                    $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND is_main = 0 AND id IN ($in)")
                        ->execute(array_merge([$productId], $ids));
                }
            }

            // Size variants (overwrite by deleting all and re-inserting for simplicity)
            $pdo->prepare("DELETE FROM product_variants WHERE product_id = ? AND (type = 'size' OR type IS NULL)")->execute([$productId]);
            $featuredVariantId = null;

            $rows = $_POST['size_rows'] ?? [];
            foreach ($rows as $index => $variant) {
                $vSize  = trim((string)($variant['size'] ?? ''));
                if ($vSize === '') continue;

                $vPrice = isset($variant['price']) && $variant['price'] !== '' ? (float)$variant['price'] : null;
                $vStock = isset($variant['stock']) ? (int)$variant['stock'] : 0;

                $vImagePath = null;
                if (!empty($_FILES['size_rows']['tmp_name'][$index]['image'])) {
                    $file = [
                        'name'     => $_FILES['size_rows']['name'][$index]['image'],
                        'type'     => $_FILES['size_rows']['type'][$index]['image'],
                        'tmp_name' => $_FILES['size_rows']['tmp_name'][$index]['image'],
                        'error'    => $_FILES['size_rows']['error'][$index]['image'],
                        'size'     => $_FILES['size_rows']['size'][$index]['image'],
                    ];
                    $vImagePath = handle_file_upload($file);
                }

                $pdo->prepare("
                    INSERT INTO product_variants (product_id, type, size, color, price, stock, image_path, featured)
                    VALUES (:product_id, 'size', :size, NULL, :price, :stock, :image_path, 0)
                ")->execute([
                    'product_id' => $productId,
                    'size'       => $vSize,
                    'price'      => $vPrice,
                    'stock'      => $vStock,
                    'image_path' => $vImagePath
                ]);

                $thisVariantId = (int)$pdo->lastInsertId();
                if (isset($_POST['featured_choice']) && $_POST['featured_choice'] === "variant:size:{$index}") {
                    $featuredVariantId = $thisVariantId;
                }
            }

            // Update attributes selections
            $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?")->execute([$productId]);
            $attributeIds = array_merge(
                $_POST['occasion_ids'] ?? [],
                $_POST['length_ids']   ?? [],
                $_POST['style_ids']    ?? []
            );
            if (!empty($attributeIds)) {
                $attributeIds = array_unique(array_map('intval', $attributeIds));
                $ins = $pdo->prepare("INSERT INTO product_attributes (product_id, attribute_id) VALUES (?, ?)");
                foreach ($attributeIds as $aid) $ins->execute([$productId, $aid]);
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
            header("Location: manage-products.php?updated={$productId}");
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = "Error updating product: " . $e->getMessage();
            error_log("Product update error: " . $e->getMessage());
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
</style>

<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php include __DIR__ . '/../partials/page-header.php'; ?>
  <div class="page-body-wrapper">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="page-body">
      <div class="container">

        <div class="page-header mb-4">
          <h1 class="fw-bold">Edit Product</h1>
          <p class="text-muted">Update the product details below</p>
        </div>

        <?php if ($success): ?>
          <div class="alert alert-success mb-4">Product updated successfully!</div>
        <?php elseif (!empty($errors)): ?>
          <div class="alert alert-danger mb-4">
            <strong>Please fix these issues:</strong>
            <ul class="mb-0">
              <?php foreach ($errors as $error): ?>
                <li><?= $esc($error) ?></li>
              <?php endforeach; ?>
            </ul>
            <div class="small mt-2">
              A detailed error log is available at: <code><?= $esc(PRODUCT_ERR_LOG) ?></code>
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
                      <input type="text" name="name" class="form-control" value="<?= $esc($_POST['name'] ?? $product['name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label required-field">SKU</label>
                      <input type="text" name="sku" class="form-control" value="<?= $esc($_POST['sku'] ?? $product['sku']) ?>" required>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Description</label>
                      <textarea name="description" class="form-control" rows="3"><?= $esc($_POST['description'] ?? $product['description']) ?></textarea>
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
                        <span class="input-group-text"><?= $esc($product['base_currency_code'] ?? $baseCurrency) ?></span>
                        <input type="number" name="base_price" step="0.01" class="form-control"
                               value="<?= $esc($_POST['base_price'] ?? $product['base_price']) ?>" required>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label required-field">Currency</label>
                      <select name="base_currency_code" class="form-select" required>
                        <?php foreach ($currencies as $currency): ?>
                          <option value="<?= $esc($currency['code']) ?>"
                            <?= ($_POST['base_currency_code'] ?? $product['base_currency_code'] ?? $baseCurrency) === $currency['code'] ? 'selected' : '' ?>>
                            <?= $esc($currency['code']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label required-field">Weight (kg)</label>
                      <input type="text" name="weight_kg" inputmode="decimal" pattern="\d+(\.\d+)?" class="form-control"
                             value="<?= $esc($_POST['weight_kg'] ?? ($product['weight_kg_tmp'] !== '' ? $product['weight_kg_tmp'] : $product['weight_kg'])) ?>" required>
                      <div class="form-text">Exact as typed; up to 2 decimals; no rounding.</div>
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
                            <?= ((int)($_POST['category_id'] ?? $product['category_id']) === (int)$category['id']) ? 'selected' : '' ?>>
                            <?= $esc($category['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Occasions</label>
                      <select name="occasion_ids[]" id="occasion_ids" class="form-select" multiple size="3">
                        <?php foreach ($allowedAttrs['occasion'] as $o): ?>
                          <option value="<?= (int)$o['id'] ?>" <?= in_array((int)$o['id'], $_POST['occasion_ids'] ?? $selectedAttrIds, true) ? 'selected' : '' ?>>
                            <?= $esc($o['value']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Lengths</label>
                      <select name="length_ids[]" id="length_ids" class="form-select" multiple size="3">
                        <?php foreach ($allowedAttrs['length'] as $l): ?>
                          <option value="<?= (int)$l['id'] ?>" <?= in_array((int)$l['id'], $_POST['length_ids'] ?? $selectedAttrIds, true) ? 'selected' : '' ?>>
                            <?= $esc($l['value']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Styles</label>
                      <select name="style_ids[]" id="style_ids" class="form-select" multiple size="3">
                        <?php foreach ($allowedAttrs['style'] as $s): ?>
                          <option value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $_POST['style_ids'] ?? $selectedAttrIds, true) ? 'selected' : '' ?>>
                            <?= $esc($s['value']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <!-- SIZE VARIANTS ONLY -->
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h2>Size Variants</h2>
                  <small class="text-muted">Each size may have its own price</small>
                </div>
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0">Sizes</h3>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="addSizeRow()">
                      <i class="fas fa-plus me-1"></i> Add Size
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table variant-table" id="sizeTable">
                      <thead>
                        <tr>
                          <th>Size</th><th>Price</th><th>Stock</th><th>Image</th><th>Featured</th><th class="text-end"></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $prefillRows = $_POST['size_rows'] ?? null;
                        if ($prefillRows === null) {
                          // Build rows from DB
                          foreach ($sizeVariants as $idx => $row) {
                            $vid = (int)$row['id'];
                            ?>
                            <tr>
                              <td data-label="Size"><input type="text" name="size_rows[<?= $idx ?>][size]" class="form-control form-control-sm" value="<?= $esc($row['size']) ?>" required></td>
                              <td data-label="Price"><input type="number" step="0.01" name="size_rows[<?= $idx ?>][price]" class="form-control form-control-sm" value="<?= $esc($row['price']) ?>"></td>
                              <td data-label="Stock"><input type="number" name="size_rows[<?= $idx ?>][stock]" class="form-control form-control-sm" min="0" value="<?= $esc($row['stock']) ?>"></td>
                              <td data-label="Image">
                                <div class="d-flex align-items-center gap-2">
                                  <?php if (!empty($row['image_path'])): ?>
                                    <img src="<?= $esc(product_image_url($row['image_path'])) ?>" class="thumb" alt="">
                                  <?php endif; ?>
                                  <input type="file" name="size_rows[<?= $idx ?>][image]" class="form-control form-control-sm" accept="image/*">
                                </div>
                              </td>
                              <td data-label="Featured"><input type="radio" name="featured_choice" value="variant:size:<?= $idx ?>"></td>
                              <td class="text-end"><button type="button" class="btn btn-sm btn-outline-dark" onclick="this.closest('tr').remove()"><i class="fas fa-trash-alt"></i></button></td>
                            </tr>
                            <?php
                          }
                        } else {
                          foreach ($prefillRows as $idx => $row) { ?>
                            <tr>
                              <td data-label="Size"><input type="text" name="size_rows[<?= $idx ?>][size]" class="form-control form-control-sm" value="<?= $esc($row['size'] ?? '') ?>" required></td>
                              <td data-label="Price"><input type="number" step="0.01" name="size_rows[<?= $idx ?>][price]" class="form-control form-control-sm" value="<?= $esc($row['price'] ?? '') ?>"></td>
                              <td data-label="Stock"><input type="number" name="size_rows[<?= $idx ?>][stock]" class="form-control form-control-sm" min="0" value="<?= $esc($row['stock'] ?? 0) ?>"></td>
                              <td data-label="Image">
                                <input type="file" name="size_rows[<?= $idx ?>][image]" class="form-control form-control-sm" accept="image/*">
                              </td>
                              <td data-label="Featured"><input type="radio" name="featured_choice" value="variant:size:<?= $idx ?>"></td>
                              <td class="text-end"><button type="button" class="btn btn-sm btn-outline-dark" onclick="this.closest('tr').remove()"><i class="fas fa-trash-alt"></i></button></td>
                            </tr>
                          <?php }
                        } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-between mt-4">
                <a href="manage-products.php" class="btn btn-outline-dark">Back</a>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i> Save Changes</button>
              </div>
            </div>

            <!-- RIGHT -->
            <div class="col-lg-4">
              <div class="card mb-4">
                <div class="card-header"><h2>Media</h2></div>
                <div class="card-body">
                  <div class="mb-4">
                    <label class="form-label">Main Product Image</label>
                    <div class="mb-3">
                      <?php if ($mainImage): ?>
                        <img id="preview" class="img-fluid rounded border" src="<?= $esc(product_image_url($mainImage['image_path'])) ?>">
                      <?php else: ?>
                        <img id="preview" class="img-fluid rounded border" style="display:none;">
                      <?php endif; ?>
                    </div>
                    <input type="file" name="image_file" class="form-control" accept="image/*" onchange="previewMain(this)">
                    <div class="form-text">Leave empty to keep the current image</div>
                  </div>

                  <div class="mb-4">
                    <label class="form-label">Additional Images</label>
                    <input type="file" name="gallery_files[]" id="gallery_files" class="form-control" accept="image/*" multiple>
                    <div id="galleryPreview" class="d-flex flex-wrap gap-2 mt-3">
                      <?php foreach ($gallery as $g): ?>
                        <div class="position-relative">
                          <img src="<?= $esc(product_image_url($g['image_path'])) ?>" class="rounded border" style="width:72px;height:72px;object-fit:cover">
                          <label class="small d-block mt-1">
                            <input type="checkbox" name="remove_image_ids[]" value="<?= (int)$g['id'] ?>"> remove
                          </label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div>
                    <label class="form-label">Featured Image</label>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="featured_choice" id="feat_main" value="main" checked>
                      <label class="form-check-label" for="feat_main">Use Main Image</label>
                    </div>
                    <div id="featuredGalleryRadios" class="mt-2">
                      <?php foreach (array_values($gallery) as $idx=>$g): ?>
                        <div class="form-check mt-2">
                          <input class="form-check-input" type="radio" name="featured_choice" id="feat_gallery_<?= $idx ?>" value="gallery:<?= $idx ?>">
                          <label class="form-check-label" for="feat_gallery_<?= $idx ?>">Use Gallery Image <?= $idx+1 ?></label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <div class="form-text">Or select a variant image in the table</div>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header"><h2>Quick Tips</h2></div>
                <div class="card-body">
                  <ul class="list-unstyled small">
                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Use high-quality images (min. 800×800px)</li>
                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Sizes can carry own prices</li>
                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Exact weight is stored and shown as typed</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i> Category attributes update without reloading</li>
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
let sizeIdx = (function(){
  const tbody = document.querySelector('#sizeTable tbody');
  return tbody ? tbody.querySelectorAll('tr').length : 0;
})();

function previewMain(input){
  const img = document.getElementById('preview');
  if (input?.files?.[0]) { img.src = URL.createObjectURL(input.files[0]); img.style.display='block'; }
}

function addSizeRow(){
  const tbody = document.querySelector('#sizeTable tbody'); const tr = document.createElement('tr');
  const i = sizeIdx++;
  const mk = (name, props={}) => {
    const el = document.createElement('input');
    el.className = 'form-control form-control-sm';
    el.name = name; Object.assign(el, props);
    return el;
  }
  const td = (child,label)=>{ const c=document.createElement('td'); if(label)c.setAttribute('data-label',label); c.appendChild(child); return c; }

  const sizeInput  = mk(`size_rows[${i}][size]`, {required:true, placeholder:'e.g. M'});
  const priceInput = mk(`size_rows[${i}][price]`, {type:'number', step:'0.01', placeholder:'0.00'});
  const stockInput = mk(`size_rows[${i}][stock]`, {type:'number', min:'0', value:'0'});

  const file = mk(`size_rows[${i}][image]`, {type:'file', accept:'image/*'});

  const feat = document.createElement('input'); feat.type='radio'; feat.name='featured_choice'; feat.value=`variant:size:${i}`;

  const btnRm = document.createElement('button'); btnRm.type='button'; btnRm.className='btn btn-sm btn-outline-dark';
  btnRm.innerHTML='<i class="fas fa-trash-alt"></i>'; btnRm.onclick=()=>tr.remove();

  tr.appendChild(td(sizeInput,'Size'));
  tr.appendChild(td(priceInput,'Price'));
  tr.appendChild(td(stockInput,'Stock'));
  tr.appendChild(td(file,'Image'));
  const ftd=document.createElement('td'); ftd.setAttribute('data-label','Featured'); ftd.appendChild(feat); tr.appendChild(ftd);
  const act=document.createElement('td'); act.className='text-end'; act.appendChild(btnRm); tr.appendChild(act);

  tbody.appendChild(tr);
}

// Category change → load attributes via AJAX (no reload)
document.getElementById('category_id')?.addEventListener('change', async function(){
  const cid = this.value;
  try{
    const r = await fetch('?ajax=attrs&category_id='+encodeURIComponent(cid), {credentials:'same-origin'});
    const j = await r.json();
    if (!j.success) return;

    const opts = j.data || {occasion:[], length:[], style:[]};
    const mount = (id, list)=> {
      const sel = document.getElementById(id); if (!sel) return;
      const chosen = new Set(Array.from(sel.selectedOptions).map(o => o.value));
      sel.innerHTML = '';
      list.forEach(it => {
        const opt = document.createElement('option');
        opt.value = String(it.id);
        opt.textContent = it.value;
        if (chosen.has(String(it.id))) opt.selected = true;
        sel.appendChild(opt);
      });
    };
    mount('occasion_ids', opts.occasion);
    mount('length_ids',   opts.length);
    mount('style_ids',    opts.style);
  } catch(e){}
});

// bootstrap validation
document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(f => {
    f.addEventListener('submit', e => {
      if (!f.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      f.classList.add('was-validated');
    }, false);
  });
});
</script>

<?php include __DIR__ . '/../partials/logout.php'; ?>
<?php include __DIR__ . '/../partials/script-js.php'; ?>
