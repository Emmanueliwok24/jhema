<?php
// --- fetch categories + attributes (STYLE/LENGTH/OCCASION) ---
require_once __DIR__ . '/config.php';

$cats = [];
$attrTypes = ['style' => [], 'length' => [], 'occasion' => []];

try {
  // Categories
  $q = $pdo->query("SELECT name, slug FROM categories ORDER BY name ASC");
  $cats = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Attribute types -> id
  $typeStmt = $pdo->query("SELECT id, code, label FROM attribute_types");
  $typeRows = $typeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $typeMap = [];
  foreach ($typeRows as $t) {
    $typeMap[strtolower($t['code'])] = (int)$t['id'];
  }

  // Attributes for each code we care about
  $attrStmt = $pdo->prepare("SELECT value FROM attributes WHERE type_id = ? ORDER BY value ASC");

  foreach (['style', 'length', 'occasion'] as $code) {
    if (!empty($typeMap[$code])) {
      $attrStmt->execute([$typeMap[$code]]);
      $attrTypes[$code] = $attrStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } else {
      $attrTypes[$code] = [];
    }
  }
} catch (Throwable $e) {
  // graceful fallbacks
  $cats = $cats ?: [];
  $attrTypes = $attrTypes ?: ['style'=>[], 'length'=>[], 'occasion'=>[]];
}

// little helper for safe link text
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>

<!-- Sitemap -->
<div class="modal fade" id="siteMap" tabindex="-1">
  <div class="modal-dialog modal-fullscreen">
    <div class="sitemap d-flex">
      <div class="w-50 d-none d-lg-block">
        <img loading="lazy" src="./images/nav-bg.jpg" alt="Site map" class="sitemap__bg" />
      </div>
      <!-- /.sitemap__bg w-50 d-none d-lg-block -->

      <div class="sitemap__links w-50 flex-grow-1">
        <div class="modal-content">
          <div class="modal-header">
            <ul class="nav nav-pills" id="pills-tab" role="tablist">
              <!-- Keep IDs/structure; only labels changed -->
              <li class="nav-item" role="presentation">
                <a class="nav-link active rounded-1 text-uppercase"
                   id="pills-item-1-tab"
                   data-bs-toggle="pill"
                   href="#pills-item-1"
                   role="tab"
                   aria-controls="pills-item-1"
                   aria-selected="true">STYLE</a>
              </li>
              <li class="nav-item" role="presentation">
                <a class="nav-link rounded-1 text-uppercase"
                   id="pills-item-2-tab"
                   data-bs-toggle="pill"
                   href="#pills-item-2"
                   role="tab"
                   aria-controls="pills-item-2"
                   aria-selected="false">LENGTH</a>
              </li>
              <li class="nav-item" role="presentation">
                <a class="nav-link rounded-1 text-uppercase"
                   id="pills-item-3-tab"
                   data-bs-toggle="pill"
                   href="#pills-item-3"
                   role="tab"
                   aria-controls="pills-item-3"
                   aria-selected="false">OCCASION</a>
              </li>
            </ul>
            <button type="button" class="btn-close-lg" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div class="tab-content col-12" id="pills-tabContent">

              <!-- ===================== STYLE (tab 1) ===================== -->
              <div class="tab-pane fade show active" id="pills-item-1" role="tabpanel" aria-labelledby="pills-item-1-tab">
                <div class="row">
                  <!-- Left column tabs (IDs preserved) -->
                  <ul class="nav nav-tabs list-unstyled col-5 d-block" id="myTab" role="tablist">
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline active" id="tab-item-1-tab" data-bs-toggle="tab" href="#tab-item-1" role="tab" aria-controls="tab-item-1" aria-selected="true">
                        <span class="rline-content">STYLE</span>
                      </a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline" id="tab-item-2-tab" data-bs-toggle="tab" href="#tab-item-2" role="tab" aria-controls="tab-item-2" aria-selected="false">
                        <span class="rline-content">CATEGORIES</span>
                      </a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline" id="tab-item-3-tab" data-bs-toggle="tab" href="#tab-item-3" role="tab" aria-controls="tab-item-3" aria-selected="false">
                        <span class="rline-content">FEATURED</span>
                      </a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline" href="#"><span class="rline-content">HOME</span></a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline" href="#"><span class="rline-content">COLLECTION</span></a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline text-red" href="#">SALE UP TO 50% OFF</a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline" href="#"><span class="rline-content">NEW</span></a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline" href="#"><span class="rline-content">SHOES</span></a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline" href="#"><span class="rline-content">ACCESSORIES</span></a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline" href="#"><span class="rline-content">JOIN LIFE</span></a>
                    </li>
                    <li class="nav-item position-relative" role="presentation">
                      <a class="nav-link nav-link_rline" href="#"><span class="rline-content">#jhemaSTYLE</span></a>
                    </li>
                  </ul>

                  <!-- Right column panes (IDs preserved) -->
                  <div class="tab-content col-7" id="myTabContent">
                    <!-- tab-item-1: STYLE values -->
                    <div class="tab-pane fade show active" id="tab-item-1" role="tabpanel" aria-labelledby="tab-item-1-tab">
                      <ul class="sub-menu list-unstyled">
                        <?php if (!empty($attrTypes['style'])): ?>
                          <?php foreach ($attrTypes['style'] as $val): ?>
                            <li class="sub-menu__item">
                              <a href="<?= BASE_URL ?>shop/shop.php?style=<?= urlencode($val) ?>" class="menu-link menu-link_us-s"><?= $e($val) ?></a>
                            </li>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <li class="sub-menu__item"><span class="menu-link menu-link_us-s text-muted">No styles yet</span></li>
                        <?php endif; ?>
                      </ul>
                    </div>

                    <!-- tab-item-2: CATEGORIES -->
                    <div class="tab-pane fade" id="tab-item-2" role="tabpanel" aria-labelledby="tab-item-2-tab">
                      <ul class="sub-menu list-unstyled">
                        <?php if (!empty($cats)): ?>
                          <?php foreach ($cats as $c): ?>
                            <li class="sub-menu__item">
                              <a href="<?= BASE_URL ?>shop/shop.php?category=<?= urlencode($c['slug']) ?>" class="menu-link menu-link_us-s">
                                <?= $e($c['name']) ?>
                              </a>
                            </li>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <li class="sub-menu__item"><span class="menu-link menu-link_us-s text-muted">No categories yet</span></li>
                        <?php endif; ?>
                      </ul>
                    </div>

                    <!-- tab-item-3: FEATURED (keep placeholders/links as in your original) -->
                    <div class="tab-pane fade" id="tab-item-3" role="tabpanel" aria-labelledby="tab-item-3-tab">
                      <ul class="sub-menu list-unstyled">
                        <li class="sub-menu__item"><a href="#" class="menu-link menu-link_us-s">New</a></li>
                        <li class="sub-menu__item"><a href="#" class="menu-link menu-link_us-s">Best Sellers</a></li>
                        <li class="sub-menu__item"><a href="#" class="menu-link menu-link_us-s">Collaborations®</a></li>
                        <li class="sub-menu__item"><a href="#" class="menu-link menu-link_us-s">Sets</a></li>
                        <li class="sub-menu__item"><a href="<?= BASE_URL ?>shop3.php" class="menu-link menu-link_us-s">Accessories</a></li>
                        <li class="sub-menu__item"><a href="<?= BASE_URL ?>about.php" class="menu-link menu-link_us-s">Gift Card</a></li>
                      </ul>
                    </div>
                  </div>
                </div>
                <!-- /.row -->
              </div>

              <!-- ===================== LENGTH (tab 2) ===================== -->
              <div class="tab-pane fade" id="pills-item-2" role="tabpanel" aria-labelledby="pills-item-2-tab">
                <ul class="sub-menu list-unstyled">
                  <?php if (!empty($attrTypes['length'])): ?>
                    <?php foreach ($attrTypes['length'] as $val): ?>
                      <li class="sub-menu__item">
                        <a href="<?= BASE_URL ?>shop/shop.php?length=<?= urlencode($val) ?>" class="menu-link menu-link_us-s"><?= $e($val) ?></a>
                      </li>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <li class="sub-menu__item"><span class="menu-link menu-link_us-s text-muted">No length options yet</span></li>
                  <?php endif; ?>
                </ul>
                Elementum lectus a porta commodo suspendisse arcu, aliquam lectus faucibus.
              </div>

              <!-- ===================== OCCASION (tab 3) ===================== -->
              <div class="tab-pane fade" id="pills-item-3" role="tabpanel" aria-labelledby="pills-item-3-tab">
                <ul class="sub-menu list-unstyled">
                  <?php if (!empty($attrTypes['occasion'])): ?>
                    <?php foreach ($attrTypes['occasion'] as $val): ?>
                      <li class="sub-menu__item">
                        <a href="<?= BASE_URL ?>shop/shop.php?occasion=<?= urlencode($val) ?>" class="menu-link menu-link_us-s"><?= $e($val) ?></a>
                      </li>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <li class="sub-menu__item"><span class="menu-link menu-link_us-s text-muted">No occasions yet</span></li>
                  <?php endif; ?>
                </ul>
                Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit.
              </div>

            </div>
          </div>
          <!-- /.modal-body -->
        </div>
        <!-- /.modal-content -->
      </div>
      <!-- /.sitemap__links w-50 flex-grow-1 -->
    </div>
  </div>
  <!-- /.modal-dialog modal-fullscreen -->
</div>
<!-- /.sitemap position-fixed w-100 -->
