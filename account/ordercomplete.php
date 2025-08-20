<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_user.php';
require_user();

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status  = $_GET['status'] ?? '';
$success = !empty($_GET['success']);

if ($orderId <= 0) {
  // fallback: latest paid order for user
  $st = $pdo->prepare("SELECT id FROM orders WHERE user_id=? AND status='paid' ORDER BY id DESC LIMIT 1");
  $st->execute([(int)$_SESSION['user_id']]);
  $orderId = (int)$st->fetchColumn();
}

$order = null;
$items = [];
if ($orderId > 0) {
  $st = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1");
  $st->execute([$orderId, (int)$_SESSION['user_id']]);
  $order = $st->fetch(PDO::FETCH_ASSOC);

  if ($order) {
    // Use * to tolerate different schemas (e.g., variant_label column)
    $it = $pdo->prepare("SELECT * FROM order_items WHERE order_id=? ORDER BY id ASC");
    $it->execute([$orderId]);
    $items = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
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
    <h2 class="page-title">Order Received</h2>

    <div class="checkout-steps">
      <a href="<?= BASE_URL ?>shop/shop_cart.php" class="checkout-steps__item active">
        <span class="checkout-steps__item-number">01</span>
        <span class="checkout-steps__item-title"><span>Shopping Bag</span><em>Manage Your Items List</em></span>
      </a>
      <a href="<?= BASE_URL ?>shop/shop_checkout.php" class="checkout-steps__item active">
        <span class="checkout-steps__item-number">02</span>
        <span class="checkout-steps__item-title"><span>Shipping and Checkout</span><em>Checkout Your Items List</em></span>
      </a>
      <span class="checkout-steps__item active">
        <span class="checkout-steps__item-number">03</span>
        <span class="checkout-steps__item-title"><span>Confirmation</span><em>Review And Submit Your Order</em></span>
      </span>
    </div>

    <?php if (empty($order)): ?>
      <div class="alert alert-warning">We could not find your order.</div>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>shop/shop.php">Back to Shop</a>
    <?php else: ?>
    <div class="order-complete">
      <div class="order-complete__message">
        <?php if ($success): ?>
          <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="40" r="40" fill="#B9A16B"></circle><path d="M52.9743 35.7612C52.9743 35.3426 52.8069 34.9241 52.5056 34.6228L50.2288 32.346C49.9275 32.0446 49.5089 31.8772 49.0904 31.8772C48.6719 31.8772 48.2533 32.0446 47.952 32.346L36.9699 43.3449L32.048 38.4062C31.7467 38.1049 31.3281 37.9375 30.9096 37.9375C30.4911 37.9375 30.0725 38.1049 29.7712 38.4062L27.4944 40.683C27.1931 40.9844 27.0257 41.4029 27.0257 41.8214C27.0257 42.24 27.1931 42.6585 27.4944 42.9598L33.5547 49.0201L35.8315 51.2969C36.1328 51.5982 36.5513 51.7656 36.9699 51.7656C37.3884 51.7656 37.8069 51.5982 38.1083 51.2969L40.385 49.0201L52.5056 36.8996C52.8069 36.5982 52.9743 36.1797 52.9743 35.7612Z" fill="white"></path></svg>
          <h3>Your order is completed!</h3>
          <p>Thank you. Your order has been received.</p>
        <?php else: ?>
          <h3>Order status: <?= htmlspecialchars($order['status']) ?></h3>
          <?php if ($status==='failed'): ?><p class="text-danger">Payment failed.</p><?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="order-info">
        <div class="order-info__item"><label>Order Number</label><span>#<?= (int)$order['id'] ?></span></div>
        <div class="order-info__item"><label>Date</label><span><?= htmlspecialchars($order['created_at']) ?></span></div>
        <div class="order-info__item"><label>Total</label><span>₦<?= number_format((float)$order['total'],2) ?></span></div>
        <div class="order-info__item"><label>Payment Method</label><span>Flutterwave</span></div>
      </div>

      <div class="checkout__totals-wrapper">
        <div class="checkout__totals">
          <h3>Order Details</h3>
          <table class="checkout-cart-items">
            <thead><tr><th>PRODUCT</th><th>SUBTOTAL</th></tr></thead>
            <tbody>
            <?php foreach ($items as $row): ?>
              <?php
                $name = (string)($row['name'] ?? '');
                $qty  = (int)($row['quantity'] ?? 0);
                $sub  = (float)($row['subtotal'] ?? 0);
                $vLab = trim((string)($row['variant_label'] ?? ''));
                $displayName = $name . ($vLab !== '' ? " ({$vLab})" : '');
              ?>
              <tr>
                <td><?= htmlspecialchars($displayName) ?> × <?= $qty ?></td>
                <td>₦<?= number_format($sub,2) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <table class="checkout-totals">
            <tbody>
              <tr><th>SUBTOTAL</th><td>₦<?= number_format((float)$order['subtotal'],2) ?></td></tr>
              <tr><th>SHIPPING</th><td>₦<?= number_format((float)$order['shipping'],2) ?> (<?= $order['country_code']==='NG'?'₦1,500':'₦5,000' ?>/kg)</td></tr>
              <tr><th>TOTAL</th><td>₦<?= number_format((float)$order['total'],2) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <a class="btn btn-primary btn-checkout" href="<?= BASE_URL ?>account/ordertracking.php">Track Order</a>
      <a class="btn btn-outline-secondary ms-2" href="<?= BASE_URL ?>account/dashboard.php">Go to Dashboard</a>
    </div>
    <?php endif; ?>
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
