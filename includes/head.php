<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html dir="ltr" lang="zxx">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="flexkit">
    <meta name="description" content="Jhema eCommerce || Elevated Elegance">

    <!-- Favicon -->
    <link rel="icon" href="<?= BASE_URL ?>images/favicon.png" type="image/x-icon">
    <link rel="shortcut icon" href="<?= BASE_URL ?>images/favicon.png" type="image/x-icon">

    <!-- Document Title -->
    <title>Home | Jhema eCommerce || Elevated Elegance</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Allura&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Plugins CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/plugins/swiper.min.css" type="text/css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/plugins/jquery.fancybox.css" type="text/css">

    <!-- Main Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css" type="text/css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/products-styles.css" type="text/css">
    <!-- meta theme -->
    <meta name="theme-color" content="#ff0000">

    <script>
// QTY GUARD — add this in head.php, BEFORE any theme scripts if possible
(function () {
  if (window.__QTY_GUARD__) return; window.__QTY_GUARD__ = true;

  // Helper: find the numeric input next to the +/- buttons
  function findQtyInput(btn) {
    const wrap = btn.closest('.qty-control');
    return wrap ? wrap.querySelector('.js-qty, input[name="quantity"]') : null;
  }

  // Capture-phase click handler: consumes clicks on +/- before theme code sees them
  window.addEventListener('click', function qtyGuardCapture(e) {
    const btn = e.target.closest && e.target.closest('.qty-control__increase, .qty-control__reduce');
    if (!btn) return;

    const input = findQtyInput(btn);
    if (!input) return;

    // Compute next value (single step)
    const curr = parseInt(input.value || '1', 10) || 1;
    const next = btn.classList.contains('qty-control__reduce') ? Math.max(1, curr - 1) : curr + 1;

    // Update UI immediately
    input.value = next;

    // If you are on the cart page (row has data-pid), trigger your server sync
    const tr = btn.closest('tr');
    if (tr && tr.dataset && tr.dataset.pid) {
      // mirror your updateRowQuantity() without needing other scripts
      const fd = new FormData();
      fd.append('product_id', tr.dataset.pid);
      fd.append('quantity', next);

      fetch(window.BASE_URL + 'api/cart/update.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(j => {
          if (!j.success) throw new Error(j.message || 'Update failed');

          // Update line subtotal
          const updated = j.updated || null;
          const cell = tr.querySelector('.js-line-subtotal');
          if (updated && cell) {
            cell.textContent = '₦' + Number(updated.line_subtotal).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          }

          // Update totals
          const cart = j.cart || {};
          const subtotal = cart.subtotal || 0;
          const weight   = cart.weight_kg || 0;
          const $subtotal = document.getElementById('cart-subtotal');
          const $weight   = document.getElementById('cart-weight');
          if ($subtotal) $subtotal.textContent = '₦' + Number(subtotal).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          if ($weight)   $weight.textContent   = Number(weight).toFixed(2) + ' kg';
        })
        .catch(err => alert(err.message));
    }

    // IMPORTANT: stop everyone else (theme/global handlers) from also incrementing
    e.preventDefault();
    e.stopImmediatePropagation();
    e.stopPropagation();
  }, /* capture */ true);
})();
</script>

</head>
<body>
