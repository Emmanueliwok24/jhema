<?php
// api/orders/track.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/order_helpers.php';

/* ---------- shared tracking helpers (identical to tracking page) ---------- */
if (!function_exists('jhema_status_flow')) {
  function jhema_status_flow(): array {
    return [
      'pending',
      'awaiting_payment',
      'paid',
      'processing',
      'shipped',
      'in_transit',
      'delivered',
      'completed',
    ];
  }
}
if (!function_exists('jhema_progress_from_history')) {
  function jhema_progress_from_history(array $history, string $currentStatus): array {
    $flow     = jhema_status_flow();
    $indexMap = array_flip($flow);
    $idx      = 0;
    $terminal = false;
    $terminalHit = null;

    if ($history) {
      foreach ($history as $ev) {
        $s = strtolower(trim((string)($ev['status'] ?? '')));
        if (isset($indexMap[$s])) $idx = max($idx, (int)$indexMap[$s]);
        if (in_array($s, ['cancelled','failed','refunded'], true)) {
          $terminal = true; $terminalHit = $s;
        }
      }
    } else {
      $s = strtolower(trim($currentStatus));
      if (isset($indexMap[$s])) $idx = (int)$indexMap[$s];
      if (in_array($s, ['cancelled','failed','refunded'], true)) {
        $terminal = true; $terminalHit = $s;
      }
    }

    $steps = max(1, count($flow));
    $pct   = ($steps > 1) ? (int)round(($idx / ($steps - 1)) * 100) : 0;

    return [
      'flow'         => $flow,
      'index'        => $idx,
      'percent'      => $pct,
      'is_terminal'  => $terminal,
      'terminal_key' => $terminalHit,
    ];
  }
}

/* ---------- read JSON body ---------- */
$body = file_get_contents('php://input');
$data = json_decode($body ?? '', true);

$orderCode = trim((string)($data['order_code'] ?? ''));
$email     = trim((string)($data['email'] ?? ''));

if ($orderCode === '' || $email === '') {
  echo json_encode(['success'=>false,'error'=>'missing_params']); exit;
}

/* ---------- lookup order by code/ID + email ---------- */
try {
  // Try order_number (case-insensitive) first
  $sql = "SELECT o.id, o.order_number, o.status, 
                 o.total_amount, COALESCE(o.currency,'NGN') AS currency, o.created_at
          FROM orders o
          JOIN users u ON u.id = o.user_id
          WHERE LOWER(o.order_number) = LOWER(?) AND u.email = ?
          LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$orderCode, $email]);
  $order = $st->fetch(PDO::FETCH_ASSOC);

  // Fallback: numeric ID typed instead of code
  if (!$order && ctype_digit($orderCode)) {
    $st = $pdo->prepare("SELECT o.id, o.order_number, o.status, 
                                o.total_amount, COALESCE(o.currency,'NGN') AS currency, o.created_at
                         FROM orders o
                         JOIN users u ON u.id = o.user_id
                         WHERE o.id = ? AND u.email = ?
                         LIMIT 1");
    $st->execute([(int)$orderCode, $email]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
  }

  if (!$order) {
    echo json_encode(['success'=>false,'error'=>'not_found']); exit;
  }

  // Load timeline from order_events (same as tracking page uses)
  $history  = load_order_history($pdo, (int)$order['id']);   // [['status','created_at','note'], ...]
  $progress = jhema_progress_from_history($history, (string)$order['status']);

  $resp = [
    'success' => true,
    'order' => [
      'id'             => (int)$order['id'],
      'order_number'   => (string)($order['order_number'] ?? ''),
      'status'         => (string)$order['status'],
      'status_label'   => order_status_human((string)$order['status']),
      'status_message' => order_status_message((string)$order['status']),
      'created_at'     => (string)$order['created_at'],
      'total_amount'   => (float)($order['total_amount'] ?? 0),
      'currency'       => (string)($order['currency'] ?? 'NGN'),
    ],
    'progress' => [
      'index'       => $progress['index'],
      'percent'     => $progress['percent'],
      'is_terminal' => $progress['is_terminal'],
    ],
  ];
  echo json_encode($resp); exit;

} catch (Throwable $e) {
  error_log('[TRACK_API_FATAL] '.$e->getMessage());
  echo json_encode(['success'=>false,'error'=>'exception']); exit;
}
