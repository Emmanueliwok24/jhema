<!-- Cart Drawer -->
<div class="aside aside_right overflow-hidden cart-drawer" id="cartDrawer">
  <div class="aside-header d-flex align-items-center">
    <h3 class="text-uppercase fs-6 mb-0">
      SHOPPING BAG (<span class="cart-amount js-cart-items-count">0</span>)
    </h3>
    <button class="btn-close-lg js-close-aside btn-close-aside ms-auto"></button>
  </div>

  <div class="aside-content cart-drawer-items-list">
    <div class="js-cart-list"></div>

    <!-- Empty state -->
    <div class="js-cart-empty d-none">
      <div class="text-center text-secondary py-4">Your bag is empty.</div>
    </div>

    <!-- Logged-out state (only shown if API tells us) -->
    <div class="js-cart-login d-none">
      <div class="text-center py-4">
        <div class="mb-2">Please log in to view your cart.</div>
        <a class="btn btn-primary btn-sm js-login-link" href="account/auth.php">Login</a>
      </div>
    </div>

    <hr class="cart-drawer-divider js-divider d-none" />
  </div>

  <div class="cart-drawer-actions position-absolute start-0 bottom-0 w-100">
    <hr class="cart-drawer-divider" />
    <div class="d-flex justify-content-between">
      <h6 class="fs-base fw-medium">SUBTOTAL:</h6>
      <span class="cart-subtotal fw-medium">₦0.00</span>
    </div>
    <!-- Only send users to the Cart page (no direct checkout here) -->
    <a href="<?= BASE_URL ?>shop/shop_cart.php" class="btn btn-primary mt-3 d-block">View Cart</a>
  </div>
</div>

<!-- Template -->
<template id="tpl-cart-item">
  <div class="cart-drawer-item d-flex position-relative" data-pid="">
    <div class="position-relative">
      <a class="js-item-link" href="#">
        <img loading="lazy" class="cart-drawer-item__img js-item-img" src="" alt=""/>
      </a>
    </div>
    <div class="cart-drawer-item__info flex-grow-1">
      <h6 class="cart-drawer-item__title fw-normal">
        <a class="js-item-link js-item-name" href="#"></a>
      </h6>

      <!-- Simple row under title: Quantity (static) and Line Subtotal -->
      <div class="d-flex align-items-center justify-content-between mt-1">
        <span class="text-muted small">
          Qty: <strong class="js-qty-static">1</strong>
        </span>
        <span class="cart-drawer-item__price money price js-line-price">₦0.00</span>
      </div>
    </div>

    <!-- Keep remove button -->
    <button class="btn-close-xs position-absolute top-0 end-0 js-cart-item-remove" title="Remove"></button>
  </div>
  <hr class="cart-drawer-divider js-item-divider"/>
</template>

<script>
/** =========================
 *  Unified Cart Drawer Logic (no qty controls, no direct checkout)
 * ========================= */
(function(){
  if (window.__CART_DRAWER_BOUND__) return;
  window.__CART_DRAWER_BOUND__ = true;

  window.BASE_URL = <?= json_encode(rtrim(BASE_URL, '/') . '/') ?>;

  // ------- utils -------
  const money = (n)=> '₦' + Number(n || 0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2});
  const productUrl = (slug)=> BASE_URL + 'shop/product-details.php?slug=' + encodeURIComponent(slug || '');

  // normalize cart payload (works for /api/cart/get.php and /api/cart/add.php responses)
  function normalizeCartPayload(j){
    // login prompt response shape (from add.php if not logged in)
    if (j && j.login === true) {
      return {
        logged_in: false,
        loginUrl: j.loginUrl || (BASE_URL + 'account/auth.php?tab=login'),
        items: [],
        subtotal: 0,
        count: 0
      };
    }

    // standard get.php -> {success, cart:{items, subtotal, weight_kg, count}}
    const cart  = j?.cart || j || { items:[], subtotal:0, count:0 };
    const items = Array.isArray(cart.items) ? cart.items : [];

    const mapped = items.map(it => {
      // Use DIRECT image field preference (no rewriting):
      // 1) image_url (absolute)  2) featured_image  3) image  4) image_path
      const directImg = it.image_url || it.featured_image || it.image || it.image_path || '';

      return {
        product_id: it.product_id,
        quantity: it.quantity,
        name: it.name,
        slug: it.slug,
        url: productUrl(it.slug),
        img: directImg, // direct path as requested
        line_subtotal: it.line_subtotal
      };
    });

    return {
      logged_in: true,
      items: mapped,
      subtotal: cart.subtotal || 0,
      count: cart.count || 0
    };
  }

  async function fetchCart(){
    const r = await fetch(BASE_URL + 'api/cart/get.php', {credentials:'same-origin'});
    return normalizeCartPayload(await r.json());
  }

  function renderCartDrawer(state){
    const list     = document.querySelector('.js-cart-list');
    const countEls = document.querySelectorAll('.js-cart-items-count');
    const subEl    = document.querySelector('.cart-subtotal');
    const emptyEl  = document.querySelector('.js-cart-empty');
    const loginEl  = document.querySelector('.js-cart-login');
    const divider  = document.querySelector('.js-divider');
    const loginLink= document.querySelector('.js-login-link');
    const tpl      = document.getElementById('tpl-cart-item');

    list.innerHTML = '';
    divider?.classList.add('d-none');
    emptyEl?.classList.add('d-none');
    loginEl?.classList.add('d-none');

    if (state.logged_in === false) {
      countEls.forEach(el => el.textContent = '0');
      if (subEl) subEl.textContent = money(0);
      loginEl?.classList.remove('d-none');
      if (loginLink && state.loginUrl) loginLink.href = state.loginUrl;
      return;
    }

    const items = state.items || [];
    if (!items.length) {
      countEls.forEach(el => el.textContent = '0');
      if (subEl) subEl.textContent = money(0);
      emptyEl?.classList.remove('d-none');
      return;
    }

    items.forEach(it => {
      const node = tpl.content.cloneNode(true);
      node.querySelector('.cart-drawer-item').dataset.pid = it.product_id;
      node.querySelectorAll('.js-item-link').forEach(a => a.href = it.url);

      // direct image path (no transformation)
      const $img = node.querySelector('.js-item-img');
      $img.src = it.img || (BASE_URL + 'images/placeholder.png');
      $img.alt = it.name || '';

      node.querySelector('.js-item-name').textContent = it.name || '';
      node.querySelector('.js-qty-static').textContent = String(it.quantity || 1);
      node.querySelector('.js-line-price').textContent = money(it.line_subtotal);
      list.appendChild(node);
    });

    divider?.classList.remove('d-none');
    countEls.forEach(el => el.textContent = String(state.count || 0));
    if (subEl) subEl.textContent = money(state.subtotal || 0);
  }

  // public helper for pages: refresh drawer now
  window.refreshCartDrawer = async function(payloadFromAddOrGet){
    const state = payloadFromAddOrGet ? normalizeCartPayload(payloadFromAddOrGet) : await fetchCart();
    renderCartDrawer(state);
  };

  async function removeLine(pid){
    const fd = new FormData();
    fd.append('product_id', pid);
    const r = await fetch(BASE_URL + 'api/cart/remove.php', { method:'POST', body:fd, credentials:'same-origin' });
    const j = await r.json();
    if (!j.success) throw new Error(j.message || 'Remove failed');
    await refreshCartDrawer(j);
  }

  // Event delegation (document-level)
  document.addEventListener('click', (e) => {
    // Open drawer triggers -> refresh
    const openBtn = e.target.closest('.js-open-aside');
    if (openBtn && openBtn.dataset.aside === 'cartDrawer') {
      setTimeout(()=> refreshCartDrawer().catch(console.error), 10);
      return;
    }

    // Drawer remove only (no +/- handlers anymore)
    const rm = e.target.closest('.js-cart-item-remove');
    if (rm) {
      const item = rm.closest('.cart-drawer-item');
      if (!item) return;
      const pid = item.dataset.pid;
      removeLine(pid).catch(err => alert(err.message || 'Remove failed'));
    }
  });

  // Initial load (optional)
  document.addEventListener('DOMContentLoaded', () => {
    refreshCartDrawer().catch(console.error);
  });

  // Guard against any global theme handlers that might try to +/- quantities in the drawer
  // (Not strictly necessary now that we don't render those controls, but harmless.)
  window.addEventListener('click', function(e){
    const isDrawer = !!(e.target.closest && e.target.closest('.cart-drawer'));
    if (!isDrawer) return;
    if (e.target.closest('.qty-control__increase, .qty-control__reduce')) {
      e.preventDefault();
      e.stopImmediatePropagation();
      e.stopPropagation();
    }
  }, true);
})();
</script>
