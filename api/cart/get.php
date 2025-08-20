<?php
// api/cart/get.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
  echo json_encode([
    'success' => true,
    'cart'    => ['items'=>[], 'subtotal'=>0, 'weight_kg'=>0, 'count'=>0],
  ]);
  exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
[$items, $subtotal, $weight, $count] = cart_totals($pdo, $userId);

echo json_encode([
  'success' => true,
  'cart'    => [
    'items'     => $items,     // includes image_url + variant_label + unit_price
    'subtotal'  => $subtotal,
    'weight_kg' => $weight,
    'count'     => $count,
  ],
]);
