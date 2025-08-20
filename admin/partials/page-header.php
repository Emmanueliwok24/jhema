<?php
// admin/partials/page-header.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Non-super: slim header, no global nav
if (!admin_has_role('superadmin')): ?>
<header class="container d-flex align-items-center justify-content-between py-2">
  <a class="navbar-brand fw-bold text-decoration-none" href="<?= $esc(base_url('admin/index.php')) ?>">Admin</a>
  <div class="d-flex align-items-center gap-3">
    <span class="text-muted small">
      <?= $esc(current_admin_name()) ?>
      <?php $roles = admin_roles(); if ($roles) echo ' · ' . $esc(implode(', ', $roles)); ?>
    </span>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $esc(base_url('admin/logout.php')) ?>">Logout</a>
  </div>
</header>
<?php return; endif; ?>

<!-- Superadmin: full header -->
<header class="container d-flex align-items-center justify-content-between py-3">
  <a class="navbar-brand fw-bold text-decoration-none" href="<?= $esc(base_url('admin/index.php')) ?>">Super Admin</a>
  <nav class="d-flex align-items-center gap-3">
    <?php if (can('admins.manage')): ?>
      <a class="text-decoration-none" href="<?= $esc(base_url('admin/admins/index.php')) ?>">Admin Users</a>
    <?php endif; ?>
    <?php if (can('settings.manage')): ?>
      <a class="text-decoration-none" href="<?= $esc(base_url('admin/settings/index.php')) ?>">Settings</a>
    <?php endif; ?>
  </nav>
  <div class="d-flex align-items-center gap-3">
    <span class="text-muted small"><?= $esc(current_admin_name()) ?> (superadmin)</span>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $esc(base_url('admin/logout.php')) ?>">Logout</a>
  </div>
</header>
