<?php
// trend-product.php — wired to your SQL schema (u848848112_jhemamain)

require_once __DIR__ . '/../includes/config.php';

// -------------------------
// Input & sensible defaults
// -------------------------
$display = isset($_GET['cur']) ? strtoupper(trim($_GET['cur'])) : 'NGN';
$activeSlug = isset($_GET['slug']) ? trim($_GET['slug']) : null;

// -------------------------
// Load currencies map
// -------------------------
$curMap = [];
try {
    $stmt = $pdo->query("SELECT code, symbol, is_base, rate_to_base FROM currencies");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $curMap[$r['code']] = [
            'symbol'      => $r['symbol'],
            'is_base'     => (int)$r['is_base'],
            'rate_to_base'=> (float)$r['rate_to_base'],
        ];
        if ((int)$r['is_base'] === 1) {
            $baseCode = $r['code'];
        }
    }
} catch (Throwable $e) {
    $curMap = ['NGN' => ['symbol' => '₦', 'is_base' => 1, 'rate_to_base' => 1.0]];
    $baseCode = 'NGN';
}

// Ensure $display is a known currency; otherwise fall back to base
if (!isset($curMap[$display])) {
    $display = isset($baseCode) ? $baseCode : 'NGN';
}

// -------------------------
// Helpers (fallback-safe)
// -------------------------
if (!function_exists('price_display')) {
    function price_display($value) { return number_format((float)$value, 2); }
}

/**
 * Convert an amount from $from -> $to using currencies.rate_to_base
 * rule: toBase = amount * rate_to_base(from); toTarget = toBase / rate_to_base(to)
 */
if (!function_exists('convert_price')) {
    function convert_price(float $amount, string $from, string $to, array $map): float {
        $from = strtoupper($from); $to = strtoupper($to);
        if (!isset($map[$from]) || !isset($map[$to])) return $amount;
        $rateFrom = (float)$map[$from]['rate_to_base'];
        $rateTo   = (float)$map[$to]['rate_to_base'];
        if ($rateFrom <= 0 || $rateTo <= 0) return $amount;
        $toBase = $amount * $rateFrom;
        return $toBase / $rateTo;
    }
}

// Symbol getter
$getSymbol = function(string $code, array $map): string {
    if (isset($map[$code]['symbol'])) return (string)$map[$code]['symbol'];
    return match (strtoupper($code)) {
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'NGN' => '₦', default => ''
    };
};

// -------------------------
// Fetch category menu
// (include only categories that exist; optionally with counts)
// -------------------------
$menu = [];
try {
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id, c.name, c.slug
        ORDER BY c.name ASC
    ");
    $menu = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $menu = [];
}

// -------------------------
// Fetch products (optionally filter by slug)
// -------------------------
$products = [];
try {
    if ($activeSlug !== null && $activeSlug !== '') {
        $stmt = $pdo->prepare("
            SELECT
                p.id, p.category_id, p.name, p.slug, p.sku, p.description,
                p.base_currency_code, p.base_price, p.image_path, p.created_at,
                c.name AS cat_name
            FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE c.slug = :slug
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT 24
        ");
        $stmt->execute([':slug' => $activeSlug]);
    } else {
        // No slug: show latest products across all categories
        $stmt = $pdo->query("
            SELECT
                p.id, p.category_id, p.name, p.slug, p.sku, p.description,
                p.base_currency_code, p.base_price, p.image_path, p.created_at,
                c.name AS cat_name
            FROM products p
            JOIN categories c ON c.id = p.category_id
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT 24
        ");
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $products = [];
}

// -------------------------
// RENDER (your original markup kept intact)
// -------------------------
?>
<section class="products-grid container">
  <h2 class="section-title text-uppercase text-center mb-1 mb-md-3 pb-xl-2 mb-xl-4">
    Our Trendy <strong>Products</strong>
  </h2>

  <!-- Category "tabs" rendered as links (keep your structure: they navigate to index.php?slug=...) -->
  <ul class="nav nav-tabs mb-3 text-uppercase justify-content-center" id="collections-tab" role="tablist">
    <?php if (!empty($menu)): ?>
      <?php foreach ($menu as $m):
        $mSlug = isset($m['slug']) ? (string)$m['slug'] : '';
        $mName = isset($m['name']) ? (string)$m['name'] : 'Category';
        $isActive = ($activeSlug !== null && $activeSlug === $mSlug);
      ?>
        <li class="nav-item" role="presentation">
          <a
            class="nav-link nav-link_underscore<?= $isActive ? ' active' : '' ?><?= (isset($m['product_count']) && (int)$m['product_count'] === 0) ? ' disabled' : '' ?>"
            href="index.php?slug=<?= urlencode($mSlug) ?>&cur=<?= urlencode($display) ?>"
            role="tab"
            aria-selected="<?= $isActive ? 'true' : 'false' ?>"
            <?php if (isset($m['product_count']) && (int)$m['product_count'] === 0): ?> tabindex="-1" aria-disabled="true"<?php endif; ?>
          >
            <?= htmlspecialchars($mName) ?>
            <?php if (isset($m['product_count'])): ?>
              <small class="ms-1">(<?= (int)$m['product_count'] ?>)</small>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    <?php else: ?>
      <li class="nav-item"><span class="nav-link disabled">No categories</span></li>
    <?php endif; ?>
  </ul>

  <div class="tab-content pt-2" id="collections-tab-content">
    <div class="tab-pane fade show active" id="trendy-products" role="tabpanel" aria-labelledby="collections-tab-1-trigger">
      <div class="row">
        <?php if (!empty($products)): ?>
          <?php foreach ($products as $p):
            // expected keys in $p: slug, name, base_price, base_currency_code, image_path, cat_name
            $slug  = isset($p['slug']) ? (string)$p['slug'] : '';
            $name  = isset($p['name']) ? (string)$p['name'] : 'Unnamed Product';
            $img   = isset($p['image_path']) ? (string)$p['image_path'] : '';
            $cat   = isset($p['cat_name']) ? (string)$p['cat_name'] : '';
            $price = isset($p['base_price']) ? (float)$p['base_price'] : 0.0;
            $from  = isset($p['base_currency_code']) ? (string)$p['base_currency_code'] : $display;
            $converted = convert_price($price, $from, $display, $curMap);
            $symbol    = $getSymbol($display, $curMap);
          ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card mb-3 mb-md-4 mb-xxl-5">
              <div class="pc__img-wrapper">
                <a href="product.php?slug=<?= urlencode($slug) ?>&cur=<?= urlencode($display) ?>">
                  <?php if ($img !== ''): ?>
                    <img loading="lazy"
                         src="<?= htmlspecialchars($img) ?>"
                         alt="<?= htmlspecialchars($name) ?>"
                         width="330" height="400"
                         class="pc__img">
                  <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                      <span class="text-muted small">No Image</span>
                    </div>
                  <?php endif; ?>
                </a>
                <button
                  class="pc__atc btn anim_appear-bottom btn position-absolute border-0 text-uppercase fw-medium js-add-cart js-open-aside"
                  data-aside="cartDrawer"
                  title="Add To Cart"
                  data-product="<?= htmlspecialchars($slug) ?>"
                >Add To Cart</button>
              </div>

              <div class="pc__info position-relative px-2">
                <?php if ($cat !== ''): ?>
                  <p class="pc__category"><?= htmlspecialchars($cat) ?></p>
                <?php endif; ?>

                <h6 class="pc__title">
                  <a href="product.php?slug=<?= urlencode($slug) ?>&cur=<?= urlencode($display) ?>">
                    <?= htmlspecialchars($name) ?>
                  </a>
                </h6>

                <div class="product-card__price d-flex">
                  <span class="money price"><?= $symbol . price_display($converted) ?></span>
                </div>

                <button
                  class="pc__btn-wl position-absolute top-0 end-0 bg-transparent border-0 js-add-wishlist"
                  title="Add To Wishlist"
                  data-product="<?= htmlspecialchars($slug) ?>"
                >
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
            <div class="text-center text-muted py-4">
              No products to display yet.
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="text-center mt-2">
        <a
          class="btn-link btn-link_lg default-underline text-uppercase fw-medium"
          href="<?= defined('BASE_URL') ? BASE_URL : '' ?>shop/shop.php?cur=<?= urlencode($display) ?>"
        >Discover More</a>
      </div>
    </div>
  </div>
</section>
