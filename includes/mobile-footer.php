<?php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
require_once __DIR__ . '/../includes/config.php';
?>
<!-- Mobile Fixed Footer -->
<footer class="footer-mobile container w-100 px-5 d-md-none bg-body">
  <div class="row text-center">

    <!-- Home -->
    <div class="col-4">
      <a href="<?= htmlspecialchars(BASE_URL . 'index.php') ?>"
         class="footer-mobile__link d-flex flex-column align-items-center" aria-label="Home">
        <svg class="d-block" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
          <use href="#icon_home"></use>
        </svg>
        <span>Home</span>
      </a>
    </div>
    <!-- /.col-4 -->

    <!-- Shop -->
    <div class="col-4">
      <a href="<?= htmlspecialchars(BASE_URL . 'shop/shop.php') ?>"
         class="footer-mobile__link d-flex flex-column align-items-center" aria-label="Shop">
        <svg class="d-block" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
          <use href="#icon_hanger"></use>
        </svg>
        <span>Shop</span>
      </a>
    </div>
    <!-- /.col-4 -->

    <!-- Wishlist -->
    <div class="col-4">
      <a href="<?= htmlspecialchars(BASE_URL . 'account/account_wishlist.php') ?>"
         class="footer-mobile__link d-flex flex-column align-items-center position-relative" aria-label="Wishlist">
        <div class="position-relative">
          <svg class="d-block" width="18" height="18" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <use href="#icon_heart"></use>
          </svg>
          <!-- Dynamic wishlist count (updated by includes/wishlist.php JS) -->
          <span
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
            style="min-width:16px;height:16px;line-height:16px;font-size:10px;"
            data-wishlist-count
            <?php
              // Hide when zero so it doesn't show an empty dot
              $__wlc = isset($_SESSION['wishlist_count']) ? (int)$_SESSION['wishlist_count'] : 0;
              echo $__wlc ? '' : 'hidden';
            ?>
          ><?= (int)$__wlc ?></span>
        </div>
        <span>Wishlist</span>
      </a>
    </div>
    <!-- /.col-4 -->

  </div>
  <!-- /.row -->
</footer>
<!-- /.footer-mobile -->
