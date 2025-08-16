<?php
// declare(strict_types=1);

// Bootstrap (config first, no output before this)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// ---------- Helpers (only if not already defined in functions.php) ----------
if (!function_exists('get_currencies')) {
    function get_currencies(PDO $pdo): array {
        $stmt = $pdo->query("SELECT code, symbol, is_base, rate_to_base FROM currencies");
        $rows = $stmt->fetchAll();
        $base = null; foreach ($rows as $r) { if (!empty($r['is_base'])) { $base = $r['code']; break; } }
        if (!$base && $rows) { $base = $rows[0]['code']; }
        return [$rows, $base ?: 'NGN'];
    }
}
if (!function_exists('fetch_categories')) {
    function fetch_categories(PDO $pdo): array {
        return $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC")->fetchAll();
    }
}
if (!function_exists('fetch_attributes_by_category')) {
    function fetch_attributes_by_category(PDO $pdo, int $category_id): array {
        $sql = "
          SELECT a.id, a.value, t.code AS type
          FROM category_attribute_allowed caa
          JOIN attributes a ON a.id = caa.attribute_id
          JOIN attribute_types t ON t.id = a.type_id
          WHERE caa.category_id = ?
          ORDER BY t.code, a.value
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id]);
        $res = ['occasion'=>[], 'length'=>[], 'style'=>[]];
        while ($row = $stmt->fetch()) {
            if (!isset($res[$row['type']])) { $res[$row['type']] = []; }
            $res[$row['type']][] = ['id' => (int)$row['id'], 'value' => $row['value']];
        }
        return $res;
    }
}
if (!function_exists('price_display')) {
    function price_display($value) { return number_format((float)$value, 2); }
}

// ---------- Currency context ----------
[$currencies, $baseCode] = get_currencies($pdo);
$display = isset($_GET['cur']) ? strtoupper((string)$_GET['cur']) : $baseCode;

$curMap = [];
foreach ($currencies as $c) { $curMap[$c['code']] = $c; }
if (!isset($curMap[$display])) { $display = $baseCode; }

// ---------- Category menu (for marquee) ----------
$cats = fetch_categories($pdo);
$menu = [];
foreach ($cats as $cat) {
    $allowed = fetch_attributes_by_category($pdo, (int)$cat['id']);
    $menu[] = [
        'id'      => (int)$cat['id'],
        'name'    => $cat['name'],
        'slug'    => $cat['slug'],
        'allowed' => $allowed
    ];
}

// ---------- Conversions ----------
function convert_price($amount, $fromCode, $toCode, $curMap) {
    if ($fromCode === $toCode) return $amount;
    $fromRate = (float)($curMap[$fromCode]['rate_to_base'] ?? 1);
    $toRate   = (float)($curMap[$toCode]['rate_to_base']   ?? 1);
    if ($fromRate <= 0 || $toRate <= 0) return $amount;
    $toBase = $amount * $fromRate;
    return $toBase / $toRate;
}

// ---------- Read filters (define BEFORE output to avoid warnings) ----------
$category_slug = isset($_GET['cat']) ? (string)$_GET['cat'] : '';
$occasion      = isset($_GET['occasion']) ? (string)$_GET['occasion'] : '';
$length        = isset($_GET['length']) ? (string)$_GET['length'] : '';
$style         = isset($_GET['style']) ? (string)$_GET['style'] : '';

// ---------- Build product query ----------
$where    = [];
$params   = [];
$joinAttr = '';

if ($category_slug !== '') {
    $where[]  = 'c.slug = ?';
    $params[] = $category_slug;
}

$attrFilters = [];
if ($occasion !== '') $attrFilters[] = ['type'=>'occasion','value'=>$occasion];
if ($length   !== '') $attrFilters[] = ['type'=>'length','value'=>$length];
if ($style    !== '') $attrFilters[] = ['type'=>'style','value'=>$style];

if ($attrFilters) {
    $i = 0;
    foreach ($attrFilters as $f) {
        $i++;
        $joinAttr .= "
          JOIN product_attributes pa{$i} ON pa{$i}.product_id = p.id
          JOIN attributes a{$i} ON a{$i}.id = pa{$i}.attribute_id
          JOIN attribute_types t{$i} ON t{$i}.id = a{$i}.type_id AND t{$i}.code = ?
        ";
        $where[]  = "a{$i}.value = ?";
        $params[] = $f['type'];
        $params[] = $f['value'];
    }
}

$sql = "
SELECT p.id, p.name, p.slug, p.sku, p.base_price, p.base_currency_code, p.image_path, c.name as cat_name
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
$joinAttr
" . ($where ? "WHERE " . implode(' AND ', $where) : "") . "
ORDER BY p.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// ---------- Small helper for image URL ----------
function product_image_url(?string $path): ?string {
    if (!$path) return null;
    // If already absolute (http/https) just return
    if (preg_match('~^https?://~i', $path)) return $path;
    // Normalize slashes
    $path = ltrim($path, '/');
    return BASE_URL . $path;
}

// symbol for display currency
$symbol = $curMap[$display]['symbol'] ?? '';
?>
<?php include __DIR__ . '/../includes/head.php'; ?>
<?php include __DIR__ . '/../includes/svg.php'; ?>
<?php include __DIR__ . '/../includes/mobile-header.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="position-relative">
  <?php
    // The scrolling categories component expects $menu and $display; both are set above.
    include __DIR__ . '/../scroll_categories.php';
  ?>
  <div class="mb-md-1 pb-xl-5"></div>

  <section class="shop-main container d-flex">

    <!-- shop sidebar -->
    <?php include __DIR__ . '/../includes/shop/sidebar.php'; ?>

    <div class="shop-list flex-grow-1">

      <!-- slide show -->
      <?php include __DIR__ . '/../includes/shop/slideshow.php'; ?>

      <!-- Active filter pills -->
      <div class="filters">
        <?php if ($category_slug !== ''): ?>
          <span class="pill">
            Category: <strong><?= htmlspecialchars(ucwords(str_replace('-', ' ', $category_slug))) ?></strong>
            <a class="x" href="<?= BASE_URL ?>shop/shop.php?cur=<?= urlencode($display) ?>" title="Clear">×</a>
          </span>
        <?php endif; ?>

        <?php if ($occasion !== ''): ?>
          <span class="pill">
            Occasion: <strong><?= htmlspecialchars($occasion) ?></strong>
            <a class="x" href="<?= BASE_URL ?>shop/shop.php?cat=<?= urlencode($category_slug) ?>&length=<?= urlencode($length) ?>&style=<?= urlencode($style) ?>&cur=<?= urlencode($display) ?>" title="Clear">×</a>
          </span>
        <?php endif; ?>

        <?php if ($length !== ''): ?>
          <span class="pill">
            Length: <strong><?= htmlspecialchars($length) ?></strong>
            <a class="x" href="<?= BASE_URL ?>shop/shop.php?cat=<?= urlencode($category_slug) ?>&occasion=<?= urlencode($occasion) ?>&style=<?= urlencode($style) ?>&cur=<?= urlencode($display) ?>" title="Clear">×</a>
          </span>
        <?php endif; ?>

        <?php if ($style !== ''): ?>
          <span class="pill">
            Style: <strong><?= htmlspecialchars($style) ?></strong>
            <a class="x" href="<?= BASE_URL ?>shop/shop.php?cat=<?= urlencode($category_slug) ?>&occasion=<?= urlencode($occasion) ?>&length=<?= urlencode($length) ?>&cur=<?= urlencode($display) ?>" title="Clear">×</a>
          </span>
        <?php endif; ?>

        <?php if ($category_slug !== '' || $occasion !== '' || $length !== '' || $style !== ''): ?>
          <a class="pill" href="<?= BASE_URL ?>shop/shop.php?cur=<?= urlencode($display) ?>">Clear all</a>
        <?php endif; ?>
      </div>

      <div class="mb-3 pb-2 pb-xl-3"></div>

      <div class="d-flex justify-content-between mb-4 pb-md-2">
        <div class="breadcrumb mb-0 d-none d-md-block flex-grow-1">
          <a href="<?= BASE_URL ?>" class="menu-link menu-link_us-s text-uppercase fw-medium">Home</a>
          <span class="breadcrumb-separator menu-link fw-medium ps-1 pe-1">/</span>
          <a href="<?= BASE_URL ?>shop/shop.php" class="menu-link menu-link_us-s text-uppercase fw-medium">The Shop</a>
        </div><!-- /.breadcrumb -->

        <div class="shop-acs d-flex align-items-center justify-content-between justify-content-md-end flex-grow-1">
          <h5 class="text-uppercase text-muted mt-2">ALL</h5>

          <div class="shop-asc__seprator mx-3 bg-light d-none d-md-block order-md-0"></div>

          <div class="col-size align-items-center order-1 d-none d-lg-flex">
            <span class="text-uppercase fw-medium me-2">View</span>
            <button class="btn-link fw-medium me-2 js-cols-size" data-target="products-grid" data-cols="2">2</button>
            <button class="btn-link fw-medium me-2 js-cols-size" data-target="products-grid" data-cols="3">3</button>
            <button class="btn-link fw-medium js-cols-size" data-target="products-grid" data-cols="4">4</button>
          </div><!-- /.col-size -->

          <div class="shop-filter d-flex align-items-center order-0 order-md-3 d-lg-none">
            <button class="btn-link btn-link_f d-flex align-items-center ps-0 js-open-aside" data-aside="shopFilter">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-filter me-2" viewBox="0 0 16 16">
                <path d="M6 10.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5m-2-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m-2-3a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5"/>
              </svg>
              <span class="text-uppercase fw-medium d-inline-block align-middle">Filter</span>
            </button>
          </div>
        </div>
      </div>

      <div class="products-grid row row-cols-2 row-cols-md-3" id="products-grid">
        <?php foreach ($products as $p): ?>
          <?php
            $converted = convert_price((float)$p['base_price'], $p['base_currency_code'], $display, $curMap);
            $imgUrl    = product_image_url($p['image_path'] ?? null);
          ?>
          <div class="product-card-wrapper">
            <div class="product-card mb-3 mb-md-4 mb-xxl-5">
              <div class="pc__img-wrapper">
                <div class="swiper-container background-img js-swiper-slider" data-settings='{"resizeObserver": true}'>
                  <div class="swiper-wrapper">
                    <div class="swiper-slide">
                      <a href="<?= BASE_URL ?>product.php?slug=<?= urlencode($p['slug']) ?>&cur=<?= urlencode($display) ?>">
                        <?php if ($imgUrl): ?>
                          <img src="<?= htmlspecialchars($imgUrl) ?>"
                               alt="<?= htmlspecialchars($p['name']) ?>"
                               width="330" height="400" class="pc__img">
                        <?php else: ?>
                          <span class="muted">No Image</span>
                        <?php endif; ?>
                      </a>
                    </div><!-- /.swiper-slide -->
                  </div>
                  <span class="pc__img-prev">
                    <svg width="7" height="11" viewBox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_prev_sm"></use></svg>
                  </span>
                  <span class="pc__img-next">
                    <svg width="7" height="11" viewBox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_next_sm"></use></svg>
                  </span>
                </div>
                <button class="pc__atc btn anim_appear-bottom position-absolute border-0 text-uppercase fw-medium js-add-cart js-open-aside" data-aside="cartDrawer" title="Add To Cart">Add To Cart</button>
              </div>

              <div class="pc__info position-relative px-2">
                <p class="pc__category"><?= htmlspecialchars($p['cat_name'] ?? '') ?></p>
                <h6 class="pc__title">
                  <a href="<?= BASE_URL ?>product.php?slug=<?= urlencode($p['slug']) ?>&cur=<?= urlencode($display) ?>">
                    <?= htmlspecialchars($p['name']) ?>
                  </a>
                </h6>
                <p><?= htmlspecialchars($p['sku']) ?></p>
                <div class="product-card__price d-flex">
                  <span class="money price"><?= $symbol . price_display($converted) ?></span>
                </div>
                <div class="product-card__review d-flex align-items-center">
                  <div class="reviews-group d-flex">
                    <svg class="review-star" viewBox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    <svg class="review-star" viewBox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    <svg class="review-star" viewBox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    <svg class="review-star" viewBox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    <svg class="review-star" viewBox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                  </div>
                  <span class="reviews-note text-lowercase text-secondary ms-1">8k+ reviews</span>
                </div>

                <button class="pc__btn-wl position-absolute top-0 end-0 bg-transparent border-0 js-add-wishlist" title="Add To Wishlist">
                  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><use href="#icon_heart"></use></svg>
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div><!-- /.products-grid row -->

      <nav class="shop-pages d-flex justify-content-between mt-3" aria-label="Page navigation">
        <a href="#" class="btn-link d-inline-flex align-items-center">
          <svg class="me-1" width="7" height="11" viewBox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_prev_sm"></use></svg>
          <span class="fw-medium">PREV</span>
        </a>
        <ul class="pagination mb-0">
          <li class="page-item"><a class="btn-link px-1 mx-2 btn-link_active" href="#">1</a></li>
          <li class="page-item"><a class="btn-link px-1 mx-2" href="#">2</a></li>
          <li class="page-item"><a class="btn-link px-1 mx-2" href="#">3</a></li>
          <li class="page-item"><a class="btn-link px-1 mx-2" href="#">4</a></li>
        </ul>
        <a href="#" class="btn-link d-inline-flex align-items-center">
          <span class="fw-medium me-1">NEXT</span>
          <svg width="7" height="11" viewBox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_next_sm"></use></svg>
        </a>
      </nav>

    </div><!-- /.shop-list -->
  </section><!-- /.shop-main container -->
</main>

<div class="mb-5 pb-xl-5"></div>

<!-- footer -->
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/mobile-footer.php'; ?>
<?php include __DIR__ . '/../includes/aside-form.php'; ?>
<?php include __DIR__ . '/../includes/cart-aside.php'; ?>
<?php include __DIR__ . '/../includes/sitemap-nav.php'; ?>
<?php include __DIR__ . '/../includes/scroll.php'; ?>
<?php include __DIR__ . '/../includes/script-footer.php'; ?>
