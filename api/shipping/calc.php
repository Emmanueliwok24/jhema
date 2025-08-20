<?php
// api/shipping/calc.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cart.php';
require_once __DIR__ . '/../../includes/shipping.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'message'=>'POST required']); 
  exit;
}

$country  = strtoupper(trim((string)($_POST['country'] ?? '')));
$weight   = (float)($_POST['weight'] ?? 0);
$subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : null;

if ($country === '') {
  echo json_encode(['success'=>false,'message'=>'No country']); 
  exit;
}

// If weight or subtotal not provided, try taking from logged-in user's cart.
if (($weight <= 0.0 || $subtotal === null) && is_logged_in()) {
  $uid = (int)($_SESSION['user_id'] ?? 0);
  if ($uid > 0) {
    [$items, $cartSubtotal, $cartWeight] = array_slice(cart_totals($pdo, $uid), 0, 3);
    if ($weight <= 0.0)   $weight   = (float)$cartWeight;
    if ($subtotal === null) $subtotal = (float)$cartSubtotal;
  }
}
// Final fallback
if ($weight <= 0.0)   $weight = 0.0;
if ($subtotal === null) $subtotal = 0.0;

$shipping = (float) calculate_shipping_linear($weight, $country, $pdo);
$total    = $subtotal + $shipping;

echo json_encode([
  'success'                => true,
  'country'                => $country,
  'weight_kg'              => $weight,
  'shippingCost'           => $shipping,
  'formattedShippingCost'  => '₦' . number_format($shipping, 2),
  'subtotal'               => $subtotal,
  'formattedSubtotal'      => '₦' . number_format($subtotal, 2),
  'total'                  => $total,
  'formattedTotal'         => '₦' . number_format($total, 2),
]);
