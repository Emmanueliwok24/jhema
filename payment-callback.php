<?php
// payment-callback.php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/payment_config.php';

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

$tx_ref = $_GET['tx_ref'] ?? '';
$flw_id = isset($_GET['transaction_id']) ? (int)$_GET['transaction_id'] : 0;

if ($tx_ref === '' || $flw_id <= 0) {
  header('Location: ' . BASE_URL . 'account/ordercomplete.php?status=failed'); exit;
}

$ch = curl_init('https://api.flutterwave.com/v3/transactions/' . $flw_id . '/verify');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . FLW_SECRET_KEY],
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$verified = false; $amount = 0.0;
if ($http === 200 && $resp) {
  $data = json_decode($resp, true);
  if (($data['status'] ?? '') === 'success' && ($data['data']['status'] ?? '') === 'successful') {
    $verified = true;
    $amount = (float)($data['data']['amount'] ?? 0);
  }
}

$st = $pdo->prepare("SELECT id,total,user_id FROM orders WHERE flw_tx_ref=? LIMIT 1");
$st->execute([$tx_ref]);
$order = $st->fetch(PDO::FETCH_ASSOC);

if ($order) {
  if ($verified && abs((float)$order['total'] - $amount) < 0.01) {
    $pdo->prepare("UPDATE orders SET status='paid', flw_id=? WHERE id=?")->execute([$flw_id, (int)$order['id']]);

    // clear cart for the paying user (if matches session)
    if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$order['user_id']) {
      $pdo->prepare("DELETE FROM cart WHERE user_id=?")->execute([(int)$order['user_id']]);
    }

    header('Location: ' . BASE_URL . 'account/ordercomplete.php?id='.(int)$order['id'].'&success=1'); exit;
  } else {
    $pdo->prepare("UPDATE orders SET status='failed', flw_id=? WHERE id=?")->execute([$flw_id, (int)$order['id']]);
    header('Location: ' . BASE_URL . 'account/ordercomplete.php?id='.(int)$order['id'].'&status=failed'); exit;
  }
}
header('Location: ' . BASE_URL . 'account/ordercomplete.php?status=unknown');
