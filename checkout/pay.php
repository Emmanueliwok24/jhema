<?php
// checkout/pay.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/payment_config.php';

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
$ctx = $_SESSION['pay_ctx'] ?? null;
if (!$ctx) { header('Location: ' . BASE_URL . 'shop/shop_cart.php'); exit; }

$txRef  = $ctx['tx_ref'];
$amount = (float)$ctx['amount'];
$email  = $ctx['email'];
$name   = $ctx['name'];
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Processing Payment…</title></head>
<body>
  <p style="font-family: system-ui, sans-serif">Launching payment…</p>
  <script src="https://checkout.flutterwave.com/v3.js"></script>
  <script>
  FlutterwaveCheckout({
    public_key: "<?= FLW_PUBLIC_KEY ?>",
    tx_ref: "<?= htmlspecialchars($txRef) ?>",
    amount: <?= json_encode($amount) ?>,
    currency: "<?= FLW_CURRENCY ?>",
    redirect_url: "<?= FLW_CALLBACK_URL ?>",
    customer: { email: <?= json_encode($email) ?>, name: <?= json_encode($name) ?> },
    customizations: { title: "JHEMA", description: "Order Payment", logo: "<?= BASE_URL ?>images/logo.svg" }
  });
  </script>
</body>
</html>
