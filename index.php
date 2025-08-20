<?php
// index.php — Homepage (full)
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/svg.php';
require_once __DIR__ . '/includes/mobile-header.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';

/* ---------------- Helpers ---------------- */
if (!function_exists('price_display')) {
  function price_display($value) { return number_format((float)$value, 2); }
}
if (!function_exists('convert_price')) {
  /**
   * Convert amount from $from -> $to using currencies.rate_to_base
   * toBase = amount * rate_to_base(from)
   * toTarget = toBase / rate_to_base(to)
   */
  function convert_price(float $amount, string $from, string $to, array $map): float {
    $from = strtoupper($from); $to = strtoupper($to);
    if (!isset($map[$from]) || !isset($map[$to])) return $amount;
    $rf = (float)($map[$from]['rate_to_base'] ?? 1);
    $rt = (float)($map[$to]['rate_to_base']   ?? 1);
    if ($rf <= 0 || $rt <= 0) return $amount;
    return ($amount * $rf) / $rt;
  }
}
$esc  = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '';

/* ---------------- Currency ---------------- */
$display  = isset($_GET['cur']) ? strtoupper(trim((string)$_GET['cur'])) : 'NGN';
$curMap   = [];
$baseCode = 'NGN';

try {
  $stmt = $pdo->query("SELECT code, symbol, is_base, rate_to_base FROM currencies");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $curMap[$r['code']] = [
      'symbol'       => $r['symbol'],
      'is_base'      => (int)$r['is_base'],
      'rate_to_base' => (float)$r['rate_to_base'],
    ];
    if ((int)$r['is_base'] === 1) $baseCode = $r['code'];
  }
} catch (Throwable $e) { /* ignore; fallback below */ }

if (!isset($curMap[$display])) $display = $baseCode;
$getSymbol = function(string $code) use ($curMap) {
  if (isset($curMap[$code]['symbol']) && $curMap[$code]['symbol'] !== null && $curMap[$code]['symbol'] !== '') {
    return (string)$curMap[$code]['symbol'];
  }
  return match (strtoupper($code)) { 'USD'=>'$', 'EUR'=>'€', 'GBP'=>'£', 'NGN'=>'₦', default => '' };
};

/* ---------------- Categories ---------------- */
$cats = [];
try {
  $q = $pdo->query("SELECT name, slug FROM categories ORDER BY name ASC");
  $cats = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $cats = []; }

/* ---------------- Attribute Types ---------------- */
$typeMap = ['style'=>null, 'length'=>null, 'occasion'=>null];
try {
  $q = $pdo->query("SELECT id, code FROM attribute_types");
  foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $typeMap[strtolower((string)$t['code'])] = (int)$t['id'];
  }
} catch (Throwable $e) {}

/* ---------------- Attribute Values ---------------- */
$attrs = ['style'=>[], 'length'=>[], 'occasion'=>[]];
try {
  $as = $pdo->prepare("SELECT value FROM attributes WHERE type_id = ? ORDER BY value ASC");
  foreach (['style','length','occasion'] as $code) {
    if (!empty($typeMap[$code])) {
      $as->execute([$typeMap[$code]]);
      $attrs[$code] = $as->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
  }
} catch (Throwable $e) {}

/* ---------------- Optional Filters (for homepage slice) ---------------- */
$fCategory = isset($_GET['category']) ? trim((string)$_GET['category']) : null;
$fStyle    = isset($_GET['style'])    ? trim((string)$_GET['style'])    : null;
$fOcc      = isset($_GET['occasion']) ? trim((string)$_GET['occasion']) : null;
$fLen      = isset($_GET['length'])   ? trim((string)$_GET['length'])   : null;

/* ---------------- Trending Products (12)
   Prefers: featured variant image -> featured gallery image -> product.image_path -> first gallery
   Also exposes has_size_variants so the card can switch to “Select Options”
--------------------------------------------------------- */
$products = [];
try {
  $sql = "
    SELECT
      p.id, p.category_id, p.name, p.slug, p.sku, p.description,
      p.base_currency_code, p.base_price, p.image_path, p.created_at,
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
      EXISTS(
        SELECT 1
        FROM product_variants pv2
        WHERE pv2.product_id = p.id
          AND (pv2.type = 'size' OR pv2.type IS NULL)
          AND COALESCE(pv2.size, '') <> ''
      ) AS has_size_variants
    FROM products p
    JOIN categories c ON c.id = p.category_id
  ";

  $where = [];
  $bind  = [];

  if ($fCategory) {
    $where[] = "c.slug = :cat";
    $bind[':cat'] = $fCategory;
  }

  if ($fStyle || $fOcc || $fLen) {
    $sql .= "
      JOIN product_attributes pa ON pa.product_id = p.id
      JOIN attributes a ON a.id = pa.attribute_id
    ";
    $attrConds = [];
    if ($fStyle && !empty($typeMap['style'])) {
      $attrConds[] = "(a.type_id = :tid_style AND a.value = :v_style)";
      $bind[':tid_style'] = $typeMap['style'];
      $bind[':v_style']   = $fStyle;
    }
    if ($fOcc && !empty($typeMap['occasion'])) {
      $attrConds[] = "(a.type_id = :tid_occ AND a.value = :v_occ)";
      $bind[':tid_occ'] = $typeMap['occasion'];
      $bind[':v_occ']   = $fOcc;
    }
    if ($fLen && !empty($typeMap['length'])) {
      $attrConds[] = "(a.type_id = :tid_len AND a.value = :v_len)";
      $bind[':tid_len'] = $typeMap['length'];
      $bind[':v_len']   = $fLen;
    }
    if (!empty($attrConds)) {
      // require product to match at least one selected attribute
      $where[] = '(' . implode(' OR ', $attrConds) . ')';
    }
  }

  if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= " GROUP BY p.id, p.created_at ORDER BY p.created_at DESC, p.id DESC LIMIT 12";

  $st = $pdo->prepare($sql);
  $st->execute($bind);
  $products = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $products = [];
}
?>
<main class="position-relative">
  <?php include __DIR__ . '/scroll_categories.php'; ?>

  <!-- Slide Show -->
  <?php include __DIR__ . '/index-main/slide-show.php'; ?>

  <div class="mb-3 pb-3 mb-md-4 pb-md-4 mb-xl-5 pb-xl-5"></div>
  <div class="pb-1"></div>

  <!-- Collections -->
  <section class="collections-grid collections-grid_masonry" id="section-collections-grid_masonry">
    <div class="container h-md-100">
      <div class="row h-md-100">
        <div class="col-lg-6 h-md-100">
          <div class="collection-grid__item position-relative h-md-100">
            <div class="background-img" style="background-image: url('./images/collection_grid_1.jpg');"></div>
            <div class="content_abs content_bottom content_left content_bottom-md content_left-md">
              <p class="text-uppercase mb-1">Explore</p>
              <h3 class="text-uppercase"><strong>Shop by Style</strong></h3>
              <a href="<?= $BASE ?>shop/shop.php" class="btn-link default-underline text-uppercase fw-medium">Browse All</a>
            </div>
          </div>
        </div>

        <div class="col-lg-6 d-flex flex-column">
          <div class="collection-grid__item position-relative flex-grow-1 mb-lg-4">
            <div class="background-img" style="background-image: url('./images/collection_grid_2.jpg');"></div>
            <div class="content_abs content_bottom content_left content_bottom-md content_left-md">
              <p class="text-uppercase mb-1">Explore</p>
              <h3 class="text-uppercase"><strong>Shop by Occasion</strong></h3>
              <a href="<?= $BASE ?>shop/shop.php" class="btn-link default-underline text-uppercase fw-medium">Browse All</a>
            </div>
          </div>

          <div class="position-relative flex-grow-1 mt-lg-1">
            <div class="row h-md-100">
              <div class="col-md-6 h-md-100">
                <div class="collection-grid__item h-md-100 position-relative">
                  <div class="background-img" style="background-image: url('./images/collection_grid_3.jpg');"></div>
                  <div class="content_abs content_bottom content_left content_bottom-md content_left-md">
                    <p class="text-uppercase mb-1">Explore</p>
                    <h3 class="text-uppercase"><strong>Shop by Length</strong></h3>
                    <a href="<?= $BASE ?>shop/shop.php" class="btn-link default-underline text-uppercase fw-medium">Browse All</a>
                  </div>
                </div>
              </div>

              <div class="col-md-6 h-md-100">
                <div class="collection-grid__item h-md-100 position-relative">
                  <div class="background-img" style="background-color: #f5e6e0"></div>
                  <div class="content_abs content_bottom content_left content_bottom-md content_left-md">
                    <h3 class="text-uppercase"><strong>E-Gift</strong> Cards</h3>
                    <p class="mb-1">Surprise someone with the gift they really want.</p>
                    <a href="<?= $BASE ?>shop/shop.php" class="btn-link default-underline text-uppercase fw-medium">Shop Now</a>
                  </div>
                </div>
              </div>
            </div><!-- /.row -->
          </div>
        </div>
      </div><!-- /.row -->
    </div>
  </section>

  <div class="mb-4 pb-4 mb-xl-5 pb-xl-5"></div>

  <!-- ============ TRENDING PRODUCTS with Sidebar Filters ============ -->
  <section class="container">
    <div class="row">
      <!-- Sidebar -->
      <aside class="col-lg-3 mb-4 mb-lg-0">
        <div class="card shadow-sm border-0">
          <div class="card-body">
            <h5 class="text-uppercase mb-3">Categories</h5>
            <ul class="list-unstyled small">
              <?php if (!empty($cats)): ?>
                <?php foreach ($cats as $c): ?>
                  <li class="mb-2">
                    <a class="link-dark text-decoration-none"
                       href="<?= $BASE ?>index.php?category=<?= urlencode((string)$c['slug']) ?>&cur=<?= urlencode($display) ?>">
                      <?= $esc($c['name']) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li class="text-muted">No categories yet</li>
              <?php endif; ?>
            </ul>

            <hr class="my-3">

            <h6 class="text-uppercase mb-2">Style</h6>
            <ul class="list-unstyled small">
              <?php if (!empty($attrs['style'])): ?>
                <?php foreach ($attrs['style'] as $val): ?>
                  <li class="mb-1">
                    <a class="link-secondary text-decoration-none"
                       href="<?= $BASE ?>index.php?style=<?= urlencode((string)$val) ?>&cur=<?= urlencode($display) ?>">
                      <?= $esc($val) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li class="text-muted">—</li>
              <?php endif; ?>
            </ul>

            <h6 class="text-uppercase mt-3 mb-2">Occasion</h6>
            <ul class="list-unstyled small">
              <?php if (!empty($attrs['occasion'])): ?>
                <?php foreach ($attrs['occasion'] as $val): ?>
                  <li class="mb-1">
                    <a class="link-secondary text-decoration-none"
                       href="<?= $BASE ?>index.php?occasion=<?= urlencode((string)$val) ?>&cur=<?= urlencode($display) ?>">
                      <?= $esc($val) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li class="text-muted">—</li>
              <?php endif; ?>
            </ul>

            <h6 class="text-uppercase mt-3 mb-2">Length</h6>
            <ul class="list-unstyled small">
              <?php if (!empty($attrs['length'])): ?>
                <?php foreach ($attrs['length'] as $val): ?>
                  <li class="mb-1">
                    <a class="link-secondary text-decoration-none"
                       href="<?= $BASE ?>index.php?length=<?= urlencode((string)$val) ?>&cur=<?= urlencode($display) ?>">
                      <?= $esc($val) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li class="text-muted">—</li>
              <?php endif; ?>
            </ul>

            <hr class="my-3">
            <a class="btn btn-sm btn-outline-dark w-100"
               href="<?= $BASE ?>index.php?cur=<?= urlencode($display) ?>">Clear Filters</a>
          </div>
        </div>
      </aside>

      <!-- Products Grid -->
      <div class="col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="h5 text-uppercase mb-0">Trendy <strong>Products</strong></h2>
          <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">Currency:</span>
            <form method="get" class="d-inline-block">
              <?php
              foreach (['category'=>$fCategory,'style'=>$fStyle,'occasion'=>$fOcc,'length'=>$fLen] as $k=>$v) {
                if ($v !== null && $v !== '') echo '<input type="hidden" name="'.$esc($k).'" value="'.$esc($v).'">';
              }
              ?>
              <select name="cur" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach (array_keys($curMap) as $code): ?>
                  <option value="<?= $esc($code) ?>" <?= $code === $display ? 'selected' : '' ?>><?= $esc($code) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>

        <div class="row g-3 g-md-4">
          <?php if (!empty($products)): ?>
            <?php foreach ($products as $p):
              $slug     = (string)$p['slug'];
              $name     = (string)$p['name'];
              $cat      = (string)($p['cat_name'] ?? '');
              $price    = (float)$p['base_price'];
              $fromCode = (string)$p['base_currency_code'];
              $imgRel   = $p['featured_image'] ?: ($p['image_path'] ?? null);
              $imgUrl   = $imgRel ? product_image_url($imgRel) : null;
              $hasSize  = (bool)$p['has_size_variants'];

              $converted = convert_price($price, $fromCode, $display, $curMap);
              $symbol    = $getSymbol($display);
            ?>
              <div class="col-6 col-md-4 col-xl-3">
                <div class="product-card mb-2 mb-md-3 h-100" data-slug="<?= $esc($slug) ?>">
                  <div class="pc__img-wrapper">
                    <a href="<?= $BASE ?>shop/product-details.php?slug=<?= urlencode($slug) ?>&cur=<?= urlencode($display) ?>">
                      <?php if (!empty($imgUrl)): ?>
                        <img loading="lazy" src="<?= $esc($imgUrl) ?>" alt="<?= $esc($name) ?>" width="330" height="400" class="pc__img"/>
                      <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                          <span class="text-muted small">No Image</span>
                        </div>
                      <?php endif; ?>
                    </a>

                    <?php if ($hasSize): ?>
                      <!-- Variant products: prompt selection on PDP -->
                      <a class="pc__atc btn anim_appear-bottom position-absolute border-0 text-uppercase fw-medium"
                         href="<?= $BASE ?>shop/product-details.php?slug=<?= urlencode($slug) ?>&cur=<?= urlencode($display) ?>"
                         title="Select Options">
                        Select Options
                      </a>
                    <?php else: ?>
                      <!-- Simple products: Add to Cart inline -->
                      <button
                        class="pc__atc btn anim_appear-bottom position-absolute border-0 text-uppercase fw-medium js-open-aside js-add-to-cart"
                        data-aside="cartDrawer"
                        title="Add To Cart"
                        data-product="<?= $esc($slug) ?>"
                      >Add To Cart</button>
                    <?php endif; ?>
                  </div>

                  <div class="pc__info position-relative px-2">
                    <?php if ($cat !== ''): ?>
                      <p class="pc__category mb-1"><?= $esc($cat) ?></p>
                    <?php endif; ?>
                    <h6 class="pc__title mb-1">
                      <a href="<?= $BASE ?>shop/product-details.php?slug=<?= urlencode($slug) ?>&cur=<?= urlencode($display) ?>">
                        <?= $esc($name) ?>
                      </a>
                    </h6>
                    <div class="product-card__price d-flex">
                      <span class="money price"><?= $esc($symbol) . price_display($converted) ?></span>
                    </div>

                    <!-- Wishlist heart -->
                    <button class="pc__btn-wl position-absolute top-0 end-0 bg-transparent border-0 js-add-wishlist"
                            title="Add To Wishlist"
                            data-product="<?= $esc($slug) ?>">
                      <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <use href="#icon_heart"></use>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="alert alert-light border text-center mb-0">
                No products to display yet.
                <a class="alert-link" href="<?= $BASE ?>shop/shop.php?cur=<?= urlencode($display) ?>">Browse the shop</a>.
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="text-center mt-3">
          <a class="btn-link btn-link_lg default-underline text-uppercase fw-medium"
             href="<?= $BASE ?>shop/shop.php?cur=<?= urlencode($display) ?>">
            Discover More
          </a>
        </div>
      </div>
    </div>
  </section>

  <div class="mb-3 mb-xl-5 pb-1 pb-xl-5"></div>

  <!-- Weekly deal -->
  <section class="deal-timer position-relative d-flex align-items-end overflow-hidden" style="background-color: #ebebeb">
    <div class="background-img" style="background-image: url('./images/collection_grid_1.jpg')"></div>
    <div class="deal-timer-wrapper container position-relative">
      <div class="deal-timer__content pb-2 mb-3 pb-xl-5 mb-xl-3 mb-xxl-5">
        <p class="text_dash text-uppercase text-red fw-medium">Deal of the week</p>
        <h3 class="h1 text-uppercase"><strong>Spring</strong> Collection</h3>
        <a href="<?= $BASE ?>shop/shop.php" class="btn-link default-underline text-uppercase fw-medium mt-3">Shop Now</a>
      </div>
      <div class="position-relative d-flex align-items-center text-center pt-xxl-4 js-countdown" data-date="18-5-2024" data-time="06:50">
        <div class="day countdown-unit"><span class="countdown-num d-block"></span><span class="countdown-word fw-bold text-uppercase text-secondary">Days</span></div>
        <div class="hour countdown-unit"><span class="countdown-num d-block"></span><span class="countdown-word fw-bold text-uppercase text-secondary">Hours</span></div>
        <div class="min countdown-unit"><span class="countdown-num d-block"></span><span class="countdown-word fw-bold text-uppercase text-secondary">Mins</span></div>
        <div class="sec countdown-unit"><span class="countdown-num d-block"></span><span class="countdown-word fw-bold text-uppercase text-secondary">Sec</span></div>
      </div>
    </div>
  </section>

  <div class="mb-3 mb-xl-5 pb-1 pb-xl-5"></div>

  <!-- Banners -->
  <section class="grid-banner container mb-3">
    <div class="row">
      <div class="col-md-6">
        <div class="grid-banner__item grid-banner__item_rect position-relative mb-3">
          <div class="background-img" style="background-image: url('./images/banner_1.jpg')"></div>
          <div class="content_abs content_bottom content_left content_bottom-lg content_left-lg">
            <h6 class="text-uppercase text-white fw-medium mb-3">Starting At $19</h6>
            <h3 class="text-white mb-3">Women's T-Shirts</h3>
            <a href="<?= $BASE ?>shop/shop.php" class="btn-link default-underline text-uppercase text-white fw-medium">Shop Now</a>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="grid-banner__item grid-banner__item_rect position-relative mb-3">
          <div class="background-img" style="background-image: url('./images/banner_2.jpg')"></div>
          <div class="content_abs content_bottom content_left content_bottom-lg content_left-lg">
            <h6 class="text-uppercase fw-medium mb-3">Starting At $39</h6>
            <h3 class="mb-3">Men's Sportswear</h3>
            <a href="<?= $BASE ?>shop/shop.php" class="btn-link default-underline text-uppercase fw-medium">Shop Now</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="mb-5 pb-1 pb-xl-4"></div>

  <!-- Edition include -->
  <?php include __DIR__ . '/index-main/edition.php'; ?>

  <section class="service-promotion container mb-md-4 pb-md-4 mb-xl-5">
    <div class="row">
      <div class="col-md-4 text-center mb-5 mb-md-0">
        <div class="service-promotion__icon mb-4">
          <svg width="52" height="52" viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg">
            <use href="#icon_shipping"></use>
          </svg>
        </div>
        <h3 class="service-promotion__title h5 text-uppercase">Fast And Free Delivery</h3>
        <p class="service-promotion__content text-secondary">Free delivery for all orders over $140</p>
      </div>

      <div class="col-md-4 text-center mb-5 mb-md-0">
        <div class="service-promotion__icon mb-4">
          <svg width="53" height="52" viewBox="0 0 53 52" fill="none" xmlns="http://www.w3.org/2000/svg">
            <use href="#icon_headphone"></use>
          </svg>
        </div>
        <h3 class="service-promotion__title h5 text-uppercase">24/7 Customer Support</h3>
        <p class="service-promotion__content text-secondary">Friendly 24/7 customer support</p>
      </div>

      <div class="col-md-4 text-center mb-4 pb-1 mb-md-0">
        <div class="service-promotion__icon mb-4">
          <svg width="52" height="52" viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg">
            <use href="#icon_shield"></use>
          </svg>
        </div>
        <h3 class="service-promotion__title h5 text-uppercase">Money Back Guarantee</h3>
        <p class="service-promotion__content text-secondary">We return money within 30 days</p>
      </div>
    </div>
  </section>
</main>

<!-- footer -->
<?php include __DIR__ . '/includes/footer.php'; ?>
<?php include __DIR__ . '/includes/mobile-footer.php'; ?>

<!-- form -->
<?php include __DIR__ . '/includes/aside-form.php'; ?>

<!-- aside cart -->
<?php include __DIR__ . '/includes/cart-aside.php'; ?>

<!-- sitemap -->
<?php include __DIR__ . '/includes/sitemap-nav.php'; ?>

<!-- newsletter -->
<?php include __DIR__ . '/includes/newsletter.php'; ?>

<?php include __DIR__ . '/includes/scroll.php'; ?>

<!-- Wishlist API + JS (bind hearts & count) -->
<?php include __DIR__ . '/includes/wishlist.php'; ?>

<!-- script footer -->
<?php include __DIR__ . '/includes/script-footer.php'; ?>

<script>
/* ====== Index page cart helpers (mirrors shop page behavior) ====== */
(function(){
  const BASE_URL = <?= json_encode(rtrim(BASE_URL, '/') . '/') ?>;

  async function getCart() {
    try {
      const r = await fetch(BASE_URL + 'api/cart/get.php', {credentials: 'same-origin'});
      const j = await r.json();
      return j && j.success ? (j.cart || {items:[], subtotal:0, weight_kg:0, count:0}) : {items:[], subtotal:0, weight_kg:0, count:0};
    } catch(e) {
      return {items:[], subtotal:0, weight_kg:0, count:0};
    }
  }

  async function addToCartBySlug(slug, qty=1) {
    const fd = new FormData();
    fd.append('slug', slug);
    fd.append('quantity', String(qty));
    const r = await fetch(BASE_URL + 'api/cart/add.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });
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
    const anyOpenTrigger = document.querySelector('.js-open-aside[data-aside="cartDrawer"]');
    if (anyOpenTrigger) anyOpenTrigger.click();
    if (typeof loadCartIntoDrawer === 'function') {
      setTimeout(() => loadCartIntoDrawer(), 50);
    }
  }

  // Initial sync
  getCart().then(cart => {
    updateButtonsForCart(cart.items || []);
    if (typeof renderCartDrawer === 'function') renderCartDrawer(cart);
  });

  // Click handler: Add to Cart (simple products only)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-add-to-cart');
    if (!btn) return;

    // If already in cart, just open drawer
    if (btn.dataset.inCart === '1') return;

    e.preventDefault();
    e.stopPropagation();

    const slug = btn.dataset.product;
    if (!slug) return;

    const oldText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Adding…';

    try {
      const res = await addToCartBySlug(slug, 1);

      if (!res || !res.success) {
        if (res && res.login && res.loginUrl) {
          window.location.href = res.loginUrl;
          return;
        }
        throw new Error(res && res.message ? res.message : 'Could not add to cart.');
      }

      // Refresh buttons + drawer state
      const cart = (res.cart) ? res.cart : await getCart();
      updateButtonsForCart(cart.items || []);
      if (typeof renderCartDrawer === 'function') renderCartDrawer(cart);

      // Open drawer
      openCartDrawer();

    } catch (err) {
      alert(err.message || 'Failed to add to cart.');
      btn.textContent = oldText;
    } finally {
      btn.disabled = false;
    }
  });

  // Keep drawer fresh when opening via .js-open-aside
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.js-open-aside');
    if (trigger && trigger.dataset.aside === 'cartDrawer') {
      if (typeof loadCartIntoDrawer === 'function') {
        setTimeout(() => loadCartIntoDrawer(), 10);
      }
    }
  });
})();
</script>
