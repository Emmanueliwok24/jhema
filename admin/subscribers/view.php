<?php
// admin/subscribers/view.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ' . rtrim(BASE_URL,'/') . '/admin/subscribers/all.php'); exit; }

$st = $pdo->prepare("SELECT * FROM newsletter_subscribers WHERE id = ? LIMIT 1");
$st->execute([$id]);
$s = $st->fetch(PDO::FETCH_ASSOC);
if (!$s) { header('Location: ' . rtrim(BASE_URL,'/') . '/admin/subscribers/all.php?flash='.rawurlencode('Subscriber not found')); exit; }
$active = ($s['status']==='active');
?>
<?php include __DIR__ . '/../partials/head.php'; ?>
<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php include __DIR__ . '/../partials/page-header.php'; ?>
  <div class="page-body-wrapper">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="page-body">
      <div class="container-fluid py-4">
        <h3 class="mb-3">Subscriber Details</h3>

        <div class="card p-3 p-md-4">
          <dl class="row">
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9 font-monospace"><?= e($s['email']) ?></dd>
            <dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= e($s['name'] ?? '') ?></dd>
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= e($s['status']) ?></dd>
            <dt class="col-sm-3">Joined</dt><dd class="col-sm-9"><?= e((string)$s['created_at']) ?></dd>
            <dt class="col-sm-3">Last Sent</dt><dd class="col-sm-9"><?= e($s['last_sent_at'] ?? '—') ?></dd>
          </dl>

          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/subscribers/all.php')) ?>">Back</a>
            <a class="btn btn-primary" href="<?= e(base_url('admin/subscribers/message.php?id='.(int)$s['id'])) ?>">Send Message</a>

            <?php if ($active): ?>
            <form method="post" action="<?= e(base_url('admin/subscribers/toggle.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="unsubscribe">
              <input type="hidden" name="ids[]" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-warning" onclick="return confirm('Unsubscribe this email?')">Unsubscribe</button>
            </form>
            <?php else: ?>
            <form method="post" action="<?= e(base_url('admin/subscribers/toggle.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="resubscribe">
              <input type="hidden" name="ids[]" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-success" onclick="return confirm('Resubscribe this email?')">Resubscribe</button>
            </form>
            <?php endif; ?>

            <form method="post" action="<?= e(base_url('admin/subscribers/delete.php')) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-danger" onclick="return confirm('Delete this subscriber? This cannot be undone.')">Delete</button>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../partials/logout.php'; ?>
<?php include __DIR__ . '/../partials/script-js.php'; ?>
