<?php
// includes/mobile-header.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';      // provides base_url()
require_once __DIR__ . '/auth_user.php'; // provides require_user()

/* ---------------- Counters ---------------- */
$cartCount = (int)($_SESSION['cart_count'] ?? 0);

$wishCount = 0;
try {
  if (!empty($_SESSION['user_id']) && isset($pdo) && $pdo instanceof PDO) {
    $uid = (int)$_SESSION['user_id'];
    if ($uid > 0) {
      $st = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
      $st->execute([$uid]);
      $wishCount = (int)$st->fetchColumn();
    }
  }
} catch (Throwable $e) { $wishCount = 0; }

/* -------------- Mega menu attributes -------------- */
$__attrs = ['occasion'=>[], 'style'=>[], 'length'=>[]];
try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $typeMap = ['occasion'=>null, 'style'=>null, 'length'=>null];
    $tq = $pdo->query("SELECT id, code FROM attribute_types");
    foreach ($tq->fetchAll(PDO::FETCH_ASSOC) as $t) {
      $code = strtolower((string)$t['code']);
      if (array_key_exists($code, $typeMap)) $typeMap[$code] = (int)$t['id'];
    }
    $as = $pdo->prepare("SELECT value FROM attributes WHERE type_id = ? ORDER BY value ASC");
    foreach (['occasion','style','length'] as $code) {
      if (!empty($typeMap[$code])) {
        $as->execute([$typeMap[$code]]);
        $__attrs[$code] = $as->fetchAll(PDO::FETCH_COLUMN) ?: [];
      }
    }
  }
} catch (Throwable $e) { /* silent */ }

/* -------------- Tiny helpers -------------- */
$__e    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$__base = fn(string $path = '') => htmlspecialchars(base_url($path), ENT_QUOTES, 'UTF-8');
?>
<!-- Mobile Header -->
<style>
  .header-mobile { position: relative; z-index: 1030; }
  .header-mobile__navigation[hidden] { display: none !important; }
  .header-mobile__navigation {
    position: absolute; left: 0; right: 0; top: 100%;
    max-height: 75vh;
    overflow: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,.08);
  }
  .header-mobile[data-open="1"] .header-mobile__navigation[hidden] { display: block !important; }
  body.nav-locked { overflow: hidden; touch-action: none; }
</style>

<div class="header-mobile header_sticky" data-mobile-nav-container>
  <div class="container d-flex align-items-center h-100 py-2">
    <button type="button"
            class="mobile-nav-activator btn btn-link p-0 me-2"
            aria-controls="mobileMainNav"
            aria-expanded="false"
            aria-label="Open menu"
            data-mobile-nav-toggle>
      <svg class="nav-icon" width="25" height="18" xmlns="http://www.w3.org/2000/svg"><use href="#icon_nav"></use></svg>
    </button>

    <div class="logo ms-1 me-auto">
      <a href="<?= $__base() ?>">
        <img src="<?= $__base('images/logo.svg') ?>" alt="jhema" class="logo__image d-block" />
      </a>
    </div>

    <!-- Cart -->
    <a href="#"
       class="header-tools__item header-tools__cart js-open-aside position-relative"
       data-aside="cartDrawer"
       aria-label="Cart" title="Cart">
      <svg width="20" height="20"><use href="#icon_cart" /></svg>
      <span class="cart-amount position-absolute js-cart-items-count"><?= $__e((string)$cartCount) ?></span>
    </a>
  </div>

  <!-- Horizontal scroll categories -->
  <section>
    <?php
      if (!defined('JHEMA_SCROLL_CATEGORIES_INCLUDED')) {
        define('JHEMA_SCROLL_CATEGORIES_INCLUDED', true);
        $scrollPath = dirname(__DIR__) . '/scroll_categories.php';
        if (is_file($scrollPath)) { include $scrollPath; }
      }
    ?>
  </section>

  <!-- Mobile Navigation -->
  <nav id="mobileMainNav"
       class="header-mobile__navigation navigation d-flex flex-column w-100 bg-body overflow-auto"
       hidden>
    <div class="container">
      <form action="<?= $__base('search_result.php') ?>" method="GET" class="search-field position-relative mt-4 mb-3" role="search">
        <div class="position-relative">
          <input class="search-field__input w-100 border rounded-1"
                 type="text" name="search-keyword" placeholder="Search products" autocomplete="off" />
          <button class="btn-icon search-popup__submit pb-0 me-2" type="submit" aria-label="Search">
            <svg width="20" height="20"><use href="#icon_search" /></svg>
          </button>
        </div>
      </form>
    </div>

    <div class="container">
      <ul class="navigation__list list-unstyled">
        <li class="navigation__item"><a href="<?= $__base('index.php') ?>" class="navigation__link">Home</a></li>

        <li class="navigation__item">
          <button type="button" class="navigation__link d-flex align-items-center w-100 btn btn-link text-start p-0"
                  data-subnav-toggle="#mobileShopSub">
            <span>Shop</span>
            <svg class="ms-auto" width="7" height="11"><use href="#icon_next_sm"></use></svg>
          </button>

          <div id="mobileShopSub" class="sub-menu w-100" hidden>
            <button type="button" class="navigation__link d-flex align-items-center border-bottom mb-3 btn btn-link text-start p-0"
                    data-subnav-back>
              <svg class="me-2" width="7" height="11"><use href="#icon_prev_sm"></use></svg>
              Shop
            </button>

            <div class="sub-menu__wrapper">
              <ul class="sub-menu__list list-unstyled mb-3">
                <li class="sub-menu__item">
                  <a href="<?= $__base('shop/shop.php') ?>" class="menu-link">All Products</a>
                </li>
              </ul>

              <h6 class="sub-menu__title fs-base">Shop by Occasion</h6>
              <ul class="sub-menu__list list-unstyled mb-3">
                <?php if (!empty($__attrs['occasion'])): foreach ($__attrs['occasion'] as $v): ?>
                  <li><a href="<?= $__base('shop/shop.php') ?>?occasion=<?= urlencode($v) ?>" class="menu-link"><?= $__e($v) ?></a></li>
                <?php endforeach; else: ?>
                  <li><span class="text-muted">No occasions yet</span></li>
                <?php endif; ?>
              </ul>

              <h6 class="sub-menu__title fs-base">Shop by Style</h6>
              <ul class="sub-menu__list list-unstyled mb-3">
                <?php if (!empty($__attrs['style'])): foreach ($__attrs['style'] as $v): ?>
                  <li><a href="<?= $__base('shop/shop.php') ?>?style=<?= urlencode($v) ?>" class="menu-link"><?= $__e($v) ?></a></li>
                <?php endforeach; else: ?>
                  <li><span class="text-muted">No styles yet</span></li>
                <?php endif; ?>
              </ul>

              <h6 class="sub-menu__title fs-base">Shop by Length</h6>
              <ul class="sub-menu__list list-unstyled">
                <?php if (!empty($__attrs['length'])): foreach ($__attrs['length'] as $v): ?>
                  <li><a href="<?= $__base('shop/shop.php') ?>?length=<?= urlencode($v) ?>" class="menu-link"><?= $__e($v) ?></a></li>
                <?php endforeach; else: ?>
                  <li><span class="text-muted">No length options yet</span></li>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        </li>

        <li class="navigation__item"><a href="<?= $__base('about.php') ?>" class="navigation__link">About</a></li>
        <li class="navigation__item"><a href="<?= $__base('contact.php') ?>" class="navigation__link">Contact</a></li>
        <li class="navigation__item"><a href="<?= $__base('faq.php') ?>" class="navigation__link">FAQ</a></li>
      </ul>
    </div>

    <!-- Account link -->
    <div class="customer-links container mt-4 mb-2 pb-1">
      <svg width="20" height="20"><use href="#icon_user" /></svg>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?= $__base('account/dashboard.php') ?>" class="ms-2 text-uppercase fw-medium">My Account</a>
      <?php else: ?>
        <a href="<?= $__base('account/auth.php?tab=login') ?>" class="ms-2 text-uppercase fw-medium">Sign In</a>
      <?php endif; ?>
    </div>

    <!-- Language -->
    <div class="container d-flex align-items-center">
      <label for="footerSettingsLanguage_mobile" class="me-2 text-secondary">Language</label>
      <select id="footerSettingsLanguage_mobile" class="form-select form-select-sm bg-transparent border-0">
        <option selected>United Kingdom | English</option>
        <option value="us-en">United States | English</option>
        <option value="de">German</option>
        <option value="fr">French</option>
        <option value="sv">Swedish</option>
      </select>
    </div>

    <!-- Currency -->
    <div class="container d-flex align-items-center">
      <label for="footerSettingsCurrency_mobile" class="me-2 text-secondary">Currency</label>
      <select id="footerSettingsCurrency_mobile" class="form-select form-select-sm bg-transparent border-0">
        <option value="USD" selected>$ USD</option>
        <option value="GBP">£ GBP</option>
        <option value="EUR">€ EURO</option>
      </select>
    </div>

    <!-- Socials -->
    <ul class="container social-links list-unstyled d-flex flex-wrap mb-0">
      <li><a href="https://www.facebook.com" aria-label="Facebook"><svg width="9" height="15"><use href="#icon_facebook" /></svg></a></li>
      <li><a href="https://twitter.com" aria-label="Twitter"><svg width="14" height="13"><use href="#icon_twitter" /></svg></a></li>
      <li><a href="https://www.instagram.com" aria-label="Instagram"><svg width="14" height="13"><use href="#icon_instagram" /></svg></a></li>
      <li><a href="https://www.youtube.com" aria-label="YouTube"><svg width="16" height="11"><use href="#icon_youtube" /></svg></a></li>
      <li><a href="https://www.pinterest.com" aria-label="Pinterest"><svg width="14" height="15"><use href="#icon_pinterest" /></svg></a></li>
    </ul>
  </nav>

  <!-- Quick access -->
  <div class="container d-flex justify-content-end gap-3 py-2">
    <a class="header-tools__item position-relative"
       href="<?= $__base('account/account_wishlist.php') ?>"
       aria-label="Wishlist" title="Wishlist">
      <svg width="20" height="20"><use href="#icon_heart" /></svg>
      <span class="wishlist-amount position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
            style="min-width:18px;height:18px;line-height:18px;font-size:11px;"
            data-wishlist-count><?= $__e((string)$wishCount) ?></span>
    </a>

    <a class="header-tools__item" href="#" data-bs-toggle="modal" data-bs-target="#siteMap" aria-label="Open sitemap">
      <svg width="25" height="18"><use href="#icon_nav" /></svg>
    </a>
  </div>
</div>

<script>
(function(){
  const container = document.querySelector('[data-mobile-nav-container]');
  const toggleBtn  = container?.querySelector('[data-mobile-nav-toggle]');
  const nav        = document.getElementById('mobileMainNav');

  function openNav() {
    container?.setAttribute('data-open','1');
    if (nav) nav.hidden = false;
    document.body.classList.add('nav-locked');
    toggleBtn?.setAttribute('aria-expanded','true');
  }
  function closeNav() {
    container?.removeAttribute('data-open');
    if (nav) nav.hidden = true;
    document.body.classList.remove('nav-locked');
    toggleBtn?.setAttribute('aria-expanded','false');
  }
  toggleBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    (container?.getAttribute('data-open') === '1') ? closeNav() : openNav();
  });

  container?.querySelectorAll('[data-subnav-toggle]')?.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const target = document.querySelector(btn.getAttribute('data-subnav-toggle'));
      if (target) target.hidden = false;
    });
  });
  container?.querySelectorAll('[data-subnav-back]')?.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const wrap = btn.closest('.sub-menu');
      if (wrap) wrap.hidden = true;
    });
  });

  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeNav(); });
  document.addEventListener('click', (e)=>{
    if (!container) return;
    if (!container.contains(e.target) && container.getAttribute('data-open') === '1') closeNav();
  });
})();
</script>
