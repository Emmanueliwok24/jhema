<?php
// account/account_wishlist.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';       // require_user(), csrf_*
require_once __DIR__ . '/../includes/auth_user.php';  // safe to include; shares same session keys

require_user(); // <-- unified guard

/* -------------------- Fallback helpers (same spirit as shop) -------------------- */
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
    if (!$base) $base = $rows[0]['code'] ?? 'NGN';
    if (!$rows) { $rows = [['code'=>'NGN','symbol'=>'₦','is_base'=>1,'rate_to_base'=>1]]; $base = 'NGN'; }
    return [$rows, $base];
  }
}
if (!function_exists('convert_price')) {
  function convert_price(float $amount, string $fromCode, string $toCode, array $curMap): float {
    if ($fromCode === $toCode) return $amount;
    $fromRate = (float)($curMap[$fromCode]['rate_to_base'] ?? 1);
    $toRate   = (float)($curMap[$toCode]['rate_to_base']   ?? 1);
    if ($fromRate <= 0 || $toRate <= 0) return $amount;
    return ($amount * $fromRate) / $toRate;
  }
}
if (!function_exists('price_display')) {
  function price_display($value) { return number_format((float)$value, 2); }
}
if (!function_exists('product_image_url')) {
  function product_image_url(?string $path): ?string {
    if (!$path) return null;
    $path = str_replace(['public/', 'images/'], '', $path);
    return BASE_URL . 'images/' . ltrim($path, '/');
  }
}

/* -------------------- Currency context -------------------- */
[$currencies, $baseCode] = get_currencies($pdo);
$display = isset($_GET['cur']) ? strtoupper((string)$_GET['cur']) : $baseCode;
$curMap  = [];
foreach ($currencies as $c) $curMap[$c['code']] = $c;
if (empty($curMap[$display])) $display = $baseCode;
$symbol = $curMap[$display]['symbol'] ?? ([ 'USD'=>'$', 'EUR'=>'€', 'GBP'=>'£', 'NGN'=>'₦' ][$display] ?? '');

/* -------------------- Fetch wishlist items -------------------- */
$userId = (int)($_SESSION['user_id'] ?? 0);

$sql = "
  SELECT
    p.id, p.name, p.slug, p.sku, p.base_price, p.base_currency_code, p.image_path,
    p.featured_image_id, p.featured_variant_id, p.created_at,
    c.name AS cat_name,
    COALESCE(
      (SELECT pv.image_path FROM product_variants pv WHERE pv.id = p.featured_variant_id AND pv.image_path IS NOT NULL),
      (SELECT pi.image_path FROM product_images pi WHERE pi.id = p.featured_image_id AND pi.image_path IS NOT NULL),
      p.image_path,
      (SELECT pi2.image_path FROM product_images pi2 WHERE pi2.product_id = p.id
       ORDER BY pi2.is_main DESC, pi2.sort_order ASC, pi2.id ASC LIMIT 1)
    ) AS featured_image,
    w.created_at AS wl_created_at
  FROM wishlist w
  JOIN products p ON p.id = w.product_id
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE w.user_id = :uid
  ORDER BY w.created_at DESC, p.created_at DESC
";
$st = $pdo->prepare($sql);
$st->execute([':uid' => $userId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* -------------------- Layout includes -------------------- */
include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/svg.php';
include __DIR__ . '/../includes/mobile-header.php';
include __DIR__ . '/../includes/header.php';

// add a lightweight page marker without duplicating <body>
echo '<script>document.addEventListener("DOMContentLoaded",function(){document.body.classList.add("wishlist-page");});</script>';
?>
<main class="main">
  <div class="container">
    <div class="row">
      <!-- Optional: your account sidebar -->
      <div class="col-12 col-lg-3 col-xl-2">
        <div class="account-sidebar">
          <h6 class="mb-3 text-uppercase">My Account</h6>
          <ul class="list-unstyled small">
            <li><a href="<?= htmlspecialchars(BASE_URL . 'account/dashboard.php') ?>">Dashboard</a></li>
            <li><a href="<?= htmlspecialchars(BASE_URL . 'account/orders.php') ?>">Orders</a></li>
            <li><a class="fw-bold" href="<?= htmlspecialchars(BASE_URL . 'account/account_wishlist.php') ?>">Wishlist</a></li>
            <li><a href="<?= htmlspecialchars(BASE_URL . 'account/profile.php') ?>">Profile</a></li>
          </ul>
          <hr>
          <div class="small text-muted">Wishlist <span class="badge bg-dark ms-1" data-wishlist-count><?= count($items) ?></span></div>
        </div>
      </div>

      <div class="col-12 col-lg-9 col-xl-10">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <div class="breadcrumb mb-0">
            <a href="<?= htmlspecialchars(BASE_URL) ?>" class="menu-link menu-link_us-s text-uppercase fw-medium">Home</a>
            <span class="breadcrumb-separator menu-link fw-medium ps-1 pe-1">/</span>
            <a href="<?= htmlspecialchars(BASE_URL . 'shop/shop.php?cur=' . urlencode($display)) ?>" class="menu-link menu-link_us-s text-uppercase fw-medium">Shop</a>
            <span class="breadcrumb-separator menu-link fw-medium ps-1 pe-1">/</span>
            <span class="menu-link menu-link_us-s text-uppercase fw-medium">Wishlist</span>
          </div>
          <div class="text-uppercase fw-medium small">
            Currency: <strong><?= htmlspecialchars($display) ?></strong>
          </div>
        </div>

        <h3 class="mb-4">My Wishlist</h3>

        <!-- Empty state -->
        <div id="wl-empty" class="<?= $items ? 'd-none' : '' ?>">
          <div class="alert alert-light border">
            Your wishlist is empty. Browse the
            <a href="<?= htmlspecialchars(BASE_URL . 'shop/shop.php?cur=' . urlencode($display)) ?>">shop</a>
            and tap the heart to add items.
          </div>
        </div>

        <!-- Grid with explicit Remove button -->
        <?php if ($items): ?>
          <div class="products-grid row row-cols-2 row-cols-md-3">
            <?php foreach ($items as $p): ?>
              <?php
                $imgRel = $p['featured_image'] ?: $p['image_path'];
                $img    = $imgRel ? product_image_url($imgRel) : null;
                $price  = convert_price((float)$p['base_price'], (string)$p['base_currency_code'], $display, $curMap);
              ?>
              <div class="product-card-wrapper">
                <div class="product-card mb-3 mb-md-4 mb-xxl-5">
                  <div class="pc__img-wrapper">
                    <a href="<?= htmlspecialchars(BASE_URL . 'shop/product-details.php?slug=' . rawurlencode((string)$p['slug']) . '&cur=' . rawurlencode($display)) ?>">
                      <?php if ($img): ?>
                        <img loading="lazy" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['name']) ?>" width="330" height="400" class="pc__img">
                      <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                          <span class="text-muted small">No Image</span>
                        </div>
                      <?php endif; ?>
                    </a>
                    <button
                      class="pc__atc btn anim_appear-bottom position-absolute border-0 text-uppercase fw-medium js-open-aside"
                      data-aside="cartDrawer"
                      title="Add To Cart"
                      data-product="<?= htmlspecialchars((string)$p['slug']) ?>"
                      type="button"
                    >Add To Cart</button>
                  </div>

                  <div class="pc__info position-relative px-2">
                    <p class="pc__category"><?= htmlspecialchars($p['cat_name'] ?? '') ?></p>
                    <h6 class="pc__title">
                      <a href="<?= htmlspecialchars(BASE_URL . 'shop/product-details.php?slug=' . rawurlencode((string)$p['slug']) . '&cur=' . rawurlencode($display)) ?>">
                        <?= htmlspecialchars($p['name']) ?>
                      </a>
                    </h6>
                    <div class="product-card__price d-flex align-items-center justify-content-between gap-2">
                      <span class="money price"><?= htmlspecialchars($symbol) . price_display($price) ?></span>

                      <button
                        class="btn btn-sm btn-outline-dark js-remove-wishlist"
                        data-product="<?= htmlspecialchars((string)$p['slug']) ?>"
                        type="button"
                        title="Remove from Wishlist"
                      >
                        Remove
                      </button>
                    </div>

                    <!-- Heart in top-right (pre-marked as in wishlist) -->
                    <button
                      class="pc__btn-wl position-absolute top-0 end-0 bg-transparent border-0 js-remove-wishlist active"
                      title="Remove From Wishlist"
                      aria-pressed="true"
                      data-product="<?= htmlspecialchars((string)$p['slug']) ?>"
                      data-in-wishlist="1"
                      type="button"
                    >
                      <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <use href="#icon_heart_fill"></use>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div><!-- /.col -->
    </div><!-- /.row -->
  </div><!-- /.container -->
</main>

<div class="mb-5 pb-xl-5"></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/mobile-footer.php'; ?>
<?php include __DIR__ . '/../includes/aside-form.php'; ?>
<?php include __DIR__ . '/../includes/cart-aside.php'; ?>
<?php include __DIR__ . '/../includes/sitemap-nav.php'; ?>
<?php include __DIR__ . '/../includes/scroll.php'; ?>

<!-- Wishlist include (API + JS) -->
<?php include __DIR__ . '/../includes/wishlist.php'; ?>

<?php include __DIR__ . '/../includes/script-footer.php'; ?>
