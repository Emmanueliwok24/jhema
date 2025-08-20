<?php
// admin/newsletter_send.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';   // if you need any root helpers
require_once __DIR__ . '/../includes/mail.php';

require_once __DIR__ . '/partials/functions.php';      // base_url, session, csrf_token/verify
require_once __DIR__ . '/partials/auth.php';           // admin auth helpers
require_admin();                                       // protect page

/* -------------------- Theme helper -------------------- */
function nude_color(): string { return '#c19a6b'; }

/* -------------------- Email HTML -------------------- */
function build_newsletter_html(string $innerHtml, string $unsubUrl, string $name = ''): string {
  $greet = $name !== '' ? "Hello " . htmlspecialchars($name) . "," : "Hello,";
  return '
  <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#222;">
    <p style="margin:0 0 12px">'.$greet.'</p>
    <div>'. $innerHtml .'</div>
    <hr style="margin:20px 0;border:none;border-top:1px solid #eee;">
    <p style="font-size:12px;color:#666;margin:0;">
      You received this because you subscribed to our newsletter.
      <br>
      <a href="'. htmlspecialchars($unsubUrl) .'" style="color:'. nude_color() .';text-decoration:underline;">Unsubscribe</a>
    </p>
  </div>';
}

/* -------------------- Filters (GET) -------------------- */
$q       = trim((string)($_GET['q'] ?? ''));
$status  = (string)($_GET['status'] ?? 'active'); // active | unsubscribed | all
$perPage = max(10, min(200, (int)($_GET['per'] ?? 25)));
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* WHERE (positional placeholders) */
$whereParts = [];
$whereBind  = [];

if ($status === 'active') {
  $whereParts[] = "s.status = ?";
  $whereBind[]  = 'active';
} elseif ($status === 'unsubscribed') {
  $whereParts[] = "s.status = ?";
  $whereBind[]  = 'unsubscribed';
}

if ($q !== '') {
  $whereParts[] = "(s.email LIKE ? OR s.name LIKE ?)";
  $like = '%'.$q.'%';
  $whereBind[] = $like;
  $whereBind[] = $like;
}

$whereSql = $whereParts ? ('WHERE '.implode(' AND ', $whereParts)) : '';

/* -------------------- Stats -------------------- */
$totalAll    = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'")->fetchColumn();
$totalUnsub  = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'unsubscribed'")->fetchColumn();

/* -------------------- Count + List -------------------- */
$countSql = "SELECT COUNT(*) FROM newsletter_subscribers s $whereSql";
$cstmt = $pdo->prepare($countSql);
$cstmt->execute($whereBind);
$totalFiltered = (int)$cstmt->fetchColumn();

$lim = (int)$perPage; $off = (int)$offset; // inline validated ints
$listSql = "
  SELECT s.id, s.email, s.name, s.status, s.created_at, s.last_sent_at
  FROM newsletter_subscribers s
  $whereSql
  ORDER BY s.created_at DESC, s.id DESC
  LIMIT $lim OFFSET $off
";
$lstmt = $pdo->prepare($listSql);
$lstmt->execute($whereBind);
$rows = $lstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$pages = max(1, (int)ceil($totalFiltered / $perPage));

/* -------------------- Handle Send (POST) -------------------- */
$msg = null; $sentCount = 0; $intended = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = $_POST['csrf_token'] ?? '';
  $csrf_ok = function_exists('csrf_check') ? csrf_check($postedToken) : csrf_verify($postedToken);
  if (!$csrf_ok) {
    $msg = ['type'=>'danger','text'=>'Invalid session. Refresh and try again.'];
  } else {
    $subject = trim((string)($_POST['subject'] ?? ''));
    $html    = (string)($_POST['html'] ?? '');
    $mode    = (string)($_POST['send_mode'] ?? 'selected'); // selected | filtered
    $selectedIds = array_filter(array_map('intval', (array)($_POST['selected'] ?? [])));

    if ($subject === '' || $html === '') {
      $msg = ['type'=>'warning','text'=>'Subject and HTML body are required.'];
    } else {
      try {
        // Save campaign record
        $stc = $pdo->prepare("INSERT INTO newsletter_campaigns (subject, html, created_by) VALUES (?, ?, ?)");
        $stc->execute([$subject, $html, (int)($_SESSION['user_id'] ?? 0)]);
        $campaignId = (int)$pdo->lastInsertId();

        // Recipients
        $subs = [];

        if ($mode === 'filtered') {
          $recParts = ["s.status = ?"];
          $recBind  = ['active'];
          if ($q !== '') {
            $recParts[] = "(s.email LIKE ? OR s.name LIKE ?)";
            $like = '%'.$q.'%';
            $recBind[] = $like; $recBind[] = $like;
          }
          $recWhere = 'WHERE '.implode(' AND ', $recParts);
          $recSql = "SELECT id, email, name, unsub_token FROM newsletter_subscribers s $recWhere ORDER BY s.id ASC";
          $rst = $pdo->prepare($recSql);
          $rst->execute($recBind);
          $subs = $rst->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
          if (!$selectedIds) {
            $msg = ['type'=>'warning','text'=>'Please select at least one active subscriber (or use "Send to All Filtered").'];
          } else {
            $in = implode(',', array_fill(0, count($selectedIds), '?'));
            $recSql = "SELECT id, email, name, unsub_token FROM newsletter_subscribers WHERE status = 'active' AND id IN ($in) ORDER BY id ASC";
            $rst = $pdo->prepare($recSql);
            $rst->execute($selectedIds);
            $subs = $rst->fetchAll(PDO::FETCH_ASSOC) ?: [];
          }
        }

        $intended = count($subs);

        foreach ($subs as $s) {
          $email = (string)$s['email'];
          if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

          $name  = (string)($s['name'] ?? '');
          $tok   = (string)$s['unsub_token'];
          $unsubUrl = rtrim(BASE_URL, '/') . '/newsletter/unsubscribe.php?t=' . rawurlencode($tok);

          $body = build_newsletter_html($html, $unsubUrl, $name);

          $ok = send_mail(
            $email,
            $subject,
            $body,
            null,
            ['headers' => [['name' => 'List-Unsubscribe', 'value' => '<' . $unsubUrl . '>']]]
          );

          if ($ok) {
            $sentCount++;
            $pdo->prepare("UPDATE newsletter_subscribers SET last_sent_at = NOW() WHERE id = ?")
                ->execute([(int)$s['id']]);
          }

          usleep(60000); // 60ms throttle
        }

        if (!isset($msg)) $msg = ['type'=>'success','text'=>"Sent {$sentCount} / {$intended} message(s)."];

      } catch (Throwable $e) {
        error_log('[NEWSLETTER SEND] ' . $e->getMessage());
        $msg = ['type'=>'danger','text'=>'Failed to send. Check logs and SMTP settings.'];
      }
    }
  }
}
?>
<?php include __DIR__ . '/partials/head.php'; ?>

<!-- page-wrapper Start-->
<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <!-- page header -->
  <?php include __DIR__ . '/partials/page-header.php'; ?>

  <!-- Page Body Start-->
  <div class="page-body-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- page body start -->
    <div class="page-body">
      <div class="container-fluid py-4">

        <style>
          .chip { display:inline-flex; align-items:center; gap:.4rem; padding:.25rem .6rem; border-radius:999px; background:#f7f4f2; color:#5b4a3a; border:1px solid #eee; font-size:.85rem; }
          .chip b { color:#2b2118; }
          .btn-nude { background:<?= nude_color() ?>; color:#fff; border-color:<?= nude_color() ?>; }
          .btn-nude:hover { filter:brightness(.95); color:#fff; }
          .badge-status { font-size:.72rem; }
          .badge-status.active { background: #e8d8c6; color:#523c27; }
          .badge-status.unsubscribed { background:#f6e1e1; color:#7a3030; }
          .table-sticky th { position: sticky; top: 0; background: #fff; z-index: 2; }
          .card-soft { border:1px solid #eee; border-radius:16px; box-shadow: 0 2px 16px rgba(0,0,0,.04); }
          .tox .tox-tbtn--enabled, .tox .tox-tbtn:hover { color: <?= nude_color() ?>; }
          .tox .tox-statusbar { display: none; }
        </style>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <h3 class="mb-0">Newsletter Campaign</h3>
          <div class="d-flex align-items-center gap-2">
            <span class="chip">All <b><?= (int)$totalAll ?></b></span>
            <span class="chip">Active <b><?= (int)$totalActive ?></b></span>
            <span class="chip">Unsubscribed <b><?= (int)$totalUnsub ?></b></span>
          </div>
        </div>

        <?php if ($msg): ?>
          <div class="alert alert-<?= htmlspecialchars($msg['type']) ?>"><?= htmlspecialchars($msg['text']) ?></div>
        <?php endif; ?>

        <div class="row g-4">
          <!-- Composer -->
          <div class="col-lg-6">
            <div class="card-soft p-3 p-md-4">
              <form method="post" id="sendForm">
                <?php if (function_exists('csrf_token')): ?>
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <?php endif; ?>
                <input type="hidden" name="send_mode" id="send_mode" value="selected">

                <div class="mb-3">
                  <label class="form-label">Subject</label>
                  <input type="text" name="subject" class="form-control" placeholder="Summer Collection is Live ✨" required>
                </div>

                <div class="mb-3">
                  <label class="form-label d-flex align-items-center justify-content-between">
                    <span>HTML Body</span><small class="text-muted">Rich text editor enabled</small>
                  </label>
                  <textarea name="html" id="editor_html" class="form-control" rows="12" required>
<h2>New Arrivals</h2>
<p>Discover light, airy pieces in nude and earth tones...</p>
                  </textarea>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                  <button class="btn btn-nude" type="submit" onclick="document.getElementById('send_mode').value='selected'">
                    Send to Selected <span class="ms-1 badge bg-dark-subtle text-dark" id="selCount">0</span>
                  </button>
                  <button class="btn btn-outline-dark" type="submit" onclick="document.getElementById('send_mode').value='filtered'">
                    Send to All Filtered <span class="ms-1 badge bg-secondary-subtle text-dark"><?= (int)$totalFiltered ?></span>
                  </button>
                  <span class="ms-auto small text-muted">Each email includes an unsubscribe link.</span>
                </div>
              </form>
            </div>
          </div>

          <!-- Audience -->
          <div class="col-lg-6">
            <div class="card-soft p-3 p-md-4">
              <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                <div class="flex-grow-1">
                  <label class="form-label small mb-1">Search & Filter</label>
                  <form id="searchForm" method="get" class="d-flex flex-wrap gap-2">
                    <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Email or name">
                    <select class="form-select" name="status" style="max-width:200px">
                      <option value="active" <?= $status==='active'?'selected':''; ?>>Active only</option>
                      <option value="unsubscribed" <?= $status==='unsubscribed'?'selected':''; ?>>Unsubscribed</option>
                      <option value="all" <?= $status==='all'?'selected':''; ?>>All</option>
                    </select>
                    <select class="form-select" name="per" style="max-width:120px">
                      <?php foreach ([25,50,100,200] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $perPage===$opt?'selected':''; ?>><?= $opt ?>/page</option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-dark" type="submit">Apply</button>
                  </form>
                </div>
                <div class="text-end small">
                  <div>Filtered: <b><?= (int)$totalFiltered ?></b></div>
                  <div>Page: <b><?= (int)$page ?></b> / <?= (int)$pages ?></div>
                </div>
              </div>

              <form id="selectForm">
                <div class="table-responsive" style="max-height: 60vh; overflow:auto;">
                  <table class="table align-middle table-sticky">
                    <thead>
                      <tr>
                        <th style="width:36px;">
                          <input class="form-check-input" type="checkbox" id="checkAll">
                        </th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Last Sent</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                      <tr><td colspan="6" class="text-center text-muted py-4">No subscribers match your filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                      <?php $isActive = ($r['status'] === 'active'); ?>
                      <tr>
                        <td>
                          <?php if ($isActive): ?>
                            <input class="form-check-input sub-check" type="checkbox" name="selected[]" value="<?= (int)$r['id'] ?>" form="sendForm">
                          <?php else: ?>
                            <input class="form-check-input" type="checkbox" disabled>
                          <?php endif; ?>
                        </td>
                        <td class="font-monospace"><?= htmlspecialchars($r['email']) ?></td>
                        <td><?= htmlspecialchars($r['name'] ?? '') ?></td>
                        <td>
                          <span class="badge badge-status <?= $isActive ? 'active' : 'unsubscribed' ?>">
                            <?= htmlspecialchars($r['status']) ?>
                          </span>
                        </td>
                        <td class="small text-muted"><?= htmlspecialchars((string)$r['created_at']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($r['last_sent_at'] ?? '—') ?></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </form>

              <?php if ($pages > 1): ?>
                <nav class="d-flex justify-content-between align-items-center mt-2" aria-label="Subscribers">
                  <?php
                    $buildUrl = function(int $p) use ($q,$status,$perPage) {
                      $base = rtrim(BASE_URL, '/').'/admin/newsletter_send.php';
                      $query = http_build_query(['q'=>$q,'status'=>$status,'per'=>$perPage,'page'=>$p]);
                      return $base . '?' . $query;
                    };
                  ?>
                  <a class="btn btn-sm btn-outline-secondary <?= $page<=1?'disabled':'' ?>" href="<?= htmlspecialchars($page<=1?'#':$buildUrl($page-1)) ?>">Prev</a>
                  <span class="small text-muted">Page <b><?= (int)$page ?></b> of <b><?= (int)$pages ?></b></span>
                  <a class="btn btn-sm btn-outline-secondary <?= $page>=$pages?'disabled':'' ?>" href="<?= htmlspecialchars($page>=$pages?'#':$buildUrl($page+1)) ?>">Next</a>
                </nav>
              <?php endif; ?>

            </div>
          </div>
        </div>

      </div>
    </div>
    <!-- page body end -->

  </div>
  <!-- Page Body End -->
</div>
<!-- page-wrapper End-->

<?php include __DIR__ . '/partials/logout.php'; ?>
<?php include __DIR__ . '/partials/script-js.php'; ?>

<!-- TinyMCE (Rich Text Editor) -->
<script src="https://cdn.tiny.cloud/1/r67m9yx0grkw0tus6mgwzjrk0yy1sqaaqfikdxi21ks59nt3/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: '#editor_html',
    menubar: false,
    plugins: 'link lists code image table autoresize',
    toolbar: 'undo redo | styles | bold italic underline | bullist numlist | link image table | removeformat | code',
    branding: false,
    height: 360,
    content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; } a { color: <?= json_encode(nude_color()) ?>; }'
  });
</script>

<script>
(function(){
  const checkAll = document.getElementById('checkAll');
  const checks   = () => Array.from(document.querySelectorAll('.sub-check'));
  const selBadge = document.getElementById('selCount');

  function updateBadge(){
    const n = checks().filter(c => c.checked).length;
    if (selBadge) selBadge.textContent = n;
  }

  if (checkAll) {
    checkAll.addEventListener('change', () => {
      checks().forEach(c => { if (!c.disabled) c.checked = checkAll.checked; });
      updateBadge();
    });
  }
  document.addEventListener('change', (e) => {
    if (e.target && e.target.classList && e.target.classList.contains('sub-check')) {
      updateBadge();
      const cs = checks().filter(c => !c.disabled);
      checkAll.checked = cs.length && cs.every(c => c.checked);
    }
  });
  updateBadge();

  // Guard for "Send to Selected"
  const sendForm = document.getElementById('sendForm');
  if (sendForm) {
    sendForm.addEventListener('submit', (e) => {
      const mode = document.getElementById('send_mode')?.value || 'selected';
      if (mode === 'selected') {
        const n = checks().filter(c => c.checked).length;
        if (!n) {
          e.preventDefault();
          alert('Please select at least one active subscriber on this page, or choose "Send to All Filtered".');
          return false;
        }
      }
    });
  }
})();
</script>
