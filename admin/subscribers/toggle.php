<?php
// admin/subscribers/toggle.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
  header('Location: ' . rtrim(BASE_URL,'/') . '/admin/subscribers/all.php?flash=' . rawurlencode('Invalid action.'));
  exit;
}

$action = (string)($_POST['action'] ?? '');
$ids    = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
if (!$ids) {
  header('Location: ' . rtrim(BASE_URL,'/') . '/admin/subscribers/all.php?flash=' . rawurlencode('No subscribers selected.'));
  exit;
}

try {
  if ($action === 'unsubscribe') {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("UPDATE newsletter_subscribers SET status='unsubscribed' WHERE id IN ($in)");
    $st->execute($ids);
    $msg = 'Unsubscribed selected.';
  } elseif ($action === 'resubscribe') {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("UPDATE newsletter_subscribers SET status='active' WHERE id IN ($in)");
    $st->execute($ids);
    $msg = 'Resubscribed selected.';
  } else {
    $msg = 'Unknown action.';
  }
} catch (Throwable $e) {
  error_log('TOGGLE SUB: '.$e->getMessage());
  $msg = 'Failed to update subscribers.';
}

header('Location: ' . rtrim(BASE_URL,'/') . '/admin/subscribers/all.php?flash=' . rawurlencode($msg));
exit;
