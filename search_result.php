<?php
// search_result.php (ROOT) — aligned with site shell & components
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

/* -------------------- Currency context -------------------- */
if (!function_exists('get_currencies')) {
  function get_currencies(PDO $pdo): array {
    try {
      $rows = $pdo->query("SELECT code, symbol, is_base, rate_to_base FROM currencies")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
      $rows = $pdo->query("SELECT code, is_base FROM currencies")->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rows as &$r) { $r['symbol'] = null; $r['rate_to_base'] = 1; }
    }
    $base = null;
    foreach ($rows as $r) {
      if ((int)($r['is_base'] ?? 0) === 1) { $base = $r['code']; break; }
    }
    if (!$base && !empty($rows)) { $base = $rows[0]['code']; }
    return [$rows, $base ?? 'NGN'];
  }
}

[$currencies, $baseCode] = get_currencies($pdo);
$baseCode = strtoupper((string)$baseCode);

/* --- Normalize currencies & enforce sane rates --- */
foreach ($currencies as &$c) {
  $c['code'] = strtoupper((string)($c['code'] ?? ''));
  $rate = (float)($c['rate_to_base'] ?? 1);
  if ($c['code'] === $baseCode || (int)($c['is_base'] ?? 0) === 1) { $rate = 1.0; }
  if ($rate <= 0) { $rate = 1.0; }
  $c['rate_to_base'] = $rate;
}
unset($c);

/* Build lookup AFTER normalization */
$curMap = [];
foreach ($currencies as $c) { $curMap[$c['code']] = $c; }

/* Selected display currency */
$display = isset($_GET['cur']) ? strtoupper((string)$_GET['cur']) : $baseCode;
if (!isset($curMap[$display])) $display = $baseCode;

/* -------------------- Inputs -------------------- */
$e    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = fn(string $path = '') => htmlspecialchars(base_url($path), ENT_QUOTES, 'UTF-8');

$qRaw         = trim((string)($_GET['search-keyword'] ?? ''));
$categorySlug = trim((string)($_GET['category'] ?? ''));
$occasion     = trim((string)($_GET['occasion'] ?? ''));
$style        = trim((string)($_GET['style'] ?? ''));
$length       = trim((string)($_GET['length'] ?? ''));
$minPrice     = isset($_GET['min']) ? (float)$_GET['min'] : null;
$maxPrice     = isset($_GET['max']) ? (float)$_GET['max'] : null;

$sort = $_GET['sort'] ?? ''; // '', 'newest', 'price_asc', 'price_desc'
$allowedSort = ['newest','price_asc','price_desc'];
if (!in_array($sort, $allowedSort, true)) { $sort = ''; }

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(12, min(60, (int)($_GET['per_page'] ?? 20)));
$offset  = ($page - 1) * $perPage;

/* -------------------- WHERE builder -------------------- */
$where  = [];
$params = [];

/* Keyword search across name, sku, description, category name */
if ($qRaw !== '') {
  $tokens = preg_split('/\s+/', $qRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  foreach ($tokens as $tok) {
    $like = '%' . $tok . '%';
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    array_push($params, $like, $like, $like, $like);
  }
}

/* Category by slug */
if ($categorySlug !== '') {
  $where[]  = "c.slug = ?";
  $params[] = $categorySlug;
}

/* Price range against base_price (in product's base_currency_code) */
if ($minPrice !== null) {
  $where[]  = "p.base_price >= ?";
  $params[] = $minPrice;
}
if ($maxPrice !== null && $maxPrice >= 0.01) {
  $where[]  = "p.base_price <= ?";
  $params[] = $maxPrice;
}

/* Attribute filters: product_attributes → attributes → attribute_types */
$attrFilters = [
  'occasion' => $occasion,
  'style'    => $style,
  'length'   => $length,
];
foreach ($attrFilters as $code => $val) {
  if ($val === '') continue;
  $where[] =
    "EXISTS (
       SELECT 1
       FROM product_attributes pa
       JOIN attributes a       ON a.id = pa.attribute_id
       JOIN attribute_types at ON at.id = a.type_id
       WHERE pa.product_id = p.id
         AND at.code = ?
         AND a.value = ?
     )";
  array_push($params, $code, $val);
}

/* Only active products */
$where[] = "p.is_active = 1";

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* -------------------- Sorting -------------------- */
$orderBy = "p.created_at DESC"; // default relevance/newest
if ($sort === 'newest')     $orderBy = "p.created_at DESC";
if ($sort === 'price_asc')  $orderBy = "p.base_price ASC, p.created_at DESC";
if ($sort === 'price_desc') $orderBy = "p.base_price DESC, p.created_at DESC";

/* -------------------- Count -------------------- */
$countSql = "
  SELECT COUNT(DISTINCT p.id)
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  $whereSql
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

/* -------------------- Fetch list with cover image & featured price -------------------- */
$listSql = "
  SELECT
    p.id, p.slug, p.name, p.sku,
    p.base_price, p.base_currency_code,
    p.created_at,
    c.name AS cat_name, c.slug AS cat_slug,
    /* Prefer price from explicitly featured variant if set */
    (SELECT pv.price
       FROM product_variants pv
       WHERE pv.id = p.featured_variant_id
         AND pv.price IS NOT NULL
       LIMIT 1) AS featured_price,
    COALESCE(
      /* 1) explicitly featured variant image */
      (SELECT pv.image_path
       FROM product_variants pv
       WHERE pv.id = p.featured_variant_id
         AND pv.image_path IS NOT NULL
       LIMIT 1),
      /* 2) any featured variant */
      (SELECT pv2.image_path
       FROM product_variants pv2
       WHERE pv2.product_id = p.id
         AND pv2.featured = 1
         AND pv2.image_path IS NOT NULL
       ORDER BY pv2.id DESC
       LIMIT 1),
      /* 3) main gallery image */
      (SELECT pi.image_path
       FROM product_images pi
       WHERE pi.product_id = p.id
         AND pi.is_main = 1
       ORDER BY pi.sort_order ASC, pi.id ASC
       LIMIT 1),
      /* 4) product fallback */
      p.image_path
    ) AS cover_image
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  $whereSql
  ORDER BY $orderBy
  LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- Helpers -------------------- */
if (!function_exists('product_image_url')) {
  function product_image_url(?string $path): ?string {
    $path = trim((string)$path);
    if ($path === '') return null;
    if (preg_match('#^https?://#i', $path)) return $path;
    $clean = ltrim($path, '/');
    return base_url($clean);
  }
}

/* Price display with robust conversion where rate_to_base(base)=1 */
function price_view(?float $price, string $code, array $curMap, string $display, string $baseCode) : string {
  if ($price === null) return '';

  $fromCode = strtoupper((string)$code);
  $toCode   = strtoupper((string)$display);

  $from = $curMap[$fromCode] ?? null;
  $to   = $curMap[$toCode] ?? null;

  $val = $price;

  if ($from && $to) {
    $fromRate = (float)($from['rate_to_base'] ?? 1);
    $toRate   = (float)($to['rate_to_base'] ?? 1);

    if ($fromCode === $baseCode) $fromRate = 1.0;
    if ($toCode   === $baseCode) $toRate   = 1.0;

    if ($fromRate <= 0) $fromRate = 1.0;
    if ($toRate   <= 0) $toRate   = 1.0;

    // amount_in_base = amount * from.rate_to_base
    // amount_in_display = amount_in_base / to.rate_to_base
    $val = $val * $fromRate / $toRate;
  }

  $symbol = $to['symbol'] ?? '';
  $fmt    = number_format($val, 2);
  return $symbol ? ($symbol . $fmt) : ($toCode . ' ' . $fmt);
}

$defaultCode = $display;

/* -------------------- Shell -------------------- */
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/svg.php';
include __DIR__ . '/includes/mobile-header.php';
include __DIR__ . '/includes/header.php';
?>

<main class="position-relative">
  <div class="mb-md-1 pb-xl-3"></div>

  <section class="shop-main container d-flex">
    <!-- Optional left sidebar -->
    <!-- <?php // include __DIR__ . '/includes/shop/sidebar.php'; ?> -->

    <div class="shop-list flex-grow-1">
      <!-- Top bar: results + sort -->
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
          <h5 class="mb-0">
            <?= $total ?> result<?= $total === 1 ? '' : 's' ?>
            <?php if ($qRaw !== ''): ?> for “<strong><?= $e($qRaw) ?></strong>”<?php endif; ?>
          </h5>
          <div class="small text-muted">
            Page <?= $page ?> of <?= $pages ?>
            <?php if ($categorySlug): ?> • Category: <strong><?= $e(ucwords(str_replace('-', ' ', $categorySlug))) ?></strong><?php endif; ?>
            <?php if ($occasion): ?> • Occasion: <strong><?= $e($occasion) ?></strong><?php endif; ?>
            <?php if ($style): ?> • Style: <strong><?= $e($style) ?></strong><?php endif; ?>
            <?php if ($length): ?> • Length: <strong><?= $e($length) ?></strong><?php endif; ?>
            <?php if ($minPrice !== null || $maxPrice !== null): ?> • Price:
              <strong><?= $e($minPrice !== null ? (string)$minPrice : '0') ?> – <?= $e($maxPrice !== null ? (string)$maxPrice : '∞') ?></strong>
            <?php endif; ?>
          </div>
        </div>

        <form method="get" class="d-flex align-items-center gap-2">
          <input type="hidden" name="search-keyword" value="<?= $e($qRaw) ?>">
          <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= $e($categorySlug) ?>"><?php endif; ?>
          <?php if ($occasion): ?><input type="hidden" name="occasion" value="<?= $e($occasion) ?>"><?php endif; ?>
          <?php if ($style): ?><input type="hidden" name="style" value="<?= $e($style) ?>"><?php endif; ?>
          <?php if ($length): ?><input type="hidden" name="length" value="<?= $e($length) ?>"><?php endif; ?>
          <?php if ($minPrice !== null): ?><input type="hidden" name="min" value="<?= $e((string)$minPrice) ?>"><?php endif; ?>
          <?php if ($maxPrice !== null): ?><input type="hidden" name="max" value="<?= $e((string)$maxPrice) ?>"><?php endif; ?>
          <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value=""           <?= $sort==='' ? 'selected' : '' ?>>Sort: Relevance</option>
            <option value="newest"     <?= $sort==='newest' ? 'selected' : '' ?>>Newest</option>
            <option value="price_asc"  <?= $sort==='price_asc' ? 'selected' : '' ?>>Price: Low → High</option>
            <option value="price_desc" <?= $sort==='price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
          </select>
        </form>
      </div>

      <!-- Active filters (pills) -->
      <?php
        $all = $_GET;
        $makeUrl = function(array $without) use ($all) {
          foreach ($without as $key) unset($all[$key]);
          $qs = http_build_query($all);
          return $qs ? ('search_result.php?' . $qs) : 'search_result.php';
        };
      ?>
      <div class="filters mb-3">
        <?php if ($categorySlug): ?>
          <span class="pill">Category: <strong><?= $e(ucwords(str_replace('-', ' ', $categorySlug))) ?></strong>
            <a class="x" href="<?= $e($makeUrl(['category'])) ?>">×</a></span>
        <?php endif; ?>
        <?php if ($occasion): ?>
          <span class="pill">Occasion: <strong><?= $e($occasion) ?></strong>
            <a class="x" href="<?= $e($makeUrl(['occasion'])) ?>">×</a></span>
        <?php endif; ?>
        <?php if ($style): ?>
          <span class="pill">Style: <strong><?= $e($style) ?></strong>
            <a class="x" href="<?= $e($makeUrl(['style'])) ?>">×</a></span>
        <?php endif; ?>
        <?php if ($length): ?>
          <span class="pill">Length: <strong><?= $e($length) ?></strong>
            <a class="x" href="<?= $e($makeUrl(['length'])) ?>">×</a></span>
        <?php endif; ?>
        <?php if ($minPrice !== null || $maxPrice !== null): ?>
          <span class="pill">Price:
            <strong><?= $e($minPrice !== null ? (string)$minPrice : '0') ?> – <?= $e($maxPrice !== null ? (string)$maxPrice : '∞') ?></strong>
            <a class="x" href="<?= $e($makeUrl(['min','max'])) ?>">×</a></span>
        <?php endif; ?>
      </div>

      <!-- Grid -->
      <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
        <?php if (!$items): ?>
          <div class="col-12">
            <div class="alert alert-light border mb-0">No products matched your search.</div>
          </div>
        <?php else: ?>
          <?php foreach ($items as $p): ?>
            <?php
              $slug = $p['slug'] ?: (string)$p['id'];
              $href = $base('shop/product-details.php') . '?slug=' . urlencode($slug);

              $imgUrl = product_image_url($p['cover_image'] ?? '') ?: $base('images/placeholder.png');

              $code = !empty($p['base_currency_code']) ? strtoupper((string)$p['base_currency_code']) : $defaultCode;
              $finalPrice = isset($p['featured_price']) && $p['featured_price'] !== null
                              ? (float)$p['featured_price']
                              : (float)$p['base_price'];
              $priceHtml = price_view($finalPrice, $code, $curMap, $display, $baseCode);
            ?>
            <div class="col">
              <div class="product-card h-100">
                <a href="<?= $href ?>" class="d-block mb-2">
                  <img src="<?= $e($imgUrl) ?>" alt="<?= $e($p['name']) ?>" class="img-fluid w-100">
                </a>
                <div class="small text-muted"><?= $e($p['cat_name'] ?? '') ?></div>
                <a href="<?= $href ?>" class="text-body fw-medium d-block mb-1"><?= $e($p['name']) ?></a>
                <div class="d-flex align-items-center gap-2">
                  <span class="fw-bold"><?= $e($priceHtml) ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
        <?php
          $qs = $_GET;
          $renderLink = function(int $p) use ($qs) { $qs['page'] = $p; return 'search_result.php?' . http_build_query($qs); };
        ?>
        <nav class="mt-4">
          <ul class="pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $e($renderLink(max(1, $page-1))) ?>">Prev</a>
            </li>
            <?php
              $start = max(1, $page - 2);
              $end   = min($pages, $page + 2);
              if ($start > 1) {
                echo '<li class="page-item"><a class="page-link" href="' . $e($renderLink(1)) . '">1</a></li>';
                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
              }
              for ($i=$start; $i<=$end; $i++) {
                $active = $i === $page ? 'active' : '';
                echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $e($renderLink($i)) . '">' . $i . '</a></li>';
              }
              if ($end < $pages) {
                if ($end < $pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                echo '<li class="page-item"><a class="page-link" href="' . $e($renderLink($pages)) . '">' . $pages . '</a></li>';
              }
            ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $e($renderLink(min($pages, $page+1))) ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </section>

  <div class="mb-5 pb-xl-5"></div>
</main>

<?php
// Footer stack to match other frontend pages
include __DIR__ . '/includes/footer.php';
include __DIR__ . '/includes/mobile-footer.php';
include __DIR__ . '/includes/aside-form.php';
include __DIR__ . '/includes/cart-aside.php';
include __DIR__ . '/includes/sitemap-nav.php';
include __DIR__ . '/includes/scroll.php';
