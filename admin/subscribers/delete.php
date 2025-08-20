<?php
// admin/subscribers/delete.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
  header('Location: ' . rtrim(BASE_URL,'/') . '/admin/subscribers/all.php?flash=' . rawurlencode('Invalid action.'));
  exit;
}

$ids = [];
if (isset($_POST['id'])) $ids[] = (int)$_POST['id'];
if (!empty($_POST['ids']) && is_array($_POST['ids'])) $ids = array_merge($ids, array_map('intval', $_POST['ids']));
$ids = array_values(array_unique(array_filter($ids)));

if (!$ids) {
  header('Location: ' . rtrim(BASE_URL,'/') . '/admin/subscribers/all.php?flash=' . rawurlencode('No subscribers selected.'));
  exit;
}

try {
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id IN ($in)");
  $st->execute($ids);
  $msg = 'Deleted subscriber(s).';
} catch (Throwable $e) {
  error_log('DELETE SUB: '.$e->getMessage());
  $msg = 'Failed to delete subscriber(s).';
}

header('Location: ' . rtrim(BASE_URL,'/') . '/admin/subscribers/all.php?flash=' . rawurlencode($msg));
exit;
