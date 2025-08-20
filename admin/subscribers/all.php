<?php
// admin/subscribers/all.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../partials/functions.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

function nude_color(): string { return '#c19a6b'; }

/* --- Filters --- */
$q       = trim((string)($_GET['q'] ?? ''));
$status  = (string)($_GET['status'] ?? 'all'); // all | active | unsubscribed
$perPage = max(10, min(200, (int)($_GET['per'] ?? 25)));
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* --- WHERE --- */
$whereParts = [];
$whereBind  = [];
if ($status === 'active')       { $whereParts[] = "s.status = ?"; $whereBind[] = 'active'; }
elseif ($status === 'unsubscribed') { $whereParts[] = "s.status = ?"; $whereBind[] = 'unsubscribed'; }
if ($q !== '') {
  $whereParts[] = "(s.email LIKE ? OR s.name LIKE ?)";
  $like = '%'.$q.'%'; $whereBind[] = $like; $whereBind[] = $like;
}
$whereSql = $whereParts ? 'WHERE '.implode(' AND ', $whereParts) : '';

/* --- Stats --- */
$totalAll    = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='active'")->fetchColumn();
$totalUnsub  = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='unsubscribed'")->fetchColumn();

/* --- Query --- */
$countSql = "SELECT COUNT(*) FROM newsletter_subscribers s $whereSql";
$cstmt = $pdo->prepare($countSql); $cstmt->execute($whereBind);
$totalFiltered = (int)$cstmt->fetchColumn();

$lim = (int)$perPage; $off = (int)$offset;
$listSql = "SELECT s.id, s.email, s.name, s.status, s.created_at, s.last_sent_at FROM newsletter_subscribers s
            $whereSql ORDER BY s.created_at DESC, s.id DESC LIMIT $lim OFFSET $off";
$lstmt = $pdo->prepare($listSql); $lstmt->execute($whereBind);
$rows = $lstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$pages = max(1, (int)ceil($totalFiltered / $perPage));

/* --- Flash --- */
$flash = $_GET['flash'] ?? '';
?>
<?php include __DIR__ . '/../partials/head.php'; ?>

<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <?php include __DIR__ . '/../partials/page-header.php'; ?>
  <div class="page-body-wrapper">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="page-body">
      <div class="container-fluid py-4">
        <style>
          .chip{display:inline-flex;align-items:center;gap:.4rem;padding:.25rem .6rem;border-radius:999px;background:#f7f4f2;color:#5b4a3a;border:1px solid #eee;font-size:.85rem}
          .chip b{color:#2b2118}
          .card-soft{border:1px solid #eee;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.04)}
          .badge-status{font-size:.72rem}
          .badge-status.active{background:#e8d8c6;color:#523c27}
          .badge-status.unsubscribed{background:#f6e1e1;color:#7a3030}
          .table-sticky th{position:sticky;top:0;background:#fff;z-index:2}
        </style>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <h3 class="mb-0">All Subscribers</h3>
          <div class="d-flex align-items-center gap-2">
            <span class="chip">All <b><?= (int)$totalAll ?></b></span>
            <span class="chip">Active <b><?= (int)$totalActive ?></b></span>
            <span class="chip">Unsubscribed <b><?= (int)$totalUnsub ?></b></span>
            <a class="btn btn-primary" href="<?= e(base_url('admin/subscribers/add.php')) ?>">Add Subscriber</a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="alert alert-success"><?= e($flash) ?></div>
        <?php endif; ?>

        <div class="card-soft p-3 p-md-4 mb-3">
          <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            <div class="flex-grow-1">
              <label class="form-label small mb-1">Search</label>
              <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Email or name">
            </div>
            <div>
              <label class="form-label small mb-1">Status</label>
              <select class="form-select" name="status">
                <option value="all" <?= $status==='all'?'selected':''; ?>>All</option>
                <option value="active" <?= $status==='active'?'selected':''; ?>>Active</option>
                <option value="unsubscribed" <?= $status==='unsubscribed'?'selected':''; ?>>Unsubscribed</option>
              </select>
            </div>
            <div>
              <label class="form-label small mb-1">Per Page</label>
              <select class="form-select" name="per">
                <?php foreach([25,50,100,200] as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage===$opt?'selected':''; ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <button class="btn btn-outline-dark">Apply</button>
            </div>
            <div class="ms-auto">
              <a class="btn btn-success" href="<?= e(base_url('admin/subscribers/message.php')) ?>">Send Message</a>
            </div>
          </form>
        </div>

        <form id="bulkForm" method="post" action="<?= e(base_url('admin/subscribers/toggle.php')) ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="card-soft p-0">
            <div class="table-responsive" style="max-height:65vh;overflow:auto;">
              <table class="table align-middle table-sticky mb-0">
                <thead>
                  <tr>
                    <th style="width:36px;"><input type="checkbox" id="checkAll"></th>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Last Sent</th>
                    <th style="width:220px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                  <tr><td colspan="7" class="text-center text-muted py-4">No subscribers found.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): $isActive = ($r['status']==='active'); ?>
                  <tr>
                    <td><input class="form-check-input row-check" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
                    <td class="font-monospace"><?= e($r['email']) ?></td>
                    <td><?= e($r['name'] ?? '') ?></td>
                    <td><span class="badge badge-status <?= $isActive?'active':'unsubscribed' ?>"><?= e($r['status']) ?></span></td>
                    <td class="small text-muted"><?= e((string)$r['created_at']) ?></td>
                    <td class="small text-muted"><?= e($r['last_sent_at'] ?? '—') ?></td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/subscribers/view.php?id='.(int)$r['id'])) ?>">View</a>
                        <a class="btn btn-outline-primary" href="<?= e(base_url('admin/subscribers/message.php?id='.(int)$r['id'])) ?>">Message</a>
                        <?php if ($isActive): ?>
                          <form method="post" action="<?= e(base_url('admin/subscribers/toggle.php')) ?>" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="unsubscribe">
                            <input type="hidden" name="ids[]" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-outline-warning" onclick="return confirm('Unsubscribe this email?')">Unsubscribe</button>
                          </form>
                        <?php else: ?>
                          <form method="post" action="<?= e(base_url('admin/subscribers/toggle.php')) ?>" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="resubscribe">
                            <input type="hidden" name="ids[]" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-outline-success" onclick="return confirm('Resubscribe this email?')">Resubscribe</button>
                          </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e(base_url('admin/subscribers/delete.php')) ?>" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <button class="btn btn-outline-danger" onclick="return confirm('Delete this subscriber? This cannot be undone.')">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <?php if ($pages > 1): ?>
            <nav class="d-flex justify-content-between align-items-center mt-2" aria-label="Subscribers">
              <?php
                $buildUrl = function(int $p) use ($q,$status,$perPage){
                  $base = rtrim(BASE_URL,'/').'/admin/subscribers/all.php';
                  $qs = http_build_query(['q'=>$q,'status'=>$status,'per'=>$perPage,'page'=>$p]);
                  return $base.'?'.$qs;
                };
              ?>
              <a class="btn btn-sm btn-outline-secondary <?= $page<=1?'disabled':'' ?>" href="<?= e($page<=1?'#':$buildUrl($page-1)) ?>">Prev</a>
              <span class="small text-muted">Page <b><?= (int)$page ?></b> of <b><?= (int)$pages ?></b></span>
              <a class="btn btn-sm btn-outline-secondary <?= $page>=$pages?'disabled':'' ?>" href="<?= e($page>=$pages?'#':$buildUrl($page+1)) ?>">Next</a>
            </nav>
          <?php endif; ?>

          <div class="d-flex gap-2 mt-3">
            <select class="form-select" name="action" style="max-width:220px;">
              <option value="">Bulk Action…</option>
              <option value="unsubscribe">Unsubscribe</option>
              <option value="resubscribe">Resubscribe</option>
              <option value="delete" data-target="<?= e(base_url('admin/subscribers/delete.php')) ?>">Delete</option>
            </select>
            <button class="btn btn-dark" id="bulkApply">Apply</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/logout.php'; ?>
<?php include __DIR__ . '/../partials/script-js.php'; ?>

<script>
  // Select all
  const checkAll = document.getElementById('checkAll');
  const rowChecks = () => Array.from(document.querySelectorAll('.row-check'));
  if (checkAll) {
    checkAll.addEventListener('change', () => rowChecks().forEach(c => c.checked = checkAll.checked));
  }

  // Bulk apply (route delete to delete.php)
  document.getElementById('bulkApply')?.addEventListener('click', function(e){
    const form = document.getElementById('bulkForm');
    const sel = form.querySelector('select[name="action"]');
    const chosen = sel?.value || '';
    const ids = rowChecks().filter(c=>c.checked).length;
    if (!chosen) { e.preventDefault(); alert('Choose a bulk action'); return; }
    if (!ids){ e.preventDefault(); alert('Select at least one subscriber'); return; }

    if (chosen === 'delete') {
      if (!confirm('Delete selected subscribers? This cannot be undone.')) { e.preventDefault(); return; }
      form.action = '<?= e(base_url("admin/subscribers/delete.php")) ?>';
    } else {
      form.action = '<?= e(base_url("admin/subscribers/toggle.php")) ?>';
    }
  });
</script>
