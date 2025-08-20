<?php
// shop/shop_cart.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';   // product_image_url()
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_user.php';         // is_logged_in / base_url()
require_once __DIR__ . '/../includes/cart.php';

/* --- FIX: don't wrap require_user() in a condition --- */
require_user();

$userId = (int)($_SESSION['user_id'] ?? 0);
[$items, $subtotal, $weight, $count] = cart_totals($pdo, $userId);

// Minimal number string (no forced decimals)
function min_num_str($n): string {
  $s = rtrim(rtrim(number_format((float)$n, 6, '.', ''), '0'), '.');
  return ($s === '') ? '0' : $s;
}

include("../includes/head.php");
include("../includes/svg.php");
include("../includes/mobile-header.php");
include("../includes/header.php");
?>
<main class="position-relative">
  <?php include("../scroll_categories.php"); ?>
  <div class="mb-4 pb-4"></div>

  <section class="shop-checkout container">
    <h2 class="page-title">Cart</h2>

    <div class="checkout-steps">
      <span class="checkout-steps__item active">
        <span class="checkout-steps__item-number">01</span>
        <span class="checkout-steps__item-title"><span>Shopping Bag</span><em>Manage Your Items List</em></span>
      </span>
      <a href="<?= BASE_URL ?>shop/shop_checkout.php" class="checkout-steps__item">
        <span class="checkout-steps__item-number">02</span>
        <span class="checkout-steps__item-title"><span>Shipping and Checkout</span><em>Checkout Your Items List</em></span>
      </a>
      <span class="checkout-steps__item">
        <span class="checkout-steps__item-number">03</span>
        <span class="checkout-steps__item-title"><span>Confirmation</span><em>Review And Submit Your Order</em></span>
      </span>
    </div>

    <div class="shopping-cart">
      <div class="cart-table__wrapper">
        <?php if (!$items): ?>
          <div class="alert alert-info">Your cart is empty.</div>
          <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>shop/shop.php">Continue Shopping</a>
        <?php else: ?>
        <table class="cart-table" id="cart-table">
          <thead>
            <tr>
              <th>Product</th>
              <th></th>
              <th>Price</th>
              <th>Quantity</th>
              <th>Subtotal</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <?php
              $pid   = (int)$it['product_id'];
              $qty   = (int)$it['quantity'];
              $price = (float)$it['base_price'];
              $line  = (float)$it['line_subtotal'];
              // Keep using product_image_url() here (works with absolute/relative)
              $img   = product_image_url($it['image_path'] ?? null);
            ?>
            <tr data-pid="<?= $pid ?>">
              <td>
                <div class="shopping-cart__product-item">
                  <a href="<?= BASE_URL ?>shop/product-details.php?slug=<?= urlencode($it['slug']) ?>">
                    <?php if ($img): ?>
                      <img loading="lazy" src="<?= htmlspecialchars($img) ?>" width="120" height="120" alt="<?= htmlspecialchars($it['name']) ?>">
                    <?php endif; ?>
                  </a>
                </div>
              </td>
              <td>
                <div class="shopping-cart__product-item__detail">
                  <h4 class="mb-1">
                    <a href="<?= BASE_URL ?>shop/product-details.php?slug=<?= urlencode($it['slug']) ?>">
                      <?= htmlspecialchars($it['name']) ?>
                    </a>
                  </h4>
                  <?php if (!empty($it['weight_kg_text'])): ?>
                    <div class="text-muted small">Weight: <?= htmlspecialchars($it['weight_kg_text']) ?> kg (per unit)</div>
                  <?php endif; ?>
                  <?php if (!empty($it['variant_label'])): ?>
                    <div class="text-muted small"><?= htmlspecialchars($it['variant_label']) ?></div>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <span class="shopping-cart__product-price" data-price="<?= number_format($price, 2, '.', '') ?>">
                  ₦<?= number_format($price,2) ?>
                </span>
              </td>
              <td>
                <div class="qty-control position-relative d-inline-flex align-items-center">
                  <div class="qty-control__reduce px-2" role="button" aria-label="Decrease quantity">-</div>
                  <input type="number" name="quantity" value="<?= $qty ?>" min="1" step="1"
                         class="qty-control__number text-center js-qty mx-1" style="width:72px" />
                  <div class="qty-control__increase px-2" role="button" aria-label="Increase quantity">+</div>
                </div>
              </td>
              <td>
                <span class="shopping-cart__subtotal js-line-subtotal">₦<?= number_format($line,2) ?></span>
              </td>
              <td class="text-end">
                <a href="#" class="remove-cart js-remove btn btn-sm btn-outline-danger" title="Remove">Remove</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="cart-table-footer d-flex justify-content-between align-items-center mt-3">
          <a href="<?= BASE_URL ?>shop/shop.php" class="btn btn-light">CONTINUE SHOPPING</a>
          <a href="<?= BASE_URL ?>shop/shop_checkout.php" class="btn btn-primary">PROCEED TO CHECKOUT</a>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($items): ?>
      <div class="shopping-cart__totals-wrapper">
        <div class="sticky-content">
          <div class="shopping-cart__totals">
            <h3>Cart Totals</h3>
            <table class="cart-totals">
              <tbody>
                <tr>
                  <th>Subtotal</th>
                  <td id="cart-subtotal">₦<?= number_format($subtotal,2) ?></td>
                </tr>
                <tr>
                  <th>Estimated Weight</th>
                  <td id="cart-weight"><?= htmlspecialchars(min_num_str($weight)) ?> kg</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="mobile_fixed-btn_wrapper">
            <div class="button-wrapper container">
              <a class="btn btn-primary btn-checkout w-100" href="<?= BASE_URL ?>shop/shop_checkout.php">PROCEED TO CHECKOUT</a>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<div class="mb-5 pb-xl-5"></div>
<?php include("../includes/footer.php"); ?>
<?php include("../includes/mobile-footer.php"); ?>
<?php include("../includes/aside-form.php"); ?>
<?php include("../includes/cart-aside.php"); ?>
<?php include("../includes/sitemap-nav.php"); ?>
<?php include("../includes/scroll.php"); ?>
<?php include("../includes/script-footer.php"); ?>

<script>
(function(){
  if (window.__SHOP_CART_BOUND__) return;
  window.__SHOP_CART_BOUND__ = true;

  const BASE_URL = <?= json_encode(rtrim(BASE_URL, '/') . '/') ?>;
  const money = (n)=> '₦' + Number(n).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2});
  const minNumStr = (n)=> (Number(n)||0).toFixed(6).replace(/\.?0+$/,''); // no forced decimals
  const cartContainer = document.querySelector('.shopping-cart');

  // Row state
  function state(tr){ if (!tr.__st){tr.__st={updating:false,timer:null,lastQty:null};} return tr.__st; }

  function schedule(tr, qty){
    const st = state(tr);
    qty = Math.max(1, parseInt(qty||'1',10));
    st.lastQty = qty;
    if (st.timer) clearTimeout(st.timer);
    st.timer = setTimeout(()=>{ st.timer=null; updateRow(tr, st.lastQty); }, 120);
  }

  function updateRow(tr, qty){
    const st = state(tr);
    if (st.updating) return;
    st.updating = true;

    const pid = tr.dataset.pid;
    const fd  = new FormData();
    fd.append('product_id', pid);
    fd.append('quantity', Math.max(1, parseInt(qty||'1',10)));

    fetch(BASE_URL + 'api/cart/update.php', { method:'POST', body:fd, credentials:'same-origin' })
      .then(r=>r.json())
      .then(j=>{
        if (!j.success) throw new Error(j.message||'Update failed');

        const input = tr.querySelector('.js-qty');
        const srvQty = j.updated && typeof j.updated.quantity!=='undefined' ? parseInt(j.updated.quantity,10) : qty;
        if (input) input.value = srvQty;

        const cell = tr.querySelector('.js-line-subtotal');
        if (j.updated && cell) {
          cell.textContent = money(j.updated.line_subtotal);
        } else if (cell) {
          const unit = parseFloat(tr.querySelector('[data-price]')?.dataset.price || '0');
          cell.textContent = money(unit * srvQty);
        }

        const cart = j.cart || {};
        const $subtotal = document.getElementById('cart-subtotal');
        const $weight   = document.getElementById('cart-weight');
        if ($subtotal) $subtotal.textContent = money(cart.subtotal || 0);
        if ($weight)   $weight.textContent   = minNumStr(cart.weight_kg || 0) + ' kg';
      })
      .catch(err=>alert(err.message))
      .finally(()=>{ st.updating=false; });
  }

  cartContainer?.addEventListener('click', (e)=>{
    const minus = e.target.closest('.qty-control__reduce');
    const plus  = e.target.closest('.qty-control__increase');
    const rm    = e.target.closest('.js-remove');

    if (minus || plus) {
      e.preventDefault(); e.stopImmediatePropagation(); e.stopPropagation();
      const tr    = (minus||plus).closest('tr');
      const input = tr?.querySelector('.js-qty');
      if (!tr || !input) return;
      const curr = parseInt(input.value||'1',10);
      const next = minus ? Math.max(1, curr-1) : curr+1;
      input.value = next;
      schedule(tr, next);
      return;
    }

    if (rm) {
      e.preventDefault();
      const tr  = rm.closest('tr');
      const fd  = new FormData();
      fd.append('product_id', tr.dataset.pid);
      fetch(BASE_URL + 'api/cart/remove.php', { method:'POST', body:fd, credentials:'same-origin' })
        .then(r=>r.json())
        .then(j=>{
          if (!j.success) throw new Error(j.message||'Remove failed');
          tr.remove();

          const $subtotal = document.getElementById('cart-subtotal');
          const $weight   = document.getElementById('cart-weight');
          if ($subtotal) $subtotal.textContent = money((j.cart||{}).subtotal||0);
          if ($weight)   $weight.textContent   = minNumStr((j.cart||{}).weight_kg||0) + ' kg';

          if (((j.cart||{}).items||[]).length === 0) {
            const tblWrap = document.querySelector('.cart-table__wrapper');
            if (tblWrap) {
              tblWrap.innerHTML = '<div class="alert alert-info">Your cart is empty.</div>' +
                '<a class="btn btn-outline-secondary" href="'+BASE_URL+'shop/shop.php">Continue Shopping</a>';
            }
            document.querySelector('.shopping-cart__totals-wrapper')?.remove();
          }
        })
        .catch(err=>alert(err.message));
    }
  });

  cartContainer?.addEventListener('change', (e)=>{
    const input = e.target.closest('.js-qty');
    if (!input) return;
    const tr  = input.closest('tr');
    let qty = Math.max(1, parseInt(input.value||'1',10));
    input.value = qty;
    schedule(tr, qty);
  });

  // Prevent accidental mousewheel qty changes
  cartContainer?.addEventListener('wheel', (e)=>{
    const input = e.target.closest && e.target.closest('.js-qty');
    if (input === e.target) input.blur();
  }, {passive:true});
})();
</script>
