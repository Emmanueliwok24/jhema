<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '') { header('Location: index.php'); exit; }

/* Currencies */
list($currencies, $baseCode) = get_currencies($pdo);
$curMap = []; foreach ($currencies as $c) $curMap[$c['code']] = $c;

$display = isset($_GET['cur']) ? strtoupper($_GET['cur']) : $baseCode;
if (!isset($curMap[$display])) $display = $baseCode;

/* Product */
$stmt = $pdo->prepare("
  SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.slug = ?
");
$stmt->execute([$slug]);
$product = $stmt->fetch();
if (!$product) { header('Location: index.php'); exit; }
$product_id = (int)$product['id'];

/* Product attributes (JHEMA chips) */
$attrStmt = $pdo->prepare("
  SELECT at.code, a.value
  FROM product_attributes pa
  JOIN attributes a ON a.id = pa.attribute_id
  JOIN attribute_types at ON at.id = a.type_id
  WHERE pa.product_id = ?
  ORDER BY at.code, a.value
");
$attrStmt->execute([$product_id]);
$attrRows = $attrStmt->fetchAll(PDO::FETCH_ASSOC);
$attrByType = ['occasion'=>[], 'length'=>[], 'style'=>[]];
foreach ($attrRows as $r) { $attrByType[$r['code']][] = $r['value']; }

/* Variants (with image_path) */
$stmt = $pdo->prepare("
  SELECT size, color, price_override, stock, image_path
  FROM product_variants
  WHERE product_id = ?
");
$stmt->execute([$product_id]);
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Unique sizes/colors */
$sizes = []; $colors = [];
foreach ($variants as $v) {
  if (!empty($v['size']))  $sizes[$v['size']]  = true;
  if (!empty($v['color'])) $colors[$v['color']] = true;
}
$sizes  = array_values(array_keys($sizes));
$colors = array_values(array_keys($colors));

/* Price/stock/image map: keys "size|color", "size|", "|color" */
$map = [];
foreach ($variants as $v) {
  $k = ($v['size'] ?? '') . '|' . ($v['color'] ?? '');
  $map[$k] = [
    'price' => (float)$v['price_override'],
    'stock' => isset($v['stock']) ? (int)$v['stock'] : null,
    'image' => $v['image_path'] ?: null
  ];
}

/* Main image & swatch thumbnails */
$mainImage = $product['image_path'] ?: null;

/* Build representative image per size/color for swatches */
$sizeThumbs = [];
foreach ($sizes as $s) {
  $img = $map["{$s}|"]['image'] ?? null;
  if (!$img) {
    foreach ($colors as $c) {
      if (!empty($map["{$s}|{$c}"]['image'])) { $img = $map["{$s}|{$c}"]['image']; break; }
    }
  }
  $sizeThumbs[$s] = $img ?: $mainImage;
}

$colorThumbs = [];
foreach ($colors as $c) {
  $img = $map["|{$c}"]['image'] ?? null;
  if (!$img) {
    foreach ($sizes as $s) {
      if (!empty($map["{$s}|{$c}"]['image'])) { $img = $map["{$s}|{$c}"]['image']; break; }
    }
  }
  $colorThumbs[$c] = $img ?: $mainImage;
}

/* Thumb rail: main + any unique variant images */
$thumbs = [];
if ($mainImage) $thumbs[$mainImage] = true;
foreach ($map as $info) {
  if (!empty($info['image'])) $thumbs[$info['image']] = true;
}
$thumbList = array_keys($thumbs);

/* JS payloads */
$jsMap        = json_encode($map, JSON_UNESCAPED_UNICODE);
$jsBasePrice  = (float)$product['base_price'];
$jsDisplay    = $display;
$jsBaseCode   = $product['base_currency_code'];
$jsMainImage  = $mainImage ?: '';

$rates = []; $symbols = [];
foreach ($currencies as $c) { $rates[$c['code']] = (float)$c['rate_to_base']; $symbols[$c['code']] = $c['symbol']; }
$jsRates   = json_encode($rates, JSON_UNESCAPED_UNICODE);
$jsSymbols = json_encode($symbols, JSON_UNESCAPED_UNICODE);
$jsThumbs  = json_encode($thumbList, JSON_UNESCAPED_UNICODE);

/* Optional default size chart (CM) */
$sizeChart = [
  ['label'=>'XS','bust'=>80,'waist'=>62,'hips'=>86],
  ['label'=>'S', 'bust'=>84,'waist'=>66,'hips'=>90],
  ['label'=>'M', 'bust'=>88,'waist'=>70,'hips'=>94],
  ['label'=>'L', 'bust'=>94,'waist'=>76,'hips'=>100],
  ['label'=>'XL','bust'=>100,'waist'=>82,'hips'=>106],
  ['label'=>'XXL','bust'=>106,'waist'=>88,'hips'=>112],
];
$jsSizeChart = json_encode($sizeChart, JSON_UNESCAPED_UNICODE);

/* Has dimensions? */
$hasSizes  = !empty($sizes);
$hasColors = !empty($colors);
?>



<?php include("includes/head.php"); ?>
<?php include("includes/svg.php"); ?>
<?php include("includes/mobile-header.php"); ?>
<?php include("includes/header.php"); ?>

  <style>
    :root{ --lux-bg:#f6f3ee; --lux-card:#fff; --lux-ink:#0f0f0f; --lux-sub:#5b5b5b; --lux-line:#e7e1d8; }
    body{color:var(--lux-ink);-webkit-font-smoothing:antialiased}
    .lux-card{background:var(--lux-card);border:1px solid var(--lux-line);border-radius:18px;box-shadow:0 6px 24px rgba(17,17,17,.04)}
    .lux-hr{border-top:1px solid var(--lux-line);opacity:1}
    .lux-brand{letter-spacing:.06em;text-transform:uppercase;font-weight:700}
    .btn-ghost{background:#fff;color:#111;border:1px solid var(--lux-line);border-radius:9999px}
    .btn-lux{background:#111;color:#fff;border:1px solid #111;border-radius:9999px}
    .btn-lux:hover{background:#000}
    .muted{color:var(--lux-sub)}
    .price{font-size:2rem;font-weight:800}
    .imgbox{background:#faf7f2;border:1px solid var(--lux-line);border-radius:16px;display:flex;align-items:center;justify-content:center;aspect-ratio:1/1}
    .imgbox img{max-width:100%;max-height:100%;object-fit:contain}
    .thumbrail img{width:72px;height:72px;object-fit:cover;border-radius:12px;border:1px solid var(--lux-line);cursor:pointer;background:#fff}
    .thumbrail .active{outline:2px solid #111}
    .swatch-grid{display:flex;flex-wrap:wrap;gap:.75rem}
    .swatch{
      display:flex; align-items:center; gap:.5rem; padding:.4rem .55rem;
      border:1px solid var(--lux-line); border-radius:9999px; background:#fff;
      cursor:pointer; transition: box-shadow .12s ease, border-color .12s ease; user-select:none;
    }
    .swatch:hover{box-shadow:0 6px 18px rgba(17,17,17,.06)}
    .swatch.active{border-color:#111; box-shadow:0 0 0 2px rgba(17,17,17,.08) inset}
    .swatch.disabled{opacity:.45;filter:grayscale(30%);pointer-events:none}
    .swatch .thumb{width:34px;height:34px;border-radius:8px;border:1px solid var(--lux-line);background:#fff;object-fit:cover;flex-shrink:0}
    .swatch .label{font-weight:600;letter-spacing:.02em}
    .chips{display:flex;gap:8px;flex-wrap:wrap}
    .chip{background:#f1f1f1;border-radius:9999px;padding:4px 10px;font-size:.8rem}
    .pill{display:inline-block;padding:.35rem .75rem;border:1px solid var(--lux-line);border-radius:9999px}
    .chart-table thead th{background:#faf7f2;border-bottom:1px solid var(--lux-line);text-transform:uppercase;font-size:.78rem;letter-spacing:.06em;color:#3a3a3a}
    .chart-table td, .chart-table th{border-color:var(--lux-line)}
    .unit-toggle .btn{border-radius:9999px}
  </style>


  <main class="position-relative">
      <?php include("scroll_categories.php"); ?>

    <div class="mb-md-1 pb-md-3"></div>
    <section class="product-single container">
      <div class="row">
        <div class="col-lg-7">
          <div class="product-single__media" data-media-type="horizontal-thumbnail">
            <div class="product-single__image">
              <div class="swiper-container">
                <div class="swiper-wrapper">
                  <div class="swiper-slide product-single__image-item">
                     <?php if ($mainImage): ?>
                      <img id="mainImage" loading="lazy" class="h-auto" src="<?= htmlspecialchars($mainImage) ?>" width="788" height="788" alt="<?= htmlspecialchars($product['name']) ?>">
                      <?php else: ?>
                         <div class="text-center text-secondary">No Image</div>
            <?php endif; ?>

                  </div>
                 <!-- ADD more swipe -->

                </div>

              </div>
            </div>
            <div class="product-single__thumbnail">
              <div class="swiper-container">
                <?php if ($thumbList): ?>


                  <!-- Add more swipes -->
              <div class="thumbrail border" id="thumbRail">
              <?php foreach ($thumbList as $i => $src): ?>
                <img loading="lazy" class="h-auto" <?= $i===0?'class="active"':'' ?> src="<?= htmlspecialchars($src) ?>" data-src="<?= htmlspecialchars($src) ?>" height="104" alt="thumb">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="d-flex justify-content-between mb-4 pb-md-2">
            <div class="breadcrumb mb-0 d-none d-md-block flex-grow-1">
              <a href="<?= BASE_URL ?>index.php" class="menu-link menu-link_us-s text-uppercase fw-medium">Home</a>
              <span class="breadcrumb-separator menu-link fw-medium ps-1 pe-1">/</span>
              <a href="#" class="menu-link menu-link_us-s text-uppercase fw-medium">The Shop</a>
            </div><!-- /.breadcrumb -->


          </div>
          <div class="muted mb-1"><?= htmlspecialchars($product['cat_name'] ?? '') ?></div>

          <h1 class="product-single__name"><?= htmlspecialchars($product['name'] ?? '') ?></h1>
          <div class="muted">SKU: <?= htmlspecialchars($product['sku']) ?></div>





          <div class="row g-3 mt-3">
            <div class="col-md-6">
              <label class="form-label">Display Currency</label>
              <select id="curSelect" class="form-select">
                <?php foreach ($currencies as $c): ?>
                  <option value="<?= htmlspecialchars($c['code']) ?>" <?= $display===$c['code']?'selected':'' ?>>
                    <?= htmlspecialchars($c['code']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div>
                <div id="mainPrice" class="price"></div>
                <div class="muted small">Auto-updates with selection & currency.</div>
              </div>
            </div>
          </div>

          <div class="product-single__short-desc">


                              <!-- JHEMA chips -->
          <?php if ($attrByType['occasion']): ?>
            <div class="mt-2"><strong>Occasion:</strong> <span class="chips"><?php foreach ($attrByType['occasion'] as $v) echo '<span class="chip">'.htmlspecialchars($v).'</span>'; ?></span></div>
          <?php endif; ?>
          <?php if ($attrByType['length']): ?>
            <div class="mt-1"><strong>Length:</strong> <span class="chips"><?php foreach ($attrByType['length'] as $v) echo '<span class="chip">'.htmlspecialchars($v).'</span>'; ?></span></div>
          <?php endif; ?>
          <?php if ($attrByType['style']): ?>
            <div class="mt-1"><strong>Style:</strong> <span class="chips"><?php foreach ($attrByType['style'] as $v) echo '<span class="chip">'.htmlspecialchars($v).'</span>'; ?></span></div>
          <?php endif; ?>
          </div>


          <!-- CART FORM AND COLOR/SIZE SELECTION -->
          <form>
            <!-- Product Details -->

          <hr class="lux-hr my-4">


      <div class="">








          <!-- Available Sizes -->
                    <?php $hasSizesBool = !empty($sizes); $hasColorsBool = !empty($colors); ?>

          <?php if ($hasSizesBool): ?>
            <div class="mb-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label mb-0">Available Sizes</label>

                <button type="button" class="btn btn-sizeguide-link text-decoration-underline" data-bs-toggle="modal" data-bs-target="#sizeGuide">Size Guide</button>
              </div>
              <div id="sizeGrid" class="swatch-grid">
                <?php foreach ($sizes as $s): $img = $sizeThumbs[$s] ?? $mainImage; ?>
                  <div class="swatch" data-size="<?= htmlspecialchars($s) ?>" data-image="<?= htmlspecialchars($img ?? '') ?>" role="button" aria-pressed="false">
                    <?php if ($img): ?><img class="thumb" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($s) ?>"><?php endif; ?>
                    <span class="label"><?= htmlspecialchars($s) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Available Colors -->
          <div class="colors-section my-3">
            <?php if ($hasColorsBool): ?>
              <div class="mb-3">
                <label class="form-label mb-2 section-title">Available Colors</label>
              <div id="colorGrid" class="swatch-grid">
                <?php foreach ($colors as $c): $img = $colorThumbs[$c] ?? $mainImage; ?>
                  <div class="swatch" data-color="<?= htmlspecialchars($c) ?>" data-image="<?= htmlspecialchars($img ?? '') ?>" role="button" aria-pressed="false">
                    <?php if ($img): ?><img class="thumb" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($c) ?>" ><?php endif; ?>
                    <span class="label"><?= htmlspecialchars($c) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          </div>
<!-- ADD to cart section -->
<div class="product-single__addtocart">
      <div class="qty-control position-relative">
        <input type="number" name="quantity" value="1" min="1" class="qty-control__number text-center">
        <div class="qty-control__reduce">-</div>
        <div class="qty-control__increase">+</div>
      </div><!-- .qty-control -->
      <button type="submit" class="btn btn-primary btn-addtocart js-open-aside" data-aside="cartDrawer">Add to Cart</button></div>

            </div>
            <div class="product-single__addtolinks">
            <a href="#" class="menu-link menu-link_us-s add-to-wishlist"><svg width="16" height="16" viewbox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><use href="#icon_heart"></use></svg><span>Add to Wishlist</span></a>
            <share-button class="share-button">
              <button class="menu-link menu-link_us-s to-share border-0 bg-transparent d-flex align-items-center">
                <svg width="16" height="19" viewbox="0 0 16 19" fill="none" xmlns="http://www.w3.org/2000/svg"><use href="#icon_sharing"></use></svg>
                <span>Share</span>
              </button>
              <details id="Details-share-template__main" class="m-1 xl:m-1.5" hidden="">
                <summary class="btn-solid m-1 xl:m-1.5 pt-3.5 pb-3 px-5">+</summary>
                <div id="Article-share-template__main" class="share-button__fallback flex items-center absolute top-full left-0 w-full px-2 py-4 bg-container shadow-theme border-t z-10">
                  <div class="field grow mr-4">
                    <label class="field__label sr-only" for="url">Link</label>
                    <input type="text" class="field__input w-full" id="url" value="https://uomo-crystal.myshopify.com/blogs/news/go-to-wellness-tips-for-mental-health" placeholder="Link" onclick="this.select();" readonly="">
                  </div>
                  <button class="share-button__copy no-js-hidden">
                    <svg class="icon icon-clipboard inline-block mr-1" width="11" height="13" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" viewbox="0 0 11 13">
                      <path fill-rule="evenodd" clip-rule="evenodd" d="M2 1a1 1 0 011-1h7a1 1 0 011 1v9a1 1 0 01-1 1V1H2zM1 2a1 1 0 00-1 1v9a1 1 0 001 1h7a1 1 0 001-1V3a1 1 0 00-1-1H1zm0 10V3h7v9H1z" fill="currentColor"></path>
                    </svg>
                    <span class="sr-only">Copy link</span>
                  </button>
                </div>
              </details>
            </share-button>
            <script src="js/details-disclosure.js" defer="defer"></script>
            <script src="js/share.js" defer="defer"></script>
          </div>
          <div class="product-single__meta-info">
            <div class="meta-item">
              <label>SKU:</label>
              <span>N/A</span>
            </div>
            <div class="meta-item">
              <label>Categories:</label>
              <span>Casual & Urban Wear, Jackets, Men</span>
            </div>
            <div class="meta-item">
              <label>Tags:</label>
              <span>biker, black, bomber, leather</span>
            </div>
          </div>

        </div>
      </div>


        </div>
      </div>









              <!-- cart and checkout -->


          </form>



      <div class="product-single__details-tab">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
          <li class="nav-item" role="presentation">
            <a class="nav-link nav-link_underscore active" id="tab-description-tab" data-bs-toggle="tab" href="#tab-description" role="tab" aria-controls="tab-description" aria-selected="true">Description</a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link nav-link_underscore" id="tab-additional-info-tab" data-bs-toggle="tab" href="#tab-additional-info" role="tab" aria-controls="tab-additional-info" aria-selected="false">Additional Information</a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link nav-link_underscore" id="tab-reviews-tab" data-bs-toggle="tab" href="#tab-reviews" role="tab" aria-controls="tab-reviews" aria-selected="false">Reviews (2)</a>
          </li>
        </ul>





        <div class="tab-content">
          <div class="tab-pane fade show active" id="tab-description" role="tabpanel" aria-labelledby="tab-description-tab">
            <div class="product-single__description">
              <h3 class="block-title mb-4">Sed do eiusmod tempor incididunt ut labore</h3>
              <p class="content">Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.</p>
              <div class="row">
                <div class="col-lg-6">
                  <h3 class="block-title">Why choose product?</h3>
                  <ul class="list text-list">
                    <li>Creat by cotton fibric with soft and smooth</li>
                    <li>Simple, Configurable (e.g. size, color, etc.), bundled</li>
                    <li>Downloadable/Digital Products, Virtual Products</li>
                  </ul>
                </div>
                <div class="col-lg-6">
                  <h3 class="block-title">Sample Number List</h3>
                  <ol class="list text-list">
                    <li>Create Store-specific attrittbutes on the fly</li>
                    <li>Simple, Configurable (e.g. size, color, etc.), bundled</li>
                    <li>Downloadable/Digital Products, Virtual Products</li>
                  </ol>
                </div>
              </div>
              <h3 class="block-title mb-0">Lining</h3>
              <p class="content">100% Polyester, Main: 100% Polyester.</p>
            </div>
          </div>
          <div class="tab-pane fade" id="tab-additional-info" role="tabpanel" aria-labelledby="tab-additional-info-tab">
            <div class="product-single__addtional-info">
              <div class="item">
                <label class="h6">Weight</label>
                <span>1.25 kg</span>
              </div>
              <div class="item">
                <label class="h6">Dimensions</label>
                <span>90 x 60 x 90 cm</span>
              </div>
              <div class="item">
                <label class="h6">Size</label>
                <span>XS, S, M, L, XL</span>
              </div>
              <div class="item">
                <label class="h6">Color</label>
                <span>Black, Orange, White</span>
              </div>
              <div class="item">
                <label class="h6">Storage</label>
                <span>Relaxed fit shirt-style dress with a rugged</span>
              </div>
            </div>
          </div>
          <div class="tab-pane fade" id="tab-reviews" role="tabpanel" aria-labelledby="tab-reviews-tab">
            <h2 class="product-single__reviews-title">Reviews</h2>
            <div class="product-single__reviews-list">
              <div class="product-single__reviews-item">
                <div class="customer-avatar">
                  <img loading="lazy" src="./images/avatar.jpg" alt="">
                </div>
                <div class="customer-review">
                  <div class="customer-name">
                    <h6>Janice Miller</h6>
                    <div class="reviews-group d-flex">
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    </div>
                  </div>
                  <div class="review-date">April 06, 2023</div>
                  <div class="review-text">
                    <p>Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est…</p>
                  </div>
                </div>
              </div>
              <div class="product-single__reviews-item">
                <div class="customer-avatar">
                  <img loading="lazy" src="./images/avatar.jpg" alt="">
                </div>
                <div class="customer-review">
                  <div class="customer-name">
                    <h6>Benjam Porter</h6>
                    <div class="reviews-group d-flex">
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                      <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    </div>
                  </div>
                  <div class="review-date">April 06, 2023</div>
                  <div class="review-text">
                    <p>Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est…</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="product-single__review-form">
              <form name="customer-review-form">
                <h5>Be the first to review “Message Cotton T-Shirt”</h5>
                <p>Your email address will not be published. Required fields are marked *</p>
                <div class="select-star-rating">
                  <label>Your rating *</label>
                  <span class="star-rating">
                    <svg class="star-rating__star-icon" width="12" height="12" fill="#ccc" viewbox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                      <path d="M11.1429 5.04687C11.1429 4.84598 10.9286 4.76562 10.7679 4.73884L7.40625 4.25L5.89955 1.20312C5.83929 1.07589 5.72545 0.928571 5.57143 0.928571C5.41741 0.928571 5.30357 1.07589 5.2433 1.20312L3.73661 4.25L0.375 4.73884C0.207589 4.76562 0 4.84598 0 5.04687C0 5.16741 0.0870536 5.28125 0.167411 5.3683L2.60491 7.73884L2.02902 11.0871C2.02232 11.1339 2.01563 11.1741 2.01563 11.221C2.01563 11.3951 2.10268 11.5558 2.29688 11.5558C2.39063 11.5558 2.47768 11.5223 2.56473 11.4754L5.57143 9.89509L8.57813 11.4754C8.65848 11.5223 8.75223 11.5558 8.84598 11.5558C9.04018 11.5558 9.12054 11.3951 9.12054 11.221C9.12054 11.1741 9.12054 11.1339 9.11384 11.0871L8.53795 7.73884L10.9688 5.3683C11.0558 5.28125 11.1429 5.16741 11.1429 5.04687Z"></path>
                    </svg>
                    <svg class="star-rating__star-icon" width="12" height="12" fill="#ccc" viewbox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                      <path d="M11.1429 5.04687C11.1429 4.84598 10.9286 4.76562 10.7679 4.73884L7.40625 4.25L5.89955 1.20312C5.83929 1.07589 5.72545 0.928571 5.57143 0.928571C5.41741 0.928571 5.30357 1.07589 5.2433 1.20312L3.73661 4.25L0.375 4.73884C0.207589 4.76562 0 4.84598 0 5.04687C0 5.16741 0.0870536 5.28125 0.167411 5.3683L2.60491 7.73884L2.02902 11.0871C2.02232 11.1339 2.01563 11.1741 2.01563 11.221C2.01563 11.3951 2.10268 11.5558 2.29688 11.5558C2.39063 11.5558 2.47768 11.5223 2.56473 11.4754L5.57143 9.89509L8.57813 11.4754C8.65848 11.5223 8.75223 11.5558 8.84598 11.5558C9.04018 11.5558 9.12054 11.3951 9.12054 11.221C9.12054 11.1741 9.12054 11.1339 9.11384 11.0871L8.53795 7.73884L10.9688 5.3683C11.0558 5.28125 11.1429 5.16741 11.1429 5.04687Z"></path>
                    </svg>
                    <svg class="star-rating__star-icon" width="12" height="12" fill="#ccc" viewbox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                      <path d="M11.1429 5.04687C11.1429 4.84598 10.9286 4.76562 10.7679 4.73884L7.40625 4.25L5.89955 1.20312C5.83929 1.07589 5.72545 0.928571 5.57143 0.928571C5.41741 0.928571 5.30357 1.07589 5.2433 1.20312L3.73661 4.25L0.375 4.73884C0.207589 4.76562 0 4.84598 0 5.04687C0 5.16741 0.0870536 5.28125 0.167411 5.3683L2.60491 7.73884L2.02902 11.0871C2.02232 11.1339 2.01563 11.1741 2.01563 11.221C2.01563 11.3951 2.10268 11.5558 2.29688 11.5558C2.39063 11.5558 2.47768 11.5223 2.56473 11.4754L5.57143 9.89509L8.57813 11.4754C8.65848 11.5223 8.75223 11.5558 8.84598 11.5558C9.04018 11.5558 9.12054 11.3951 9.12054 11.221C9.12054 11.1741 9.12054 11.1339 9.11384 11.0871L8.53795 7.73884L10.9688 5.3683C11.0558 5.28125 11.1429 5.16741 11.1429 5.04687Z"></path>
                    </svg>
                    <svg class="star-rating__star-icon" width="12" height="12" fill="#ccc" viewbox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                      <path d="M11.1429 5.04687C11.1429 4.84598 10.9286 4.76562 10.7679 4.73884L7.40625 4.25L5.89955 1.20312C5.83929 1.07589 5.72545 0.928571 5.57143 0.928571C5.41741 0.928571 5.30357 1.07589 5.2433 1.20312L3.73661 4.25L0.375 4.73884C0.207589 4.76562 0 4.84598 0 5.04687C0 5.16741 0.0870536 5.28125 0.167411 5.3683L2.60491 7.73884L2.02902 11.0871C2.02232 11.1339 2.01563 11.1741 2.01563 11.221C2.01563 11.3951 2.10268 11.5558 2.29688 11.5558C2.39063 11.5558 2.47768 11.5223 2.56473 11.4754L5.57143 9.89509L8.57813 11.4754C8.65848 11.5223 8.75223 11.5558 8.84598 11.5558C9.04018 11.5558 9.12054 11.3951 9.12054 11.221C9.12054 11.1741 9.12054 11.1339 9.11384 11.0871L8.53795 7.73884L10.9688 5.3683C11.0558 5.28125 11.1429 5.16741 11.1429 5.04687Z"></path>
                    </svg>
                    <svg class="star-rating__star-icon" width="12" height="12" fill="#ccc" viewbox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                      <path d="M11.1429 5.04687C11.1429 4.84598 10.9286 4.76562 10.7679 4.73884L7.40625 4.25L5.89955 1.20312C5.83929 1.07589 5.72545 0.928571 5.57143 0.928571C5.41741 0.928571 5.30357 1.07589 5.2433 1.20312L3.73661 4.25L0.375 4.73884C0.207589 4.76562 0 4.84598 0 5.04687C0 5.16741 0.0870536 5.28125 0.167411 5.3683L2.60491 7.73884L2.02902 11.0871C2.02232 11.1339 2.01563 11.1741 2.01563 11.221C2.01563 11.3951 2.10268 11.5558 2.29688 11.5558C2.39063 11.5558 2.47768 11.5223 2.56473 11.4754L5.57143 9.89509L8.57813 11.4754C8.65848 11.5223 8.75223 11.5558 8.84598 11.5558C9.04018 11.5558 9.12054 11.3951 9.12054 11.221C9.12054 11.1741 9.12054 11.1339 9.11384 11.0871L8.53795 7.73884L10.9688 5.3683C11.0558 5.28125 11.1429 5.16741 11.1429 5.04687Z"></path>
                    </svg>
                  </span>
                  <input type="hidden" id="form-input-rating" value="">
                </div>
                <div class="mb-4">
                  <textarea id="form-input-review" class="form-control form-control_gray" placeholder="Your Review" cols="30" rows="8"></textarea>
                </div>
                <div class="form-label-fixed mb-4">
                  <label for="form-input-name" class="form-label">Name *</label>
                  <input id="form-input-name" class="form-control form-control-md form-control_gray">
                </div>
                <div class="form-label-fixed mb-4">
                  <label for="form-input-email" class="form-label">Email address *</label>
                  <input id="form-input-email" class="form-control form-control-md form-control_gray">
                </div>
                <div class="form-check mb-4">
                  <input class="form-check-input form-check-input_fill" type="checkbox" value="" id="remember_checkbox">
                  <label class="form-check-label" for="remember_checkbox">
                    Save my name, email, and website in this browser for the next time I comment.
                  </label>
                </div>
                <div class="form-action">
                  <button type="submit" class="btn btn-primary">Submit</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>



    <!-- Related Product section -->
    <?php include("includes/product/related-product.php"); ?>

    <?php include("includes/product/size-guide.php"); ?>
  </main>

  <div class="mb-5 pb-xl-5"></div>
  <!-- size guide MODAL -->

<!-- footer -->
<?php include("includes/footer.php"); ?>

<!-- End Footer Type 1 -->
<?php include("includes/mobile-footer.php"); ?>


<!-- form -->
<?php include("includes/aside-form.php"); ?>

<!-- aside cart -->
<?php include("includes/cart-aside.php"); ?>



<!-- sitemap -->
<?php include("includes/sitemap-nav.php"); ?>




<?php include("includes/scroll.php"); ?>


  <!-- Sizeguide -->
<?php include("includes/product/sizeguide.php"); ?>

  <!-- Page Overlay -->
  <div class="page-overlay"></div><!-- /.page-overlay -->

<!-- script footer -->
<?php include("includes/script-footer.php"); ?>


  <script>
    // ===== Server data =====
    const priceMap   = <?= $jsMap ?: '{}' ?>;       // base currency
    const basePrice  = <?= json_encode($jsBasePrice) ?>;
    const rates      = <?= $jsRates ?>;             // code -> rate_to_base
    const symbols    = <?= $jsSymbols ?>;           // code -> symbol
    let baseCode     = <?= json_encode($jsBaseCode) ?>;
    let displayCode  = <?= json_encode($jsDisplay) ?>;
    const mainFallback = <?= json_encode($jsMainImage) ?>;
    const chartRows  = <?= $jsSizeChart ?>;         // CM by default
    const thumbs     = <?= $jsThumbs ?>;

    const HAS_SIZES  = <?= $hasSizes ? 'true' : 'false' ?>;
    const HAS_COLORS = <?= $hasColors ? 'true' : 'false' ?>;

    // ===== Elements =====
    const mainImg    = document.getElementById('mainImage');
    const mainPrice  = document.getElementById('mainPrice');
    const stockNote  = document.getElementById('stockNote');
    const curSel     = document.getElementById('curSelect');
    const sizeGrid   = document.getElementById('sizeGrid');
    const colorGrid  = document.getElementById('colorGrid');
    const thumbRail  = document.getElementById('thumbRail');

    const chartTableBody = document.querySelector('#chartTable tbody');

    let selectedSize  = '';
    let selectedColor = '';

    // ===== Helpers =====
    const fmt = n => new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n);
    function convert(amount, fromCode, toCode){
      if (fromCode === toCode) return amount;
      const base = amount * (rates[fromCode] ?? 1);    // to base
      return base / (rates[toCode] ?? 1);              // base -> to
    }

    function comboValid(sz, col){
      if (!HAS_SIZES && HAS_COLORS) return !!priceMap[`|${col}`];
      if (HAS_SIZES && !HAS_COLORS) return !!priceMap[`${sz}|`];
      return !!(priceMap[`${sz}|${col}`] || priceMap[`${sz}|`] || priceMap[`|${col}`]);
    }

    function refreshDisables(){
      if (sizeGrid){
        sizeGrid.querySelectorAll('.swatch').forEach(s => {
          const sz = s.dataset.size || '';
          const ok = !HAS_COLORS ? !!priceMap[`${sz}|`] : (selectedColor ? comboValid(sz, selectedColor) : true);
          s.classList.toggle('disabled', !ok);
          s.setAttribute('aria-disabled', !ok ? 'true' : 'false');
        });
      }
      if (colorGrid){
        colorGrid.querySelectorAll('.swatch').forEach(s => {
          const col = s.dataset.color || '';
          const ok = !HAS_SIZES ? !!priceMap[`|${col}`] : (selectedSize ? comboValid(selectedSize, col) : true);
          s.classList.toggle('disabled', !ok);
          s.setAttribute('aria-disabled', !ok ? 'true' : 'false');
        });
      }
    }

    function resolveVariant(){
      let k = `${selectedSize}|${selectedColor}`;
      if (priceMap[k]) return priceMap[k];
      k = `${selectedSize}|`; if (selectedSize && priceMap[k]) return priceMap[k];
      k = `|${selectedColor}`; if (selectedColor && priceMap[k]) return priceMap[k];
      return { price: basePrice, stock: null, image: null };
    }

    function setActive(container, el){
      if (!container) return;
      container.querySelectorAll('.swatch').forEach(s => { s.classList.remove('active'); s.setAttribute('aria-pressed','false'); });
      if (el){ el.classList.add('active'); el.setAttribute('aria-pressed','true'); }
    }
    function clearActive(container){
      if (!container) return;
      container.querySelectorAll('.swatch').forEach(s => { s.classList.remove('active'); s.setAttribute('aria-pressed','false'); });
    }

    function updateUI(){
      const { price, stock, image } = resolveVariant();
      const disp = convert(price, baseCode, displayCode);
      const sym  = symbols[displayCode] || '';
      mainPrice.textContent = `${sym}${fmt(disp)}`;
      stockNote.textContent = (stock !== null && stock !== undefined) ? `Stock for selection: ${stock}` : '';
      const target = image || mainFallback || '';
      if (mainImg) {
        if (target) mainImg.src = target;
        else mainImg.removeAttribute('src');
      }
      refreshDisables();
    }

    // Size swatches
    sizeGrid?.addEventListener('click', e => {
      const sw = e.target.closest('.swatch');
      if (!sw || sw.classList.contains('disabled')) return;
      const val = sw.dataset.size || '';
      if (sw.classList.contains('active')) {
        selectedSize = '';
        clearActive(sizeGrid);
        refreshDisables();
        updateUI();
        return;
      }
      selectedSize = val;
      setActive(sizeGrid, sw);
      if (sw.dataset.image && mainImg) mainImg.src = sw.dataset.image;
      updateUI();
    });

    // Color swatches
    colorGrid?.addEventListener('click', e => {
      const sw = e.target.closest('.swatch');
      if (!sw || sw.classList.contains('disabled')) return;
      const val = sw.dataset.color || '';
      if (sw.classList.contains('active')) {
        selectedColor = '';
        clearActive(colorGrid);
        refreshDisables();
        updateUI();
        return;
      }
      selectedColor = val;
      setActive(colorGrid, sw);
      if (sw.dataset.image && mainImg) mainImg.src = sw.dataset.image;
      updateUI();
    });

    // Currency
    curSel?.addEventListener('change', () => {
      displayCode = curSel.value;
      updateUI();
      const url = new URL(window.location.href);
      url.searchParams.set('cur', displayCode);
      window.history.replaceState({}, '', url);
    });

    // Thumb rail
    thumbRail?.addEventListener('click', (e) => {
      const img = e.target.closest('img[data-src]');
      if (!img) return;
      thumbRail.querySelectorAll('img').forEach(i => i.classList.remove('active'));
      img.classList.add('active');
      if (mainImg) mainImg.src = img.dataset.src;
    });

    // Size chart (CM)
    function renderChartCM(){
      chartTableBody.innerHTML = '';
      chartRows.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${row.label}</strong></td>
          <td>${Math.round(row.bust)} CM</td>
          <td>${Math.round(row.waist)} CM</td>
          <td>${Math.round(row.hips)} CM</td>
        `;
        chartTableBody.appendChild(tr);
      });
    }

    // Init
    renderChartCM();
    refreshDisables();
    updateUI();
  </script>