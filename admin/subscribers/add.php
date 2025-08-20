<?php
// admin/subscribers/add.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

$msg = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $msg = ['type'=>'danger','text'=>'Invalid session. Refresh and try again.'];
  } else {
    $email = trim((string)($_POST['email'] ?? ''));
    $name  = trim((string)($_POST['name'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $msg = ['type'=>'warning','text'=>'Valid email is required.'];
    } else {
      try {
        // prevent duplicate
        $ex = $pdo->prepare("SELECT id FROM newsletter_subscribers WHERE email = ? LIMIT 1");
        $ex->execute([$email]);
        if ($ex->fetchColumn()) {
          $msg = ['type'=>'warning','text'=>'Email already exists.'];
        } else {
          $tok = bin2hex(random_bytes(16));
          $st = $pdo->prepare("INSERT INTO newsletter_subscribers (email, name, status, unsub_token) VALUES (?, ?, 'active', ?)");
          $st->execute([$email, $name, $tok]);
          header('Location: ' . rtrim(BASE_URL,'/') . '/admin/subscribers/all.php?flash=' . rawurlencode('Subscriber added.'));
          exit;
        }
      } catch (Throwable $e) {
        error_log('SUBSCRIBER ADD: '.$e->getMessage());
        $msg = ['type'=>'danger','text'=>'Failed to add subscriber.'];
      }
    }
  }
}
?>
<?php include __DIR__ . '/../partials/head.php'; ?>
<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php include __DIR__ . '/../partials/page-header.php'; ?>
  <div class="page-body-wrapper">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="page-body">
      <div class="container-fluid py-4">
        <h3 class="mb-3">Add Subscriber</h3>

        <?php if ($msg): ?>
          <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['text']) ?></div>
        <?php endif; ?>

        <div class="card p-3 p-md-4">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Name (optional)</label>
              <input type="text" class="form-control" name="name">
            </div>
            <button class="btn btn-primary">Add</button>
            <a href="<?= e(base_url('admin/subscribers/all.php')) ?>" class="btn btn-outline-secondary">Back</a>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../partials/logout.php'; ?>
<?php include __DIR__ . '/../partials/script-js.php'; ?>
