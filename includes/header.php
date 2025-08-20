<?php
// includes/header.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_user.php'; // Changed from auth.php to auth_user.php

// Simple cart items count (adjust to your cart implementation)
$cartCount = (int)($_SESSION['cart_count'] ?? 0);

// --- Wishlist items count (use the same table name everywhere: `wishlist`) ---
$wishCount = 0;
try {
  if (!empty($_SESSION['user_id']) && isset($pdo) && $pdo instanceof PDO) { // Changed from is_logged_in()
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
      $st = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
      $st->execute([$uid]);
      $wishCount = (int)$st->fetchColumn();
    }
  }
} catch (Throwable $e) {
  $wishCount = 0; // fail-safe
}

/**
 * Use the existing $pdo provided by config.php to fetch attributes for the mega menu.
 * We DO NOT create a new PDO here—only read via $pdo.
 */
$__attrs = ['occasion'=>[], 'style'=>[], 'length'=>[]];

try {
  if (isset($pdo) && $pdo instanceof PDO) {
    // Load attribute type IDs for occasion/style/length
    $typeMap = ['occasion'=>null, 'style'=>null, 'length'=>null];
    $tq = $pdo->query("SELECT id, code FROM attribute_types");
    foreach ($tq->fetchAll(PDO::FETCH_ASSOC) as $t) {
      $code = strtolower($t['code']);
      if (array_key_exists($code, $typeMap)) {
        $typeMap[$code] = (int)$t['id'];
      }
    }

    // Load attribute values per type
    $as = $pdo->prepare("SELECT value FROM attributes WHERE type_id = ? ORDER BY value ASC");
    foreach (['occasion','style','length'] as $code) {
      if (!empty($typeMap[$code])) {
        $as->execute([$typeMap[$code]]);
        $__attrs[$code] = $as->fetchAll(PDO::FETCH_COLUMN) ?: [];
      }
    }
  }
} catch (Throwable $e) {
  // fail silent: $__attrs remains empty arrays; menu will show "No ... yet"
}

// tiny helpers
$__e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$__base = fn(string $path = '') => htmlspecialchars(base_url($path), ENT_QUOTES, 'UTF-8');
?>
<!-- Header Type 1 -->
<header id="header" class="header header_sticky">
  <div class="container">
    <div class="header-desk header-desk_type_1">
      <div class="logo">
        <a href="<?= $__base() ?>">
          <img
            src="<?= $__base('images/logo.svg') ?>"
            alt="jhema"
            class="logo__image d-block"
          />
        </a>
      </div>
      <!-- /.logo -->

      <nav class="navigation">
        <ul class="navigation__list list-unstyled d-flex">
          <li class="navigation__item">
            <a href="<?= $__base('index.php') ?>" class="navigation__link">Home</a>
          </li>

          <li class="navigation__item">
            <a href="#" class="navigation__link">Shop</a>
            <div class="mega-menu position-absolute" style="z-index:99999 !important;">
              <div class="container d-flex">
                <!-- Column 1: Shop by Occasion -->
                <div class="col pe-4">
                  <a href="#" class="sub-menu__title">Shop by Occasion</a>
                  <ul class="sub-menu__list list-unstyled">
                    <?php if (!empty($__attrs['occasion'])): ?>
                      <?php foreach ($__attrs['occasion'] as $val): ?>
                        <li class="sub-menu__item">
                          <a href="<?= $__base('shop/shop.php') ?>?occasion=<?= urlencode($val) ?>" class="menu-link menu-link_us-s">
                            <?= $__e($val) ?>
                          </a>
                        </li>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <li class="sub-menu__item">
                        <span class="menu-link menu-link_us-s text-muted">No occasions yet</span>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>

                <!-- Column 2: Shop by Style -->
                <div class="col pe-4">
                  <a href="#" class="sub-menu__title">Shop by Style</a>
                  <ul class="sub-menu__list list-unstyled">
                    <?php if (!empty($__attrs['style'])): ?>
                      <?php foreach ($__attrs['style'] as $val): ?>
                        <li class="sub-menu__item">
                          <a href="<?= $__base('shop/shop.php') ?>?style=<?= urlencode($val) ?>" class="menu-link menu-link_us-s">
                            <?= $__e($val) ?>
                          </a>
                        </li>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <li class="sub-menu__item">
                        <span class="menu-link menu-link_us-s text-muted">No styles yet</span>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>

                <!-- Column 3: Shop by Length -->
                <div class="col pe-4">
                  <a href="#" class="sub-menu__title">Shop by Length</a>
                  <ul class="sub-menu__list list-unstyled">
                    <?php if (!empty($__attrs['length'])): ?>
                      <?php foreach ($__attrs['length'] as $val): ?>
                        <li class="sub-menu__item">
                          <a href="<?= $__base('shop/shop.php') ?>?length=<?= urlencode($val) ?>" class="menu-link menu-link_us-s">
                            <?= $__e($val) ?>
                          </a>
                        </li>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <li class="sub-menu__item">
                        <span class="menu-link menu-link_us-s text-muted">No length options yet</span>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>

                <!-- Media column -->
                <div class="mega-menu__media col">
                  <div class="position-relative">
                    <img loading="lazy" class="mega-menu__img" src="<?= $__base('images/mega-menu-item.jpg') ?>" alt="New Horizons">
                    <div class="mega-menu__media-content content_abs content_left content_bottom">
                      <h3>NEW</h3>
                      <h3 class="mb-0">HORIZONS</h3>
                      <a href="<?= $__base('shop1.php') ?>" class="btn-link default-underline fw-medium">SHOP NOW</a>
                    </div>
                  </div>
                </div>
              </div><!-- /.container d-flex -->
            </div>
          </li>

          <li class="navigation__item">
            <a href="<?= $__base('about.php') ?>" class="navigation__link">About</a>
          </li>
          <li class="navigation__item">
            <a href="<?= $__base('contact.php') ?>" class="navigation__link">Contact</a>
          </li>
          <li class="navigation__item">
            <a href="<?= $__base('faq.php') ?>" class="navigation__link">FAQ</a>
          </li>
        </ul>
      </nav>
      <!-- /.navigation -->

      <div class="header-tools d-flex align-items-center">
        <div class="header-tools__item hover-container">
          <div class="js-hover__open position-relative">
            <a class="js-search-popup search-field__actor" href="#">
              <svg class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <use href="#icon_search" />
              </svg>
              <i class="btn-icon btn-close-lg"></i>
            </a>
          </div>

          <div class="search-popup js-hidden-content">
            <form
              action="<?= $__base('search_result.php') ?>"
              method="GET"
              class="search-field container"
            >
              <p class="text-uppercase text-secondary fw-medium mb-4">
                What are you looking for?
              </p>
              <div class="position-relative">
                <input
                  class="search-field__input search-popup__input w-100 fw-medium"
                  type="text"
                  name="search-keyword"
                  placeholder="Search products"
                />
                <button class="btn-icon search-popup__submit" type="submit">
                  <svg class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <use href="#icon_search" />
                  </svg>
                </button>
                <button class="btn-icon btn-close-lg search-popup__reset" type="reset"></button>
              </div>

              <div class="search-popup__results">
                <div class="sub-menu search-suggestion">
                  <h6 class="sub-menu__title fs-base">Quicklinks</h6>
                  <ul class="sub-menu__list list-unstyled">
                    <li class="sub-menu__item">
                      <a href="<?= $__base('shop/shop.php') ?>" class="menu-link menu-link_us-s">New Arrivals</a>
                    </li>
                    <li class="sub-menu__item"><a href="#" class="menu-link menu-link_us-s">Dresses</a></li>
                    <li class="sub-menu__item">
                      <a href="<?= $__base('shop/shop.php') ?>" class="menu-link menu-link_us-s">Accessories</a>
                    </li>
                    <li class="sub-menu__item"><a href="#" class="menu-link menu-link_us-s">Footwear</a></li>
                    <li class="sub-menu__item"><a href="#" class="menu-link menu-link_us-s">Sweatshirt</a></li>
                  </ul>
                </div>

                <div class="search-result row row-cols-5"></div>
              </div>
            </form>
            <!-- /.header-search -->
          </div>
          <!-- /.search-popup -->
        </div>
        <!-- /.header-tools__item hover-container -->

        <?php if (!empty($_SESSION['user_id'])): // Changed from is_logged_in() ?>
          <div class="header-tools__item hover-container">
            <a
              class="header-tools__item js-open-aside"
              href="<?= $__base('account/dashboard.php') ?>"
              data-aside="accountAside"
            >
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><use href="#icon_user" /></svg>
            </a>
          </div>
        <?php else: ?>
          <div class="header-tools__item hover-container">
            <a
              class="header-tools__item js-open-aside"
              href="<?= $__base('account/auth.php?tab=login') ?>"
              data-aside="customerForms"
            >
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><use href="#icon_user" /></svg>
            </a>
          </div>
        <?php endif; ?>

        <!-- Wishlist with counter (make it JS-updatable) -->
        <a
          class="header-tools__item position-relative"
          href="<?= $__base('account/account_wishlist.php') ?>"
          aria-label="Wishlist"
          title="Wishlist"
        >
          <svg class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><use href="#icon_heart" /></svg>
          <span
            class="wishlist-amount d-block position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
            style="min-width:18px;height:18px;line-height:18px;font-size:11px;"
            data-wishlist-count
          ><?= $__e((string)$wishCount) ?></span>
        </a>

        <!-- Cart with counter -->
        <a
          href="#"
          class="header-tools__item header-tools__cart js-open-aside position-relative"
          data-aside="cartDrawer"
        >
          <svg class="d-block" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><use href="#icon_cart" /></svg>
          <span class="cart-amount d-block position-absolute js-cart-items-count"><?= $__e((string)$cartCount) ?></span>
        </a>

        <a
          class="header-tools__item"
          href="#"
          data-bs-toggle="modal"
          data-bs-target="#siteMap"
        >
          <svg class="nav-icon" width="25" height="18" viewBox="0 0 25 18" xmlns="http://www.w3.org/2000/svg"><use href="#icon_nav" /></svg>
        </a>
      </div>
      <!-- /.header__tools -->
    </div>
    <!-- /.header-desk header-desk_type_1 -->
  </div>
  <!-- /.container -->
</header>
<!-- End Header Type 1 -->

<?php
// Auto-include the account sideform so it's available when logged-in
if (!empty($_SESSION['user_id'])) { // Changed from is_logged_in()
  include __DIR__ . '/aside-account.php';
}