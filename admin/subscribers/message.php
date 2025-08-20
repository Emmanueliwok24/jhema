<?php
// admin/subscribers/message.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

function nude_color(): string { return '#c19a6b'; }
function build_newsletter_html(string $innerHtml, string $unsubUrl, string $name = ''): string {
  $greet = $name !== '' ? "Hello " . htmlspecialchars($name) . "," : "Hello,";
  return '<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#222;">'
      . '<p style="margin:0 0 12px">'.$greet.'</p>'
      . '<div>'.$innerHtml.'</div>'
      . '<hr style="margin:20px 0;border:none;border-top:1px solid #eee;">'
      . '<p style="font-size:12px;color:#666;margin:0;">You received this because you subscribed to our newsletter.<br>'
      . '<a href="'. htmlspecialchars($unsubUrl) .'" style="color:'. nude_color() .';text-decoration:underline;">Unsubscribe</a></p>'
      . '</div>';
}

$msg = null; $sent = 0; $intended = 0;

/* Collect targets */
$ids = [];
if (isset($_GET['id'])) $ids[] = (int)$_GET['id'];
if (!empty($_POST['ids']) && is_array($_POST['ids'])) $ids = array_merge($ids, array_map('intval', $_POST['ids']));
$ids = array_values(array_unique(array_filter($ids)));

/* Handle send */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['subject'], $_POST['html'])) {
  if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $msg = ['type'=>'danger','text'=>'Invalid session. Refresh and try again.'];
  } else {
    $subject = trim((string)$_POST['subject']);
    $html    = (string)$_POST['html'];

    // If none passed via POST, pull from GET id
    if (!$ids && isset($_POST['id'])) $ids[] = (int)$_POST['id'];

    try {
      if ($subject === '' || $html === '') throw new RuntimeException('Subject and HTML body are required.');

      // If no IDs chosen, fallback to all active (use with care)
      if (!$ids) {
        $rst = $pdo->query("SELECT id FROM newsletter_subscribers WHERE status='active' ORDER BY id ASC");
        $ids = array_map('intval', $rst->fetchAll(PDO::FETCH_COLUMN));
      }

      if (!$ids) throw new RuntimeException('No target subscribers.');

      // Load recipients
      $in = implode(',', array_fill(0, count($ids), '?'));
      $st = $pdo->prepare("SELECT id, email, name, unsub_token FROM newsletter_subscribers WHERE id IN ($in) AND status='active'");
      $st->execute($ids);
      $subs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $intended = count($subs);

      // Log campaign
      $stc = $pdo->prepare("INSERT INTO newsletter_campaigns (subject, html, created_by) VALUES (?, ?, ?)");
      $stc->execute([$subject, $html, (int)($_SESSION['user_id'] ?? 0)]);
      $campaignId = (int)$pdo->lastInsertId();

      foreach ($subs as $s) {
        $email = (string)$s['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        $unsubUrl = rtrim(BASE_URL,'/').'/newsletter/unsubscribe.php?t='.rawurlencode((string)$s['unsub_token']);
        $body = build_newsletter_html($html, $unsubUrl, (string)($s['name'] ?? ''));

        $ok = send_mail($email, $subject, $body, null, [
          'headers' => [['name'=>'List-Unsubscribe','value'=>'<'.$unsubUrl.'>']]
        ]);
        if ($ok) {
          $sent++;
          $pdo->prepare("UPDATE newsletter_subscribers SET last_sent_at = NOW() WHERE id = ?")->execute([(int)$s['id']]);
        }
        usleep(60000);
      }

      $msg = ['type'=>'success','text'=>"Sent {$sent} / {$intended} message(s)."];
    } catch (Throwable $e) {
      error_log('SUBSCRIBER MSG: '.$e->getMessage());
      $msg = ['type'=>'danger','text'=>$e->getMessage()];
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
        <h3 class="mb-3">Send Message</h3>

        <?php if ($msg): ?>
          <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['text']) ?></div>
        <?php endif; ?>

        <div class="card p-3 p-md-4">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php foreach ($ids as $id): ?>
              <input type="hidden" name="ids[]" value="<?= (int)$id ?>">
            <?php endforeach; ?>

            <div class="mb-3">
              <label class="form-label">Subject</label>
              <input type="text" class="form-control" name="subject" required placeholder="A note from Jhema">
            </div>
            <div class="mb-3">
              <label class="form-label d-flex justify-content-between"><span>HTML Body</span><small class="text-muted">Rich text editor enabled</small></label>
              <textarea class="form-control" id="editor_html" name="html" rows="12" required>
<h3>Hi there,</h3>
<p>Thanks for staying with Jhema. Here’s a quick update…</p>
              </textarea>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-primary">Send</button>
              <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/subscribers/all.php')) ?>">Back</a>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../partials/logout.php'; ?>
<?php include __DIR__ . '/../partials/script-js.php'; ?>

<script src="https://cdn.tiny.cloud/1/r67m9yx0grkw0tus6mgwzjrk0yy1sqaaqfikdxi21ks59nt3/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector:'#editor_html',
    menubar:false,
    plugins:'link lists code image table autoresize',
    toolbar:'undo redo | styles | bold italic underline | bullist numlist | link image table | removeformat | code',
    branding:false,
    height:360
  });
</script>
