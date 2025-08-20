<?php
// admin/orders/update_status.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/order_helpers.php';
// Mail is optional; if not present we just skip emailing.
@include_once __DIR__ . '/../../includes/mail.php';

require_admin();

/* ==== simple inputs (NO CSRF) ============================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect(base_url('admin/orders/index.php'));
}

$orderId = (int)($_POST['order_id'] ?? 0);
$status  = trim((string)($_POST['status'] ?? ''));
$note    = trim((string)($_POST['note'] ?? ''));

if ($orderId <= 0) {
  redirect(base_url('admin/orders/view.php?id=0&err=bad_id'));
}

$allowed = array_keys(order_status_labels());
if ($status === '' || !in_array($status, $allowed, true)) {
  redirect(base_url('admin/orders/view.php?id=' . $orderId . '&err=bad_status'));
}

/* ==== best-effort admin actor ============================================== */

$actorId = null;
if (isset($_SESSION['admin_id']) && is_numeric($_SESSION['admin_id'])) {
  $actorId = (int)$_SESSION['admin_id'];
} elseif (isset($_SESSION['admin']['id']) && is_numeric($_SESSION['admin']['id'])) {
  $actorId = (int)$_SESSION['admin']['id'];
}

/* ==== update + optional email ============================================== */

try {
  $ok = set_order_status($pdo, $orderId, $actorId, $status, ($note !== '' ? $note : null));
  if (!$ok) {
    redirect(base_url('admin/orders/view.php?id=' . $orderId . '&err=update_failed'));
  }

  // Email: best-effort; skipped if send_mail() not defined.
  $order = get_order($pdo, $orderId);
  if ($order && !empty($order['user_email']) && function_exists('send_mail')) {
    $orderCode = $order['order_number'] ?: ('#' . $order['id']);
    $human     = order_status_human($status);
    $msg       = order_status_message($status);

    $greet = trim(($order['user_first_name'] ?? '') . ' ' . ($order['user_last_name'] ?? ''));
    if ($greet === '') $greet = 'Customer';

    $trackUrl = base_url('account/ordertracking.php');

    $subject = "Order {$orderCode} status: {$human}";
    $html = "
      <p>Hello " . htmlspecialchars($greet) . ",</p>
      <p>The status of your order <strong>" . htmlspecialchars($orderCode) . "</strong> is now <strong>" . htmlspecialchars($human) . "</strong>.</p>
      <p>" . htmlspecialchars($msg) . "</p>
      <p>Track it here: <a href=\"" . htmlspecialchars($trackUrl) . "\">Order Tracking</a></p>
      <p>— Jhema</p>";
    $alt  = "Hello {$greet},\nOrder {$orderCode} status: {$human}\n{$msg}\nTrack: {$trackUrl}\n— Jhema";

    try { @send_mail($order['user_email'], $subject, $html, $alt); }
    catch (Throwable $mailErr) { error_log('[ORDER_STATUS_EMAIL] ' . $mailErr->getMessage()); }
  }

  redirect(base_url('admin/orders/view.php?id=' . $orderId . '&ok=1'));
} catch (Throwable $e) {
  error_log('[ORDER_STATUS_UPDATE_FATAL] ' . $e->getMessage());
  redirect(base_url('admin/orders/view.php?id=' . $orderId . '&err=exception'));
}
