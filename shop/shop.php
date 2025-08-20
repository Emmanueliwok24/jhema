<?php
// shop/shop.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/wishlist.php';

/* ---------- Helpers ---------- */
if (!function_exists('get_currencies')) {
  function get_currencies(PDO $pdo): array {
    try {
      $rows = $pdo->query("SELECT code, symbol, is_base, rate_to_base FROM currencies")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
      $rows = $pdo->query("SELECT code, is_base FROM currencies")->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rows as &$r) { $r['symbol'] = null; $r['rate_to_base'] = 1; }
    }
    $base = null;
    foreach ($rows as $r) { if (!empty($r['is_base'])) { $base = $r['code']; break; } }
    if (!$base) { $base = $rows[0]['code'] ?? 'NGN'; }
    if (!$rows) { $rows = [['code'=>'NGN','symbol'=>'₦','is_base'=>1,'rate_to_base'=>1]]; $base='NGN'; }
    return [$rows, $base];
  }
}
if (!function_exists('fetch_categories')) {
  function fetch_categories(PDO $pdo): array {
    return $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}
if (!function_exists('fetch_attributes_by_category')) {
  function fetch_attributes_by_category(PDO $pdo, int $category_id): array {
    $sql = "
      SELECT a.id, a.value, t.code AS type_code
      FROM category_attribute_allowed caa
      JOIN attributes a ON a.id = caa.attribute_id
      JOIN attribute_types t ON t.id = a.type_id
      WHERE caa.category_id = ?
      ORDER BY t.code, a.value
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$category_id]);
    $out = ['occasion'=>[], 'length'=>[], 'style'=>[]];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $code = strtolower((string)$r['type_code']);
      if (isset($out[$code])) $out[$code][] = ['id'=>(int)$r['id'],'value'=>$r['value']];
    }
    return $out;
  }
}
if (!function_exists('price_display')) {
  function price_display($value) { return number_format((float)$value, 2); }
}
if (!function_exists('product_image_url')) {
  function product_image_url(?string $path): ?string {
    if (!$path) return null;
    $p = str_replace(['\\','public/'], ['/',''], $path);
    if (preg_match('~^https?://~i', $p)) return $p;
    $p = ltrim($p, '/');
    if (strpos($p, 'images/products/') !== 0) {
      $p = 'images/products/' . $p;
    }
    return rtrim(BASE_URL, '/') . '/' . $p;
  }
}

/* ---------- Currency ---------- */
[$currencies, $baseCode] = get_currencies($pdo);
$display = isset($_GET['cur']) ? strtoupper((string)$_GET['cur']) : $baseCode;
$curMap = []; foreach ($currencies as $c) $curMap[$c['code']] = $c;
if (empty($curMap[$display])) $display = $baseCode;
$symbol = $curMap[$display]['symbol'] ?? ([ 'USD'=>'$', 'EUR'=>'€', 'GBP'=>'£', 'NGN'=>'₦' ][$display] ?? '');

/* ---------- Conversion ---------- */
function convert_price(float $amount, string $fromCode, string $toCode, array $curMap): float {
  if ($fromCode === $toCode) return $amount;
  $fromRate = (float)($curMap[$fromCode]['rate_to_base'] ?? 1);
  $toRate   = (float)($curMap[$toCode]['rate_to_base']   ?? 1);
  if ($fromRate <= 0 || $toRate <= 0) return $amount;
  return ($amount * $fromRate) / $toRate;
}

/* ---------- Categories + menu ---------- */
$cats = fetch_categories($pdo);
$menu = [];
foreach ($cats as $c) {
  $menu[] = [
    'id'      => (int)$c['id'],
    'name'    => (string)$c['name'],
    'slug'    => (string)$c['slug'],
    'allowed' => fetch_attributes_by_category($pdo, (int)$c['id'])
  ];
}

/* ---------- Filters ---------- */
$category_slug = isset($_GET['cat']) ? (string)$_GET['cat'] : '';
$occasion      = isset($_GET['occasion']) ? (string)$_GET['occasion'] : '';
$length        = isset($_GET['length']) ? (string)$_GET['length'] : '';
$style         = isset($_GET['style']) ? (string)$_GET['style'] : '';
$q             = trim((string)($_GET['q'] ?? ''));

/* ---------- Pagination & Sorting ---------- */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(48, (int)($_GET['per'] ?? 12)));
$offset  = ($page - 1) * $perPage;
$sort = (string)($_GET['sort'] ?? 'newest');
$sortSql = match ($sort) {
  'price_asc'  => 'COALESCE(minv.min_price, p.base_price) ASC, p.id ASC',
  'price_desc' => 'COALESCE(maxv.max_price, p.base_price) DESC, p.id DESC',
  'name_asc'   => 'p.name ASC, p.id ASC',
  default      => 'p.created_at DESC, p.id DESC'
};

/* ---------- Build SQL ---------- */
$joins  = "
  LEFT JOIN categories c ON c.id = p.category_id
  LEFT JOIN (
    SELECT product_id, MIN(price) AS min_price
    FROM product_variants
    WHERE price IS NOT NULL AND type = 'size'
    GROUP BY product_id
  ) AS minv ON minv.product_id = p.id
  LEFT JOIN (
    SELECT product_id, MAX(price) AS max_price
    FROM product_variants
    WHERE price IS NOT NULL AND type = 'size'
    GROUP BY product_id
  ) AS maxv ON maxv.product_id = p.id
";
$where  = [];
$params = [];

/* Category */
if ($category_slug !== '') {
  $where[]        = "c.slug = :cat";
  $params[':cat'] = $category_slug;
}

/* Search */
if ($q !== '') {
  $where[]      = "(p.name LIKE :q OR p.sku LIKE :q OR c.name LIKE :q)";
  $params[':q'] = '%'.$q.'%';
}

/* Attribute filters (ALL) */
$filters = [];
if ($occasion !== '') $filters[] = ['code'=>'occasion','value'=>$occasion];
if ($length   !== '') $filters[] = ['code'=>'length',   'value'=>$length];
if ($style    !== '') $filters[] = ['code'=>'style',    'value'=>$style];

$i = 0;
foreach ($filters as $f) {
  $i++;
  $joins   .= "
    JOIN product_attributes pa{$i} ON pa{$i}.product_id = p.id
    JOIN attributes a{$i}         ON a{$i}.id = pa{$i}.attribute_id
    JOIN attribute_types t{$i}    ON t{$i}.id = a{$i}.type_id AND t{$i}.code = :type{$i}
  ";
  $where[]               = "a{$i}.value = :val{$i}";
  $params[":type{$i}"]   = $f['code'];
  $params[":val{$i}"]    = $f['value'];
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---------- Count ---------- */
$countSql = "
  SELECT COUNT(DISTINCT p.id)
  FROM products p
  $joins
  $whereSql
";
$cstmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) { $cstmt->bindValue($k, $v); }
$cstmt->execute();
$total = (int)$cstmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

/* ---------- Fetch list (featured image + price range) ---------- */
$listSql = "
  SELECT
    p.id, p.name, p.slug, p.sku, p.base_price, p.base_currency_code, p.image_path,
    p.featured_image_id, p.featured_variant_id, p.created_at,
    c.name AS cat_name,
    COALESCE(
      (SELECT pv.image_path
         FROM product_variants pv
        WHERE pv.id = p.featured_variant_id
          AND pv.image_path IS NOT NULL),
      (SELECT pi.image_path
         FROM product_images pi
        WHERE pi.id = p.featured_image_id
          AND pi.image_path IS NOT NULL),
      p.image_path,
      (SELECT pi2.image_path
         FROM product_images pi2
        WHERE pi2.product_id = p.id
        ORDER BY pi2.is_main DESC, pi2.sort_order ASC, pi2.id ASC
        LIMIT 1)
    ) AS featured_image,
    minv.min_price AS min_variant_price,
    maxv.max_price AS max_variant_price
  FROM products p
  $joins
  $whereSql
  GROUP BY p.id
  ORDER BY $sortSql
  LIMIT :lim OFFSET :off
";
$lstmt = $pdo->prepare($listSql);
foreach ($params as $k => $v) { $lstmt->bindValue($k, $v); }
$lstmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$lstmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$lstmt->execute();
$products = $lstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ---------- URL helper ---------- */
$buildUrl = function(array $override = []) use ($display, $category_slug, $occasion, $length, $style, $q, $page, $perPage, $sort) {
  $query = array_merge([
    'cur'      => $display,
    'cat'      => $category_slug,
    'occasion' => $occasion,
    'length'   => $length,
    'style'    => $style,
    'q'        => $q,
    'page'     => $page,
    'per'      => $perPage,
    'sort'     => $sort,
  ], $override);
  $query = array_filter($query, fn($v) => ($v !== '' && $v !== null));
  return rtrim(BASE_URL, '/') . '/shop/shop.php?' . http_build_query($query);
};

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/svg.php';
include __DIR__ . '/../includes/mobile-header.php';
include __DIR__ . '/../includes/header.php';
?>
<main class="position-relative">
  <?php include __DIR__ . '/../scroll_categories.php'; ?>

  <div class="mb-md-1 pb-xl-5"></div>

  <section class="shop-main container d-flex">

    <!-- Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="shop-list flex-grow-1">

      <!-- Slide show -->
      <?php include __DIR__ . '/slideshow.php'; ?>

      <?php if (!empty($_GET['added'])): ?>
        <div class="alert alert-success mt-3">Product added successfully!</div>
      <?php endif; ?>

      <!-- Top controls (keep existing) ... -->

      <!-- Products grid -->
      <div class="products-grid row row-cols-2 row-cols-md-3" id="products-grid">
        <?php if (!$products): ?>
          <div class="col-12 text-center text-secondary py-5">No products found.</div>
        <?php endif; ?>

        <?php foreach ($products as $p): ?>
          <?php
            $imgRel = $p['featured_image'] ?: $p['image_path'];
            $img    = $imgRel ? product_image_url($imgRel) : null;

            // Determine display price or range
            $base  = (float)$p['base_price'];
            $fromV = isset($p['min_variant_price']) && $p['min_variant_price'] !== null ? (float)$p['min_variant_price'] : null;
            $toV   = isset($p['max_variant_price']) && $p['max_variant_price'] !== null ? (float)$p['max_variant_price'] : null;

            // Convert to display currency
            $baseConv  = convert_price($base, (string)$p['base_currency_code'], $display, $curMap);
            $fromConv  = $fromV !== null ? convert_price($fromV, (string)$p['base_currency_code'], $display, $curMap) : null;
            $toConv    = $toV   !== null ? convert_price($toV,   (string)$p['base_currency_code'], $display, $curMap) : null;

            // Decide text: if both variant prices exist and differ, show range; else show single price
            $showRange = ($fromConv !== null && $toConv !== null && abs($toConv - $fromConv) >= 0.005);
            $priceText = $showRange
              ? ($symbol . price_display($fromConv) . ' - ' . $symbol . price_display($toConv))
              : ($symbol . price_display($fromConv !== null ? $fromConv : $baseConv));
          ?>
          <div class="product-card-wrapper">
            <div class="product-card mb-3 mb-md-4 mb-xxl-5" data-slug="<?= htmlspecialchars((string)$p['slug']) ?>">
              <div class="pc__img-wrapper">
                <a href="<?= htmlspecialchars(BASE_URL.'shop/product-details.php?slug='.rawurlencode((string)$p['slug']).'&cur='.rawurlencode($display)) ?>">
                  <?php if ($img): ?>
                    <img loading="lazy" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['name']) ?>" width="330" height="400" class="pc__img">
                  <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                      <span class="text-muted small">No Image</span>
                    </div>
                  <?php endif; ?>
                </a>
                <button
                  class="pc__atc btn anim_appear-bottom position-absolute border-0 text-uppercase fw-medium js-open-aside js-add-to-cart"
                  data-aside="cartDrawer"
                  title="Add To Cart"
                  data-product="<?= htmlspecialchars((string)$p['slug']) ?>"
                >Add To Cart</button>
              </div>

              <div class="pc__info position-relative px-2">
                <p class="pc__category"><?= htmlspecialchars($p['cat_name'] ?? '') ?></p>
                <h6 class="pc__title">
                  <a href="<?= htmlspecialchars(BASE_URL.'shop/product-details.php?slug='.rawurlencode((string)$p['slug']).'&cur='.rawurlencode($display)) ?>">
                    <?= htmlspecialchars($p['name']) ?>
                  </a>
                </h6>
                <div class="product-card__price d-flex">
                  <span class="money price"><?= $priceText ?></span>
                </div>

                <button class="pc__btn-wl position-absolute top-0 end-0 bg-transparent border-0 js-add-wishlist" title="Add To Wishlist" data-product="<?= htmlspecialchars((string)$p['slug']) ?>">
                  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><use href="#icon_heart"></use></svg>
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination (unchanged) ... -->

    </div><!-- /.shop-list -->
  </section><!-- /.shop-main container -->
</main>

<div class="mb-5 pb-xl-5"></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/mobile-footer.php'; ?>
<?php include __DIR__ . '/../includes/aside-form.php'; ?>
<?php include __DIR__ . '/../includes/cart-aside.php'; ?>
<?php include __DIR__ . '/../includes/sitemap-nav.php'; ?>
<?php include __DIR__ . '/../includes/scroll.php'; ?>
<?php include __DIR__ . '/../includes/script-footer.php'; ?>

<script>
(function(){
  const BASE_URL = <?= json_encode(rtrim(BASE_URL, '/') . '/') ?>;
  function money(n){ return '₦' + Number(n).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }

  async function getCart() {
    try {
      const r = await fetch(BASE_URL + 'api/cart/get.php', {credentials: 'same-origin'});
      return await r.json();
    } catch(e) { return {success:false}; }
  }
  async function addToCartBySlug(slug, qty=1) {
    const fd = new FormData();
    fd.append('slug', slug);
    fd.append('quantity', String(qty));
    const r = await fetch(BASE_URL + 'api/cart/add.php', { method: 'POST', credentials: 'same-origin', body: fd });
    return r.json();
  }
  function updateButtonsForCart(items){
    const inCartSlugs = new Set((items||[]).map(i => (i.slug||'').toString()));
    document.querySelectorAll('.js-add-to-cart').forEach(btn => {
      const slug = btn.dataset.product;
      if (slug && inCartSlugs.has(slug)) {
        btn.textContent = 'View Cart';
        btn.dataset.inCart = '1';
        btn.classList.add('btn-view-cart');
      } else {
        btn.textContent = 'Add To Cart';
        delete btn.dataset.inCart;
        btn.classList.remove('btn-view-cart');
      }
    });
  }
  function openCartDrawer(){
    const opener = document.querySelector('.js-open-aside[data-aside="cartDrawer"]');
    opener?.click();
    if (typeof loadCartIntoDrawer === 'function') setTimeout(() => loadCartIntoDrawer(), 50);
  }
  getCart().then(data => {
    if (data && data.success) {
      updateButtonsForCart(data.items || []);
      if (typeof renderCartDrawer === 'function') renderCartDrawer(data);
    }
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-add-to-cart');
    if (!btn) return;
    if (btn.dataset.inCart === '1') return; // let drawer open

    e.preventDefault();
    e.stopPropagation();

    const slug = btn.dataset.product; if (!slug) return;
    const oldText = btn.textContent;
    btn.disabled = true; btn.textContent = 'Adding…';
    try {
      const res = await addToCartBySlug(slug, 1);
      if (!res || !res.success) {
        if (res && res.login && res.loginUrl) { window.location.href = res.loginUrl; return; }
        throw new Error(res && res.message ? res.message : 'Could not add to cart.');
      }
      btn.textContent = 'View Cart'; btn.dataset.inCart = '1'; btn.classList.add('btn-view-cart');
      const data = await getCart();
      if (data && data.success) {
        updateButtonsForCart(data.items || []);
        if (typeof renderCartDrawer === 'function') renderCartDrawer(data);
      }
      openCartDrawer();
    } catch(err) {
      alert(err.message || 'Failed to add to cart.');
      btn.textContent = oldText;
    } finally {
      btn.disabled = false;
    }
  });

  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.js-open-aside');
    if (trigger && trigger.dataset.aside === 'cartDrawer') {
      if (typeof loadCartIntoDrawer === 'function') setTimeout(() => loadCartIntoDrawer(), 10);
    }
  });
})();
</script>
