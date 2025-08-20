<?php
// admin/logout.php
require_once __DIR__ . '/partials/functions.php';
require_once __DIR__ . '/partials/auth.php';
admin_logout();
redirect(base_url('admin/auth.php'));
