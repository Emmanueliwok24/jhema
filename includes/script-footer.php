<?php require_once __DIR__ . '/../includes/config.php'; ?>

    <!-- External JavaScripts -->
    <script src="<?= BASE_URL ?>js/plugins/jquery.min.js"></script>
    <script src="<?= BASE_URL ?>js/plugins/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>js/plugins/bootstrap-slider.min.js"></script>
    <!-- <script src="<?= BASE_URL ?>js/plugins/jquery.waypoints.min.js"></script> -->
    <!-- <script src="<?= BASE_URL ?>js/plugins/sticky.min.js"></script> -->
    <script src="<?= BASE_URL ?>js/plugins/swiper.min.js"></script>
    <script src="<?= BASE_URL ?>js/plugins/countdown.js"></script>
    <script src="<?= BASE_URL ?>js/plugins/jquery.fancybox.js"></script>

    <!-- Footer Scripts -->
    <script src="<?= BASE_URL ?>js/theme.js"></script>
    <script src="<?= BASE_URL ?>js/script.js" defer></script>

    <script>
document.addEventListener('DOMContentLoaded', () => {
  const BASE_URL  = <?= json_encode(BASE_URL) ?>;   // ends with '/'
  const IS_LOGGED = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

  const qs  = (sel, ctx=document) => ctx.querySelector(sel);
  const qsa = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

  function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.classList.add('show');
      setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
      }, 2500);
    }, 25);
  }

  function setHeartState(btn, active) {
    const svg = btn.querySelector('svg') || btn;
    svg.classList.toggle('is-in-wishlist', !!active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    btn.setAttribute('title', active ? 'Remove from wishlist' : 'Add to wishlist');
  }

  function setBadges(count) {
    qsa('.wishlist-amount, .wishlist-counter').forEach(badge => {
      badge.textContent = String(Math.max(0, count|0));
    });
  }

  // Delegated click handler for all .js-add-wishlist buttons
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-add-wishlist');
    if (!btn) return;

    e.preventDefault();
    if (btn.dataset.loading === '1') return; // prevent double clicks
    btn.dataset.loading = '1';

    const productKey = btn.getAttribute('data-product');
    if (!productKey) {
      btn.dataset.loading = '0';
      showToast('Missing product identifier.', 'error');
      return;
    }

    fetch(BASE_URL + 'includes/ajax_wishlist.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'add', product: productKey }).toString()
    })
    .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
    .then(data => {
      if (data && data.login_required) {
        const redirectTo = window.location.href;
        window.location.href = BASE_URL + 'account/login.php?redirect=' + encodeURIComponent(redirectTo);
        return;
      }
      if (!data || !data.success) {
        showToast((data && data.message) || 'Error updating wishlist', 'error');
        return;
      }

      const nowInWishlist = !!data.in_wishlist;
      setHeartState(btn, nowInWishlist);

      if (typeof data.count === 'number') setBadges(data.count);

      showToast(nowInWishlist ? 'Added to wishlist' : 'Removed from wishlist', 'success');
    })
    .catch(err => {
      console.error(err);
      showToast('Failed to update wishlist', 'error');
    })
    .finally(() => { btn.dataset.loading = '0'; });
  });

  // Paint initial heart states for logged-in users
  function initHearts() {
    const buttons = qsa('.js-add-wishlist');
    if (!IS_LOGGED || !buttons.length) return;

    fetch(BASE_URL + 'includes/ajax_wishlist.php?action=get_items')
      .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
      .then(data => {
        if (!data || !data.success || !Array.isArray(data.items)) return;
        const have = new Set(data.items);
        buttons.forEach(btn => {
          const key = btn.getAttribute('data-product');
          if (key && have.has(key)) setHeartState(btn, true);
        });
        if (typeof data.count === 'number') setBadges(data.count);
      })
      .catch(err => console.error('Wishlist init failed:', err));
  }

  initHearts();
});
</script>

<style>
/* Color the heart when active (works with <use> icons using currentColor) */
svg.is-in-wishlist { color: #e02424; }

/* Minimal toast styling if your theme doesn't provide one */
.toast {
  position: fixed; left: 50%; bottom: 20px; transform: translateX(-50%);
  background: rgba(0,0,0,.85); color: #fff; padding: 10px 14px; border-radius: 6px;
  opacity: 0; transition: opacity .2s, transform .2s; z-index: 99999;
}
.toast.show { opacity: 1; transform: translate(-50%, -4px); }
.toast-success { background: #16a34a; }
.toast-error   { background: #dc2626; }
</style>

  </body>
</html>
