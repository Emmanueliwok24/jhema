<?php
// api/cart/add.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false, 'message'=>'POST required']);
  exit;
}

if (!is_logged_in()) {
  $fallback = base_url('shop/shop.php');
  $ref      = (string)($_SERVER['HTTP_REFERER'] ?? $fallback);
  echo json_encode([
    'success'  => false,
    'login'    => true,
    'loginUrl' => base_url('account/auth.php?tab=login&redirect=' . urlencode($ref)),
    'message'  => 'Please log in to add items to your cart.',
  ]);
  exit;
}

$userId       = (int)($_SESSION['user_id'] ?? 0);
$slug         = trim((string)($_POST['slug'] ?? ''));
$qty          = (int)($_POST['quantity'] ?? 1);
$variantId    = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
$variantLabel = isset($_POST['variant_label']) ? trim((string)$_POST['variant_label']) : null;

if ($slug === '' || $qty < 1) {
  echo json_encode(['success'=>false, 'message'=>'Invalid input']);
  exit;
}

[$ok, $err, $pid, $vid] = cart_add_by_slug($pdo, $userId, $slug, $qty, $variantId, $variantLabel);
if (!$ok) {
  echo json_encode(['success'=>false, 'message'=>$err ?: 'Failed to add']);
  exit;
}

[$items, $subtotal, $weight, $count] = cart_totals($pdo, $userId);

echo json_encode([
  'success'     => true,
  'product_id'  => $pid,
  'variant_id'  => $vid,
  'cart'        => [
    'items'     => $items,    // includes image_url + variant_label + unit_price
    'subtotal'  => $subtotal,
    'weight_kg' => $weight,   // numeric total
    'count'     => $count,
  ],
]);
