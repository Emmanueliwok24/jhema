<?php
// includes/order_helpers.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- Small shared helpers ---------- */

/** Map of internal status => human label (also used by admin filter) */
function order_status_labels(): array {
  return [
    'pending'           => 'Pending',
    'awaiting_payment'  => 'Awaiting Payment',
    'paid'              => 'Paid',
    'processing'        => 'Processing',
    'shipped'           => 'Shipped',
    'in_transit'        => 'In Transit',
    'delivered'         => 'Delivered',
    'completed'         => 'Completed',
    'cancelled'         => 'Cancelled',
    'failed'            => 'Failed',
    'refunded'          => 'Refunded',
  ];
}

/** Human label for a given status key */
function order_status_human(string $status): string {
  $s = strtolower(trim($status));
  $map = order_status_labels();
  return $map[$s] ?? ucwords(str_replace('_',' ', $s));
}

/** Short end-user message for status */
function order_status_message(string $status): string {
  return match (strtolower(trim($status))) {
    'pending'           => 'Your order is pending. We’ll update you soon.',
    'awaiting_payment'  => 'We’re waiting for your payment to be confirmed.',
    'paid'              => 'Payment received. Thank you!',
    'processing'        => 'Your order is being processed.',
    'shipped'           => 'Your order has been shipped.',
    'in_transit'        => 'Your order is in transit.',
    'delivered'         => 'Your order has been delivered.',
    'completed'         => 'Your order is completed. Enjoy!',
    'cancelled'         => 'This order was cancelled.',
    'failed'            => 'Payment failed. Please try again or contact support.',
    'refunded'          => 'This order was refunded.',
     default            => 'Status updated.',
  };
}

/** "JHEMA-000123" if available, else "#id" */
function order_public_number(array $order): string {
  if (!empty($order['order_number'])) return (string)$order['order_number'];
  $id = (int)($order['id'] ?? 0);
  return $id > 0 ? ('#' . $id) : '#?';
}

/** [total, currency] with safe defaults */
function order_total_and_currency(array $order): array {
  $total = (float)($order['total_amount'] ?? ($order['total'] ?? 0));
  $cur   = (string)($order['currency'] ?? 'NGN');
  return [$total, $cur];
}

/** Simple money helper */
function money_disp($amt, $currency = 'NGN'): string {
  $prefix = ($currency === 'NGN' || $currency === '₦') ? '₦' : '';
  return $prefix . number_format((float)$amt, 2);
}

/* ---------- Data loaders ---------- */

/** Get order row + user basics */
function get_order(PDO $pdo, int $orderId): ?array {
  $sql = "SELECT o.*,
                 u.email      AS user_email,
                 u.first_name AS user_first_name,
                 u.last_name  AS user_last_name
          FROM orders o
          LEFT JOIN users u ON u.id = o.user_id
          WHERE o.id = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$orderId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/** Find an order for a specific user by id or public order_number */
function find_user_order(PDO $pdo, int $userId, string $idOrNum): ?array {
  $idOrNum = trim($idOrNum);
  if ($idOrNum === '') return null;

  if (ctype_digit($idOrNum)) {
    $st = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    $st->execute([(int)$idOrNum, $userId]);
  } else {
    $st = $pdo->prepare("SELECT * FROM orders WHERE LOWER(order_number) = LOWER(?) AND user_id = ? LIMIT 1");
    $st->execute([$idOrNum, $userId]);
  }
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/** Items for an order */
function get_order_items(PDO $pdo, int $orderId): array {
  $st = $pdo->prepare("SELECT id, product_id, name, price, quantity, weight_kg, subtotal
                       FROM order_items
                       WHERE order_id = ?
                       ORDER BY id ASC");
  $st->execute([$orderId]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Alias to match account/orderview.php */
function load_order_items(PDO $pdo, int $orderId): array {
  return get_order_items($pdo, $orderId);
}

/** Status events (history) -> array of ['status','created_at','note'] */
function get_order_events(PDO $pdo, int $orderId): array {
  $st = $pdo->prepare("SELECT id, actor_admin_id, from_status, to_status, note, meta_json, created_at
                       FROM order_events
                       WHERE order_id = ?
                       ORDER BY created_at ASC, id ASC");
  $st->execute([$orderId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as &$r) {
    if (!empty($r['meta_json']) && is_string($r['meta_json'])) {
      $decoded = json_decode($r['meta_json'], true);
      if (json_last_error() === JSON_ERROR_NONE) $r['meta'] = $decoded;
    }
  }
  return $rows;
}

/** Alias for account/orderview.php expected structure */
function load_order_history(PDO $pdo, int $orderId): array {
  $events = get_order_events($pdo, $orderId);
  $hist = [];
  foreach ($events as $ev) {
    $hist[] = [
      'status'     => (string)$ev['to_status'],
      'created_at' => (string)$ev['created_at'],
      'note'       => (string)($ev['note'] ?? ''),
    ];
  }
  if (!$hist) {
    // Fallback to at least current status & created_at from orders when no events exist
    $st = $pdo->prepare("SELECT status, created_at FROM orders WHERE id = ? LIMIT 1");
    $st->execute([$orderId]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $hist[] = [
        'status'     => (string)$row['status'],
        'created_at' => (string)$row['created_at'],
        'note'       => '',
      ];
    }
  }
  return $hist;
}

/**
 * Shipment info (optional): tries to read carrier/tracking_code/tracking_url from orders.
 * If columns don’t exist, returns an array with nulls to avoid errors.
 */
function load_order_shipment(PDO $pdo, int $orderId): ?array {
  try {
    $st = $pdo->prepare("SELECT carrier, tracking_code, tracking_url FROM orders WHERE id = ? LIMIT 1");
    $st->execute([$orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
      'carrier'       => $row['carrier']       ?? null,
      'tracking_code' => $row['tracking_code'] ?? null,
      'tracking_url'  => $row['tracking_url']  ?? null,
    ];
  } catch (Throwable $e) {
    // columns might not exist yet → return defaults
    return [
      'carrier'       => null,
      'tracking_code' => null,
      'tracking_url'  => null,
    ];
  }
}

/* ---------- Mutations ---------- */

/**
 * Update status + record event.
 * - Works whether or not orders.updated_at exists.
 * - Accepts NULL actorAdminId.
 * - If order_events.actor_admin_id is NOT NULL in DB, we retry insert with 0.
 */
function set_order_status(PDO $pdo, int $orderId, ?int $actorAdminId, string $toStatus, ?string $note = null): bool {
  $toStatus = trim($toStatus);
  if ($toStatus === '') return false;

  // read from_status
  $cur = $pdo->prepare("SELECT status FROM orders WHERE id = ? LIMIT 1");
  $cur->execute([$orderId]);
  $fromStatus = (string)($cur->fetchColumn() ?: '');

  $pdo->beginTransaction();
  try {
    // Update with updated_at if present; otherwise retry without it
    try {
      $u = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
      $u->execute([$toStatus, $orderId]);
    } catch (Throwable $e1) {
      $u = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? LIMIT 1");
      $u->execute([$toStatus, $orderId]);
    }

    // Insert event, tolerant of NULL actor_admin_id
    $insert = $pdo->prepare("
      INSERT INTO order_events (order_id, actor_admin_id, from_status, to_status, note, meta_json, created_at)
      VALUES (?, ?, ?, ?, ?, NULL, NOW())
    ");

    try {
      $insert->execute([
        $orderId,
        ($actorAdminId !== null ? (int)$actorAdminId : null),
        ($fromStatus !== '' ? $fromStatus : null),
        $toStatus,
        ($note !== '' ? $note : null)
      ]);
    } catch (Throwable $e2) {
      // If actor_admin_id cannot be NULL in schema, retry with 0
      if ($actorAdminId === null) {
        $insert2 = $pdo->prepare("
          INSERT INTO order_events (order_id, actor_admin_id, from_status, to_status, note, meta_json, created_at)
          VALUES (?, 0, ?, ?, ?, NULL, NOW())
        ");
        $insert2->execute([
          $orderId,
          ($fromStatus !== '' ? $fromStatus : null),
          $toStatus,
          ($note !== '' ? $note : null)
        ]);
      } else {
        throw $e2;
      }
    }

    $pdo->commit();
    return true;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}
