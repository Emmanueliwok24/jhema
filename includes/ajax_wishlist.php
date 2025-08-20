<?php
// includes/ajax_wishlist.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

function jout(array $data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data);
  exit;
}

try {
  $userId = (int)($_SESSION['user_id'] ?? 0);
  if ($userId <= 0) {
    jout(['success' => false, 'login_required' => true, 'message' => 'Login required.']);
  }

  $getCount = function(PDO $pdo, int $uid): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
  };

  $resolveProduct = function(PDO $pdo, string $key): ?array {
    $k = trim($key);
    if ($k === '') return null;
    if (ctype_digit($k)) {
      $st = $pdo->prepare("SELECT id, slug FROM products WHERE id = ? LIMIT 1");
      $st->execute([(int)$k]);
      if ($row = $st->fetch(PDO::FETCH_ASSOC)) return ['id'=>(int)$row['id'],'slug'=>(string)$row['slug']];
    }
    $st = $pdo->prepare("SELECT id, slug FROM products WHERE slug = ? LIMIT 1");
    $st->execute([$k]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) return ['id'=>(int)$row['id'],'slug'=>(string)$row['slug']];
    $st = $pdo->prepare("SELECT id, slug FROM products WHERE sku = ? LIMIT 1");
    $st->execute([$k]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) return ['id'=>(int)$row['id'],'slug'=>(string)$row['slug']];
    return null;
  };

  $action = strtolower(trim((string)($_POST['action'] ?? $_GET['action'] ?? '')));

  if ($action === 'add') {
    $prodKey = (string)($_POST['product'] ?? $_GET['product'] ?? '');
    $prod = $resolveProduct($pdo, $prodKey);
    if (!$prod) jout(['success'=>false,'message'=>'Product not found.']);

    $pid = (int)$prod['id'];

    $chk = $pdo->prepare("SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ? LIMIT 1");
    $chk->execute([$userId, $pid]);

    if ($chk->fetchColumn()) {
      $del = $pdo->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
      $del->execute([$userId, $pid]);
      jout(['success'=>true,'in_wishlist'=>false,'count'=>$getCount($pdo,$userId)]);
    } else {
      $ins = $pdo->prepare("INSERT INTO wishlists (user_id, product_id, created_at) VALUES (?, ?, NOW())");
      $ins->execute([$userId, $pid]);
      jout(['success'=>true,'in_wishlist'=>true,'count'=>$getCount($pdo,$userId)]);
    }
  }

  if ($action === 'get_items') {
    $st = $pdo->prepare("
      SELECT p.slug
      FROM wishlists w
      LEFT JOIN products p ON p.id = w.product_id
      WHERE w.user_id = ?
      ORDER BY w.created_at DESC
    ");
    $st->execute([$userId]);
    $slugs = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
      if (!empty($r['slug'])) $slugs[] = (string)$r['slug']; // only slugs of existing products
    }
    jout(['success'=>true,'items'=>$slugs,'count'=>count($slugs)]);
  }

  if ($action === 'count') {
    jout(['success'=>true,'count'=>$getCount($pdo,$userId)]);
  }

  jout(['success'=>false,'message'=>'Unknown action.'], 400);

} catch (Throwable $e) {
  error_log('ajax_wishlist error: ' . $e->getMessage());
  jout(['success'=>false,'message'=>'Server error.'], 500);
}
