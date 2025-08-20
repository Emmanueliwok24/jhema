<?php
// newsletter/unsubscribe.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$token = isset($_GET['t']) ? trim((string)$_GET['t']) : '';

$ok = false;
if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
  try {
    $st = $pdo->prepare("
      UPDATE newsletter_subscribers
      SET status = 'unsubscribed',
          unsubscribed_at = NOW()
      WHERE unsub_token = :t AND status <> 'unsubscribed'
    ");
    $st->execute([':t' => $token]);
    $ok = $st->rowCount() > 0;
  } catch (Throwable $e) {
    error_log('[NEWSLETTER UNSUBSCRIBE] ' . $e->getMessage());
  }
}

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/svg.php';
include __DIR__ . '/../includes/mobile-header.php';
include __DIR__ . '/../includes/header.php';
?>
<main class="main">
  <div class="container py-5">
    <?php if ($ok): ?>
      <div class="alert alert-success">You’ve been unsubscribed. Sorry to see you go.</div>
    <?php else: ?>
      <div class="alert alert-warning">This unsubscribe link is invalid or has already been used.</div>
    <?php endif; ?>
    <a class="btn btn-dark mt-3" href="<?= htmlspecialchars(BASE_URL) ?>">Back to Home</a>
  </div>
</main>
<?php
include __DIR__ . '/../includes/footer.php';
include __DIR__ . '/../includes/mobile-footer.php';
include __DIR__ . '/../includes/script-footer.php';
