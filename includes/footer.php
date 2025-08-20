<?php
// includes/footer.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

// Ensure config + functions are available (safe if already loaded)
if (!isset($pdo) || !($pdo instanceof PDO)) {
  require_once __DIR__ . '/config.php';
}
if (!function_exists('product_image_url')) {
  require_once __DIR__ . '/functions.php';
}

/* ---------- Small helpers ---------- */
$esc  = $esc ?? fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '';

/* ---------- Currency passthrough ---------- */
$curParam = isset($_GET['cur']) && $_GET['cur'] !== '' ? '&cur=' . urlencode((string)$_GET['cur']) : '';

// Note: We keep the footer structure intact. Shop menu is limited to the 5 items requested.
// The first three point to your index “collections” section for consistency with your homepage.
?>

<!-- Footer Type 1 -->
<footer class="footer footer_type_1">
  <div class="footer-middle container">
    <div class="row row-cols-lg-5 row-cols-2">
      <!-- Store Info -->
      <div class="footer-column footer-store-info col-12 mb-4 mb-lg-0">
        <div class="logo">
          <a href="<?= $BASE ?>">
            <img src="<?= $BASE ?>images/logo.svg" alt="jhema" class="logo__image d-block" />
          </a>
        </div>
        <!-- /.logo -->
        <p class="footer-address">
          1418 River Drive, Suite 35 Cottonhall, CA 9622 United States
        </p>
        <p class="m-0"><strong class="fw-medium">sale@jhema.com</strong></p>
        <p><strong class="fw-medium">+1 246-345-0695</strong></p>

        <ul class="social-links list-unstyled d-flex flex-wrap mb-0">
          <li>
            <a href="https://facebook.com" class="footer__social-link d-block" aria-label="Facebook">
              <svg class="svg-icon svg-icon_facebook" width="9" height="15" viewBox="0 0 9 15" xmlns="http://www.w3.org/2000/svg">
                <use href="#icon_facebook" />
              </svg>
            </a>
          </li>
          <li>
            <a href="https://twitter.com" class="footer__social-link d-block" aria-label="Twitter">
              <svg class="svg-icon svg-icon_twitter" width="14" height="13" viewBox="0 0 14 13" xmlns="http://www.w3.org/2000/svg">
                <use href="#icon_twitter" />
              </svg>
            </a>
          </li>
          <li>
            <a href="https://instagram.com" class="footer__social-link d-block" aria-label="Instagram">
              <svg class="svg-icon svg-icon_instagram" width="14" height="13" viewBox="0 0 14 13" xmlns="http://www.w3.org/2000/svg">
                <use href="#icon_instagram" />
              </svg>
            </a>
          </li>
          <li>
            <a href="https://youtube.com" class="footer__social-link d-block" aria-label="YouTube">
              <svg class="svg-icon svg-icon_youtube" width="16" height="11" viewBox="0 0 16 11" xmlns="http://www.w3.org/2000/svg">
                <path d="M15.0117 1.8584C14.8477 1.20215 14.3281 0.682617 13.6992 0.518555C12.5234 0.19043 7.875 0.19043 7.875 0.19043C7.875 0.19043 3.19922 0.19043 2.02344 0.518555C1.39453 0.682617 0.875 1.20215 0.710938 1.8584C0.382812 3.00684 0.382812 5.46777 0.382812 5.46777C0.382812 5.46777 0.382812 7.90137 0.710938 9.07715C0.875 9.7334 1.39453 10.2256 2.02344 10.3896C3.19922 10.6904 7.875 10.6904 7.875 10.6904C7.875 10.6904 12.5234 10.6904 13.6992 10.3896C14.3281 10.2256 14.8477 9.7334 15.0117 9.07715C15.3398 7.90137 15.3398 5.46777 15.3398 5.46777C15.3398 5.46777 15.3398 3.00684 15.0117 1.8584ZM6.34375 7.68262V3.25293L10.2266 5.46777L6.34375 7.68262Z"/>
              </svg>
            </a>
          </li>
          <li>
            <a href="https://pinterest.com" class="footer__social-link d-block" aria-label="Pinterest">
              <svg class="svg-icon svg-icon_pinterest" width="14" height="15" viewBox="0 0 14 15" xmlns="http://www.w3.org/2000/svg">
                <use href="#icon_pinterest" />
              </svg>
            </a>
          </li>
        </ul>
      </div>
      <!-- /.footer-column -->

      <!-- Company -->
      <div class="footer-column footer-menu mb-4 mb-lg-0">
        <h5 class="sub-menu__title text-uppercase">Company</h5>
        <ul class="sub-menu__list list-unstyled">
          <li class="sub-menu__item"><a href="<?= $BASE ?>about.php" class="menu-link menu-link_us-s">About Us</a></li>
          <li class="sub-menu__item"><a href="<?= $BASE ?>careers.php" class="menu-link menu-link_us-s">Careers</a></li>
          <li class="sub-menu__item"><a href="<?= $BASE ?>affiliates.php" class="menu-link menu-link_us-s">Affiliates</a></li>
          <li class="sub-menu__item"><a href="<?= $BASE ?>blog_list1.php" class="menu-link menu-link_us-s">Blog</a></li>
          <li class="sub-menu__item"><a href="<?= $BASE ?>contact.php" class="menu-link menu-link_us-s">Contact Us</a></li>
        </ul>
      </div>
      <!-- /.footer-column -->

      <!-- Shop (exactly 5 items) -->
      <div class="footer-column footer-menu mb-4 mb-lg-0">
        <h5 class="sub-menu__title text-uppercase">Shop</h5>
        <ul class="sub-menu__list list-unstyled">
          <li class="sub-menu__item">
            <a href="<?= $BASE ?>shop/shop.php?occasion=fitted&cur=NGN" class="menu-link menu-link_us-s">By Style</a>
          </li>
          <li class="sub-menu__item">
            <a href="<?= $BASE ?>shop/shop.php?occasion=casual&cur=NGN" class="menu-link menu-link_us-s">By occasion</a>
          </li>
          <li class="sub-menu__item">
            <a href="<?= $BASE ?>shop/shop.php?occasion=long&cur=NGN" class="menu-link menu-link_us-s">by length</a>
          </li>
          <li class="sub-menu__item">
            <a href="<?= $BASE ?>shop/shop.php<?= ($curParam ? '?' . ltrim($curParam, '&') : '') ?>" class="menu-link menu-link_us-s">Shop</a>
          </li>
          <li class="sub-menu__item">
            <a href="<?= $BASE ?>shop/shop.php<?= ($curParam ? '?' . ltrim($curParam, '&') : '') ?>" class="menu-link menu-link_us-s">VIew All</a>
          </li>
        </ul>
      </div>
      <!-- /.footer-column -->

      <!-- Help -->
      <div class="footer-column footer-menu mb-4 mb-lg-0">
        <h5 class="sub-menu__title text-uppercase">Help</h5>
        <ul class="sub-menu__list list-unstyled">
          <li class="sub-menu__item"><a href="<?= $BASE ?>customer_service.php" class="menu-link menu-link_us-s">Customer Service</a></li>
          <li class="sub-menu__item"><a href="<?= $BASE ?>account_dashboard.php" class="menu-link menu-link_us-s">My Account</a></li>
          <li class="sub-menu__item"><a href="<?= $BASE ?>store_location.php" class="menu-link menu-link_us-s">Find a Store</a></li>
          <li class="sub-menu__item"><a href="<?= $BASE ?>terms.php" class="menu-link menu-link_us-s">Legal &amp; Privacy</a></li>
          <li class="sub-menu__item"><a href="<?= $BASE ?>contact.php" class="menu-link menu-link_us-s">Contact</a></li>
          <li class="sub-menu__item"><a href="<?= $BASE ?>giftcard.php" class="menu-link menu-link_us-s">Gift Card</a></li>
        </ul>
      </div>
      <!-- /.footer-column -->

      <!-- Newsletter -->
      <div class="footer-column footer-newsletter col-12 mb-4 mb-lg-0">
        <h5 class="sub-menu__title text-uppercase">Subscribe</h5>
        <p>Be the first to get the latest news about trends, promotions, and much more!</p>

        <form
          action="<?= $esc(rtrim($BASE, '/').'/newsletter/subscribe.php') ?>"
          method="post"
          class="footer-newsletter__form position-relative bg-body js-newsletter-form"
        >
          <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= $esc(csrf_token()) ?>">
          <?php endif; ?>
          <input class="form-control border-white" type="email" name="email" placeholder="Your email address" required />
          <input class="btn-link fw-medium bg-white position-absolute top-0 end-0 h-100" type="submit" value="JOIN" />
        </form>
        <div class="small mt-2 js-newsletter-msg" role="status"></div>

        <div class="mt-4 pt-3">
          <strong class="fw-medium">Secure payments</strong>
          <p class="mt-2">
            <img loading="lazy" src="<?= $BASE ?>images/payment-options.png" alt="Acceptable payment gateways" class="mw-100" />
          </p>
        </div>
      </div>
      <!-- /.footer-column -->
    </div>
    <!-- /.row-cols-5 -->
  </div>
  <!-- /.footer-middle container -->

  <div class="footer-bottom container">
    <div class="d-block d-md-flex align-items-center">
      <span class="footer-copyright me-auto">©<?= date('Y') ?> jhema</span>
    </div>
    <!-- /.d-flex -->
  </div>
  <!-- /.footer-bottom container -->
</footer>
<!-- /.footer footer_type_1 -->

<!-- One universal newsletter handler (shared by footer + popup) -->
<script>
(function () {
  if (window.__newsletterBound) return;
  window.__newsletterBound = true;

  function findMsgEl(form) {
    let msg = form.parentElement?.querySelector('.js-newsletter-msg');
    if (!msg) msg = form.closest('.footer-newsletter, .block-newsletter, .modal-content')?.querySelector('.js-newsletter-msg');
    return msg || document.querySelector('.js-newsletter-msg');
  }
  function showMessage(msgEl, text, ok) {
    if (!msgEl) return;
    msgEl.textContent = text;
    msgEl.className = 'small mt-2 ' + (ok ? 'text-nude fw-bold' : 'text-danger');
  }
  async function submitForm(form) {
    const msgEl = findMsgEl(form);
    const url = form.getAttribute('action') || '/newsletter/subscribe.php';
    const fd = new FormData(form);
    try {
      const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
      const ct = (res.headers.get('content-type') || '').toLowerCase();
      if (!ct.includes('application/json')) {
        const txt = await res.text();
        console.warn('Non-JSON response:', txt);
        showMessage(msgEl, 'Server returned non-JSON. Check PHP error logs.', false);
        return;
      }
      const data = await res.json();
      showMessage(msgEl, data.msg || (data.ok ? 'Subscribed!' : 'Could not subscribe.'), !!data.ok);
      if (data.ok) {
        form.reset();
        const popup = form.closest('#newsletterPopup');
        if (popup && window.bootstrap) {
          setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(popup) || new bootstrap.Modal(popup);
            modal.hide();
          }, 1200);
        }
      }
    } catch (err) {
      console.error(err);
      showMessage(msgEl, 'Network error. Try again.', false);
    }
  }
  document.addEventListener('submit', function (e) {
    const form = e.target.closest('.js-newsletter-form');
    if (!form) return;
    e.preventDefault();
    submitForm(form);
  });
})();
</script>

<style>
/* Nude theme success color to match brand */
.text-nude { color: #c19a6b !important; }
</style>
