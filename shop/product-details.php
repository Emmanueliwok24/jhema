<?php
// shop/product-details.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = isset($_GET['slug']) ? (string)$_GET['slug'] : '';
if ($slug === '') {
    header('Location: ' . rtrim(BASE_URL, '/') . '/shop/shop.php');
    exit;
}

/* ---- Currency context ---- */
if (!function_exists('get_currencies')) {
    function get_currencies(PDO $pdo): array {
        try {
            $rows = $pdo->query("
                SELECT code, symbol, is_base, rate_to_base
                FROM currencies
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows = $pdo->query("
                SELECT code, is_base
                FROM currencies
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$r) { $r['symbol']=null; $r['rate_to_base']=1; }
        }
        $base = null;
        foreach ($rows as $r) { if (!empty($r['is_base'])) { $base=$r['code']; break; } }
        if (!$base) { $base = $rows[0]['code'] ?? 'NGN'; }
        if (!$rows) { $rows = [['code'=>'NGN','symbol'=>'₦','is_base'=>1,'rate_to_base'=>1]]; $base='NGN'; }
        return [$rows, $base];
    }
}
[$currencies, $baseCode] = get_currencies($pdo);
$curMap = []; foreach ($currencies as $c) { $curMap[$c['code']] = $c; }
$display = isset($_GET['cur']) ? strtoupper((string)$_GET['cur']) : $baseCode;
if (empty($curMap[$display])) $display = $baseCode;

/* ---- Product + images/featured (include typed weight) ---- */
$stmt = $pdo->prepare("
  SELECT
    p.*,
    p.weight_kg_tmp,      -- exact text, show this every time
    c.name AS cat_name,
    c.slug AS cat_slug,
    COALESCE(
      (SELECT pv.image_path FROM product_variants pv WHERE pv.id = p.featured_variant_id AND pv.image_path IS NOT NULL LIMIT 1),
      (SELECT pi.image_path FROM product_images pi  WHERE pi.id = p.featured_image_id  AND pi.image_path IS NOT NULL LIMIT 1),
      NULLIF(p.image_path, ''),
      (SELECT pi2.image_path FROM product_images pi2 WHERE pi2.product_id = p.id AND pi2.image_path IS NOT NULL ORDER BY pi2.is_main DESC, pi2.sort_order ASC, pi2.id ASC LIMIT 1)
    ) AS featured_image
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.slug = ?
  LIMIT 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/shop/shop.php');
    exit;
}
$product_id = (int)$product['id'];

/* ---- Attributes (optional) ---- */
$attrStmt = $pdo->prepare("
    SELECT t.code AS type_code, a.value
    FROM product_attributes pa
    JOIN attributes a ON a.id = pa.attribute_id
    JOIN attribute_types t ON t.id = a.type_id
    WHERE pa.product_id = ?
    ORDER BY t.code, a.value
");
$attrStmt->execute([$product_id]);
$attrRows = $attrStmt->fetchAll(PDO::FETCH_ASSOC);
$attrByType = ['occasion'=>[], 'length'=>[], 'style'=>[]];
foreach ($attrRows as $r) {
  $code = strtolower((string)$r['type_code']);
  if (isset($attrByType[$code])) $attrByType[$code][] = (string)$r['value'];
}

/* ---- Variants: SIZES ONLY (each with optional own price/image/stock) ---- */
$vstmt = $pdo->prepare("
  SELECT id, size, price, stock, image_path
  FROM product_variants
  WHERE product_id = ? AND (type = 'size' OR type IS NULL)
  ORDER BY id ASC
");
$vstmt->execute([$product_id]);
$variants = $vstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* Build size list and size->variant map */
$sizes = [];
$variantMap = []; // key: size string
foreach ($variants as $v) {
  $size = trim((string)($v['size'] ?? ''));
  if ($size === '') continue;
  $sizes[$size] = true;
  $variantMap[$size] = [
    'price' => ($v['price'] !== null && $v['price'] !== '') ? (float)$v['price'] : (float)($product['base_price'] ?? 0),
    'stock' => isset($v['stock']) ? (int)$v['stock'] : null,
    'image' => $v['image_path'] ?: null,
    'id'    => (int)$v['id']
  ];
}
$sizes = array_keys($sizes);

/* ---- Image list ---- */
$mainRel = $product['featured_image'] ?: ($product['image_path'] ?: null);
$gi = $pdo->prepare("SELECT id, image_path FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order ASC, id ASC");
$gi->execute([$product_id]);
$galleryRel = $gi->fetchAll(PDO::FETCH_ASSOC) ?: [];
$mainAbs = $mainRel ? product_image_url($mainRel) : null;
$galleryAbs = array_map(fn($img)=> product_image_url($img['image_path']), $galleryRel);
$slideImages = [];
if ($mainAbs) $slideImages[] = $mainAbs;
foreach ($galleryAbs as $img) if ($img && !in_array($img, $slideImages, true)) $slideImages[] = $img;
foreach ($variantMap as $variant) {
  if ($variant['image']) {
    $img = product_image_url($variant['image']);
    if ($img && !in_array($img, $slideImages, true)) $slideImages[] = $img;
  }
}

/* ---- JS data (sizes only) ---- */
$jsVariantMap = [];
foreach ($variantMap as $size=>$variant) {
  $jsVariantMap[$size] = [
    'price'=>$variant['price'],
    'stock'=>$variant['stock'],
    'image'=>$variant['image'] ? product_image_url($variant['image']) : null,
    'id'=>$variant['id']
  ];
}
$jsData = [
  'variantMap'      => $jsVariantMap,
  'basePrice'       => (float)($product['base_price'] ?? 0),
  'baseCurrency'    => (string)($product['base_currency_code'] ?? 'NGN'),
  'displayCurrency' => $display,
  'currencies'      => $curMap,
  'slides'          => $slideImages,
  'hasSizes'        => !empty($sizes),
];

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/svg.php';
include __DIR__ . '/../includes/mobile-header.php';
include __DIR__ . '/../includes/header.php';

/* helper: show minimal numeric string (fallback when no typed weight) */
$min_num_str = function($n): string {
  $s = rtrim(rtrim(number_format((float)$n, 6, '.', ''), '0'), '.');
  return ($s === '') ? '0' : $s;
};
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightslider@1.1.6/dist/css/lightslider.min.css"/>

<style>
  :root { --lux-bg:#f6f3ee; --lux-card:#fff; --lux-ink:#0f0f0f; --lux-sub:#5b5b5b; --lux-line:#e7e1d8; }
  body { color: var(--lux-ink); -webkit-font-smoothing: antialiased; }
  .lux-hr { border-top:1px solid var(--lux-line); opacity:1; }
  .muted { color:var(--lux-sub); }
  .price { font-size:2rem; font-weight:800; }
  .imgbox { background:#faf7f2; border:1px solid var(--lux-line); border-radius:16px; overflow:hidden; }
  .imgbox .lslide img { width:100%; height:auto; object-fit:contain; }
  .lSSlideOuter { border-radius:16px; }
  .lSSlideOuter .lSPager.lSGallery li { border:1px solid var(--lux-line); border-radius:12px; overflow:hidden; background:#fff; }
  .lSSlideOuter .lSPager.lSGallery li.active { outline:2px solid #111; }
  .swatch-grid { display:flex; flex-wrap:wrap; gap:.75rem; }
  .swatch { display:flex; align-items:center; gap:.5rem; padding:.4rem .55rem; border:1px solid var(--lux-line); border-radius:9999px; background:#fff; cursor:pointer; }
  .swatch.active { border-color:#111; box-shadow:0 0 0 2px rgba(17,17,17,.08) inset; }
  .swatch.disabled { opacity:.45; filter:grayscale(30%); pointer-events:none; }
  .swatch .thumb { width:34px; height:34px; border-radius:8px; border:1px solid var(--lux-line); object-fit:cover; flex-shrink:0; }
  .chips { display:flex; gap:8px; flex-wrap:wrap; }
  .chip { background:#f1f1f1; border-radius:9999px; padding:4px 10px; font-size:.8rem; }
</style>

<main class="position-relative product-details">
  <?php include __DIR__ . '/../scroll_categories.php'; ?>

  <div class="mb-md-1 pb-md-3"></div>

  <section class="product-single container">
    <div class="row">
      <!-- Product Images -->
      <div class="col-lg-7">
        <div class="product-single__media">
          <?php if (!empty($slideImages)): ?>
            <ul id="productGallery" class="imgbox lightSlider">
              <?php foreach ($slideImages as $img): ?>
                <li data-thumb="<?= htmlspecialchars($img) ?>">
                  <img loading="lazy" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($product['name'] ?? '') ?>">
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="imgbox d-flex align-items-center justify-content-center" style="aspect-ratio:1/1">
              <span class="text-secondary">No Image</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Product Info -->
      <div class="col-lg-5">
        <div class="d-flex justify-content-between mb-4 pb-md-2">
          <div class="breadcrumb mb-0 d-none d-md-block flex-grow-1">
            <a href="<?= htmlspecialchars(BASE_URL) ?>" class="menu-link menu-link_us-s text-uppercase fw-medium">Home</a>
            <span class="breadcrumb-separator menu-link fw-medium ps-1 pe-1">/</span>
            <a href="<?= htmlspecialchars(BASE_URL . 'shop/shop.php?cur=' . rawurlencode($display)) ?>" class="menu-link menu-link_us-s text-uppercase fw-medium">Shop</a>
          </div>
        </div>

        <div class="muted mb-1"><?= htmlspecialchars($product['cat_name'] ?? '') ?></div>
        <h1 class="product-single__name"><?= htmlspecialchars($product['name'] ?? '') ?></h1>
        <div class="muted">SKU: <?= htmlspecialchars($product['sku'] ?? '') ?></div>

        <div class="row g-3 mt-3">
          <div class="col-md-6">
            <label class="form-label">Display Currency</label>
            <select id="curSelect" class="form-select">
              <?php foreach ($currencies as $c): ?>
                <option value="<?= htmlspecialchars($c['code']) ?>" <?= $display === $c['code'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['code']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div>
              <div id="mainPrice" class="price"></div>
              <div id="stockNote" class="muted small mt-1"></div>
              <div class="muted small">Auto-updates with size &amp; currency.</div>
            </div>
          </div>
        </div>

        <div class="product-single__short-desc mt-3">
          <?php if (!empty($attrByType['occasion'])): ?>
            <div class="mt-2"><strong>Occasion:</strong> <span class="chips"><?php foreach ($attrByType['occasion'] as $v) echo '<span class="chip">' . htmlspecialchars($v) . '</span>'; ?></span></div>
          <?php endif; ?>
          <?php if (!empty($attrByType['length'])): ?>
            <div class="mt-1"><strong>Length:</strong> <span class="chips"><?php foreach ($attrByType['length'] as $v) echo '<span class="chip">' . htmlspecialchars($v) . '</span>'; ?></span></div>
          <?php endif; ?>
          <?php if (!empty($attrByType['style'])): ?>
            <div class="mt-1"><strong>Style:</strong> <span class="chips"><?php foreach ($attrByType['style'] as $v) echo '<span class="chip">' . htmlspecialchars($v) . '</span>'; ?></span></div>
          <?php endif; ?>
        </div>

        <hr class="lux-hr my-4">

        <?php if (!empty($sizes)): ?>
          <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label mb-0">Available Sizes</label>
              <button type="button" class="btn btn-sizeguide-link text-decoration-underline" data-bs-toggle="modal" data-bs-target="#sizeGuide">Size Guide</button>
            </div>
            <div id="sizeGrid" class="swatch-grid">
              <?php foreach ($sizes as $size):
                  $v = $variantMap[$size] ?? null;
                  $imgUrl = $v && $v['image'] ? product_image_url($v['image']) : $mainAbs;
              ?>
                <div class="swatch" data-size="<?= htmlspecialchars($size) ?>" data-image="<?= htmlspecialchars((string)$imgUrl) ?>" role="button" aria-pressed="false">
                  <?php if ($imgUrl): ?><img class="thumb" src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($size) ?>"><?php endif; ?>
                  <span class="label"><?= htmlspecialchars($size) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Add to cart area -->
        <div class="product-single__addtocart d-flex align-items-center gap-3 mt-3">
          <div class="qty-control position-relative">
            <input type="number" name="quantity" value="1" min="1" step="1" class="qty-control__number js-qty text-center">
            <div class="qty-control__reduce">-</div>
            <div class="qty-control__increase">+</div>
          </div>
          <button type="button" class="btn btn-primary btn-addtocart js-open-aside" data-aside="cartDrawer">Add to Cart</button>
        </div>

        <div class="product-single__addtolinks mt-3">
          <a href="#" class="menu-link menu-link_us-s add-to-wishlist">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><use href="#icon_heart"></use></svg>
            <span>Add to Wishlist</span>
          </a>
        </div>

        <div class="product-single__meta-info mt-3">
          <div class="meta-item"><label>SKU:</label> <span><?= htmlspecialchars($product['sku'] ?? 'N/A') ?></span></div>
          <div class="meta-item"><label>Category:</label> <span><?= htmlspecialchars($product['cat_name'] ?? '') ?></span></div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="product-single__details-tab mt-5">
      <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
          <a class="nav-link nav-link_underscore active" id="tab-description-tab" data-bs-toggle="tab" href="#tab-description" role="tab" aria-controls="tab-description" aria-selected="true">Description</a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link nav-link_underscore" id="tab-additional-info-tab" data-bs-toggle="tab" href="#tab-additional-info" role="tab" aria-controls="tab-additional-info" aria-selected="false">Additional Information</a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link nav-link_underscore" id="tab-reviews-tab" data-bs-toggle="tab" href="#tab-reviews" role="tab" aria-controls="tab-reviews" aria-selected="false">Reviews</a>
        </li>
      </ul>

      <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-description" role="tabpanel" aria-labelledby="tab-description-tab">
          <div class="product-single__description">
            <p class="content"><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></p>
          </div>
        </div>
        <div class="tab-pane fade" id="tab-additional-info" role="tabpanel" aria-labelledby="tab-additional-info-tab">
          <div class="product-single__addtional-info">
            <?php
              $typedWeight = trim((string)($product['weight_kg_tmp'] ?? ''));
              $weightOut = $typedWeight !== '' ? $typedWeight : $min_num_str($product['weight_kg'] ?? 0);
            ?>
            <div class="item"><label class="h6">Weight</label> <span><?= htmlspecialchars($weightOut) ?> kg</span></div>
            <?php if (!empty($sizes)): ?><div class="item"><label class="h6">Size</label><span><?= htmlspecialchars(implode(', ', $sizes)) ?></span></div><?php endif; ?>
          </div>
        </div>
        <div class="tab-pane fade" id="tab-reviews" role="tabpanel" aria-labelledby="tab-reviews-tab">
          <h2 class="product-single__reviews-title">Reviews</h2>
        </div>
      </div>
    </div>
  </section>

  <?php include __DIR__ . '/related-product.php'; ?>
  <?php include __DIR__ . '/size-guide.php'; ?>
</main>

<div class="mb-5 pb-xl-5"></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/mobile-footer.php'; ?>
<?php include __DIR__ . '/../includes/aside-form.php'; ?>
<?php include __DIR__ . '/../includes/cart-aside.php'; ?>
<?php include __DIR__ . '/../includes/sitemap-nav.php'; ?>
<?php include __DIR__ . '/../includes/scroll.php'; ?>
<?php include __DIR__ . '/../includes/script-footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lightslider@1.1.6/dist/js/lightslider.min.js"></script>

<script>
  // ===== Server Data =====
  const productData = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  // ===== Elements =====
  const mainPrice = document.getElementById('mainPrice');
  const stockNote = document.getElementById('stockNote');
  const curSel    = document.getElementById('curSelect');
  const sizeGrid  = document.getElementById('sizeGrid');

  // ===== Helpers =====
  const convertPrice = (amount, fromCurrency, toCurrency) => {
    if (fromCurrency === toCurrency) return amount;
    const fromRate = productData.currencies[fromCurrency]?.rate_to_base || 1;
    const toRate   = productData.currencies[toCurrency]?.rate_to_base || 1;
    return (amount * fromRate) / toRate;
  };

  const getVariant = (size = '') => {
    if (size && productData.variantMap[size]) return productData.variantMap[size];
    return { price: productData.basePrice, stock: null, image: null, id: null };
  };

  let selectedSize  = '';
  let slider = null;

  jQuery(function($) {
    if ($('#productGallery').length) {
      slider = $('#productGallery').lightSlider({
        gallery: true, item: 1, loop: true, slideMargin: 0, thumbItem: 6, enableDrag: true,
        currentPagerPosition: 'left',
        responsive: [
          { breakpoint: 992, settings: { thumbItem: 6 } },
          { breakpoint: 768, settings: { thumbItem: 5 } },
          { breakpoint: 576, settings: { thumbItem: 4 } }
        ]
      });
    }
    updateUI();
  });

  const updateUI = () => {
    const variant = getVariant(selectedSize);
    const displayPrice = convertPrice(variant.price, productData.baseCurrency, productData.displayCurrency);
    const currencySymbol = productData.currencies[productData.displayCurrency]?.symbol || '';
    if (mainPrice) mainPrice.textContent = `${currencySymbol}${displayPrice.toFixed(2)}`;
    if (stockNote) stockNote.textContent = variant.stock !== null ? `Stock: ${variant.stock}` : '';

    if (variant.image && slider) {
      const slideIndex = productData.slides.indexOf(variant.image);
      if (slideIndex >= 0) slider.goToSlide(slideIndex + 1);
    }
    updateSwatchStates();
  };

  const updateSwatchStates = () => {
    if (!sizeGrid) return;
    sizeGrid.querySelectorAll('.swatch').forEach(swatch => {
      const size = swatch.dataset.size || '';
      const isValid = !!getVariant(size).id || !!productData.variantMap[size];
      swatch.classList.toggle('disabled', !isValid);
    });
  };

  // Swatches
  sizeGrid?.addEventListener('click', e => {
    const swatch = e.target.closest('.swatch'); if (!swatch || swatch.classList.contains('disabled')) return;
    const size = swatch.dataset.size || '';
    if (selectedSize === size) {
      selectedSize = ''; sizeGrid.querySelectorAll('.swatch').forEach(s => s.classList.remove('active'));
    } else {
      selectedSize = size; sizeGrid.querySelectorAll('.swatch').forEach(s => s.classList.remove('active')); swatch.classList.add('active');
      if (swatch.dataset.image && slider) {
        const idx = productData.slides.indexOf(swatch.dataset.image);
        if (idx >= 0) slider.goToSlide(idx + 1);
      }
    }
    updateUI();
  });

  // Currency
  curSel?.addEventListener('change', () => {
    productData.displayCurrency = curSel.value;
    updateUI();
    const url = new URL(window.location.href);
    url.searchParams.set('cur', productData.displayCurrency);
    window.history.replaceState({}, '', url);
  });

  // Quantity local guard
  const qtyWrap = document.querySelector('.product-single .qty-control');
  qtyWrap?.addEventListener('change', (e) => {
    const input = e.target.closest('.qty-control__number, .js-qty');
    if (!input) return;
    let v = parseInt(input.value || '1', 10); if (!Number.isFinite(v) || v < 1) v = 1; input.value = v;
  });

  // Add to Cart (variant-aware)
  document.querySelector('.btn-addtocart')?.addEventListener('click', async (e) => {
    e.preventDefault();

    const qtyInput = document.querySelector('.product-single .qty-control__number');
    const quantity = Math.max(1, parseInt(qtyInput?.value || '1', 10));

    // If product has sizes, require one
    if (productData.hasSizes && !selectedSize) {
      alert('Please select a size.');
      return;
    }

    const chosen = getVariant(selectedSize);
    const fd = new FormData();
    fd.append('slug', <?= json_encode($slug) ?>);
    fd.append('quantity', String(quantity));
    if (chosen.id) {
      fd.append('variant_id', String(chosen.id));
      fd.append('variant_label', `Size: ${selectedSize}`);
    }

    try {
      const r = await fetch(<?= json_encode(rtrim(BASE_URL,'/').'/api/cart/add.php') ?>, {
        method:'POST',
        body: fd,
        credentials:'same-origin'
      });
      const j = await r.json();

      if (!j.success) {
        if (j.login === true && j.loginUrl) {
          window.location.href = j.loginUrl;
          return;
        }
        throw new Error(j.message || 'Failed to add to cart');
      }

      // If your theme exposes a helper, refresh the drawer using server truth:
      if (typeof window.refreshCartDrawer === 'function') {
        await window.refreshCartDrawer(j);
      }

      // Open drawer via theme trigger:
      const opener = document.querySelector('.js-open-aside[data-aside="cartDrawer"]');
      opener?.click();

    } catch (err) {
      alert(err.message || 'Unable to add to cart');
    }
  });
</script>
