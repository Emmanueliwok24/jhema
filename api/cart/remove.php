<?php
// api/cart/remove.php
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
  echo json_encode(['success'=>false, 'message'=>'Not authenticated']);
  exit;
}

$userId    = (int)($_SESSION['user_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);
$variantId = isset($_POST['variant_id']) && $_POST['variant_id'] !== '' ? (int)$_POST['variant_id'] : null;

if ($productId <= 0) {
  echo json_encode(['success'=>false, 'message'=>'Invalid product']);
  exit;
}

[$ok, $err] = cart_remove($pdo, $userId, $productId, $variantId);
if (!$ok) {
  echo json_encode(['success'=>false, 'message'=>$err ?: 'Remove failed']);
  exit;
}

[$items, $subtotal, $weight, $count] = cart_totals($pdo, $userId);

echo json_encode([
  'success'  => true,
  'cart'     => [
    'items'     => $items,
    'subtotal'  => $subtotal,
    'weight_kg' => $weight,
    'count'     => $count,
  ],
]);
