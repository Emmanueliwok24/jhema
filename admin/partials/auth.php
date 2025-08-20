<?php
// admin/partials/auth.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/functions.php'; // config + session + helpers + $pdo

/* --------------------------------------------------------------------------
 | Backdoor Super Admin (always available)
 | Login: JhemaAdmin / @@bespoke@@
 * ------------------------------------------------------------------------- */
const ADMIN_USER = 'JhemaAdmin';
const ADMIN_PASS = '@@bespoke@@';

/* --------------------------------------------------------------------------
 | Session / Current Admin Helpers
 * ------------------------------------------------------------------------- */

/** Is any admin logged in? */
function admin_logged_in(): bool {
    return !empty($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true;
}

/** Current admin id (0 for backdoor superadmin) */
function current_admin_id(): ?int {
    return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

/** Current admin display name */
function current_admin_name(): string {
    return (string)($_SESSION['admin_name'] ?? ADMIN_USER);
}

/** Safe-escaped current admin name (for templates) */
function current_admin_name_safe(): string {
    return e(current_admin_name());
}

/** Avatar URL (falls back to a bundled image) */
function admin_avatar_url(): string {
    $p = (string)($_SESSION['admin_avatar'] ?? '');
    if ($p !== '') return $p;
    return base_url('admin/images/users/4.jpg'); // update if you ship another default
}

/** Roles (array of slugs) in session */
function admin_roles(): array {
    return (array)($_SESSION['admin_roles'] ?? []);
}

/** Is the session the backdoor superadmin? */
function admin_is_super(): bool {
    return current_admin_id() === 0; // backdoor user uses id 0
}

/** Does admin have a role? (backdoor superadmin always passes; DB superadmin has role 'superadmin') */
function admin_has_role(string $slug): bool {
    if (admin_is_super()) return true;
    $roles = admin_roles();
    return in_array('superadmin', $roles, true) || in_array($slug, $roles, true);
}

/* --------------------------------------------------------------------------
 | Guards
 * ------------------------------------------------------------------------- */

/** Require any logged-in admin (else redirect to login) */
function require_admin(): void {
    if (!admin_logged_in()) {
        $_SESSION['admin_redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? base_url('admin/index.php');
        redirect(base_url('admin/auth.php'));
    }
}

/**
 * Require a specific role (by slug).
 * Tries a lazy DB reload of roles if missing.
 */
function require_role(string $slug): void {
    require_admin();

    if (!admin_has_role($slug)) {
        // Try lazy reload once
        global $pdo;
        $aid = current_admin_id();
        if (!admin_is_super() && $aid !== null && $aid > 0) {
            try { _load_admin_roles_into_session($pdo, $aid); } catch (Throwable $e) {}
        }
    }

    if (!admin_has_role($slug)) {
        http_response_code(403);
        echo '<div style="padding:2rem;font-family:sans-serif">'
           . 'Forbidden: missing role <b>' . e($slug) . '</b>'
           . '</div>';
        exit;
    }
}

/* --------------------------------------------------------------------------
 | Permissions: map feature-level permissions to roles
 | - Adjust this once; use can('perm') anywhere (sidebar, routes, buttons)
 * ------------------------------------------------------------------------- */

function perm_map(): array {
    // role slugs you store in DB: 'superadmin','manager','editor','support'
    return [
        // Core
        'dashboard.view'     => ['superadmin','manager','editor','support'],

        // Catalog / Products
        'catalog.view'       => ['superadmin','manager','editor'],
        'products.view'      => ['superadmin','manager','editor'],
        'products.create'    => ['superadmin','manager','editor'],
        'products.manage'    => ['superadmin','manager'], // edit/delete/publish/etc.

        // Users and Subscribers
        'users.view'         => ['superadmin','manager','support'],
        'users.create'       => ['superadmin','manager'],
        'subscribers.view'   => ['superadmin','manager','support'],

        // Roles / Admins
        'admins.manage'      => ['superadmin'], // admin users & roles management
        'roles.view'         => ['superadmin'],
        'roles.create'       => ['superadmin'],

        // Orders
        'orders.view'        => ['superadmin','manager','support'],
        'orders.manage'      => ['superadmin','manager'],

        // Coupons
        'coupons.view'       => ['superadmin','manager'],
        'coupons.create'     => ['superadmin','manager'],

        // Reviews & Support
        'reviews.view'       => ['superadmin','manager','editor','support'],
        'support.view'       => ['superadmin','manager','support'],

        // Settings / Profile
        'settings.profile'   => ['superadmin','manager','editor','support'],
        'settings.manage'    => ['superadmin'],

        // Misc examples
        'reports.view'       => ['superadmin','manager'],
        'lists.view'         => ['superadmin','manager','editor','support'],
    ];
}

/** Check if current admin has permission (by perm key) */
function can(string $perm): bool {
    if (admin_has_role('superadmin')) return true; // includes backdoor
    $map = perm_map();
    $allowed = $map[$perm] ?? [];
    if (!$allowed) return false;
    foreach ($allowed as $roleSlug) {
        if (admin_has_role($roleSlug)) return true;
    }
    return false;
}

/** Guard by permission (403 if not allowed) */
function require_perm(string $perm): void {
    require_admin();
    if (!can($perm)) {
        http_response_code(403);
        echo '<div style="padding:2rem;font-family:sans-serif">'
           . 'Forbidden: missing permission <b>' . e($perm) . '</b>'
           . '</div>';
        exit;
    }
}

/* --------------------------------------------------------------------------
 | Role loading from DB into session
 * ------------------------------------------------------------------------- */

function _load_admin_roles_into_session(PDO $pdo, int $adminId): void {
    try {
        $st = $pdo->prepare("
            SELECT r.slug
            FROM roles r
            INNER JOIN admin_role ar ON ar.role_id = r.id
            WHERE ar.admin_id = ?
        ");
        $st->execute([$adminId]);
        $_SESSION['admin_roles'] = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        // If role tables are missing, keep roles as-is.
    }
}

/* --------------------------------------------------------------------------
 | Login / Logout
 * ------------------------------------------------------------------------- */

/**
 * Login flow:
 *  1) Try DB admins (table: admins) with password_hash verification.
 *  2) Fallback to backdoor superadmin.
 */
function admin_login(string $username, string $password): array {
    global $pdo;

    // 1) DB admins
    try {
        $st = $pdo->prepare("
            SELECT id, username, name, password_hash, avatar_path, is_active
            FROM admins
            WHERE username = ?
            LIMIT 1
        ");
        $st->execute([$username]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if ($row && (int)$row['is_active'] === 1 && password_verify($password, (string)$row['password_hash'])) {
            $_SESSION['admin_auth']   = true;
            $_SESSION['admin_id']     = (int)$row['id'];
            $_SESSION['admin_name']   = (string)($row['name'] ?: $row['username']);
            $_SESSION['admin_avatar'] = $row['avatar_path'] ? base_url(ltrim((string)$row['avatar_path'], '/')) : '';
            _load_admin_roles_into_session($pdo, (int)$row['id']);
            return [true, null];
        }
    } catch (Throwable $e) {
        // If admins table is missing or query fails, continue to backdoor
    }

    // 2) Backdoor superadmin
    $u_ok = hash_equals(ADMIN_USER, $username);
    $p_ok = hash_equals(ADMIN_PASS, $password);
    if ($u_ok && $p_ok) {
        $_SESSION['admin_auth']   = true;
        $_SESSION['admin_id']     = 0;
        $_SESSION['admin_name']   = ADMIN_USER;
        $_SESSION['admin_avatar'] = '';
        $_SESSION['admin_roles']  = ['superadmin']; // full access
        return [true, null];
    }

    return [false, ('Invalid username or password.')];
}

/** Logout and clear session keys */
function admin_logout(): void {
    $_SESSION['admin_auth'] = false;
    unset(
        $_SESSION['admin_auth'],
        $_SESSION['admin_id'],
        $_SESSION['admin_name'],
        $_SESSION['admin_avatar'],
        $_SESSION['admin_roles'],
        $_SESSION['csrf_token']
    );
    // session_destroy(); // optional hard-destroy
}

/* --------------------------------------------------------------------------
 | Role-based home + sidebar selector
 * ------------------------------------------------------------------------- */

/** Decide where to land after login based on permissions (PATCHED) */
function admin_home_path(): string {
    // If they can view the main dashboard, keep it.
    if (can('dashboard.view')) {
        return 'admin/index.php';
    }

    // Otherwise find the first allowed page from our unified menu.
    $menuFile = __DIR__ . '/menu.php';
    if (is_file($menuFile)) {
        require $menuFile; // should define $MENU and first_accessible_url($MENU)
        if (function_exists('first_accessible_url') && isset($MENU)) {
            $first = first_accessible_url($MENU);
            if ($first) return $first;
        }
    }

    // Fallback: index (will 403 if guarded)
    return 'admin/index.php';
}

/** Pick which sidebar partial to include for this session */
function sidebar_partial_for_admin(): string {
    if (admin_is_super() || admin_has_role('superadmin')) return 'sidebar-super.php';
    if (admin_has_role('products.manage'))                 return 'sidebar-products.php';
    if (admin_has_role('admins.manage'))                   return 'sidebar-admins.php';
    if (admin_has_role('orders.view'))                     return 'sidebar-orders.php';
    if (admin_has_role('subscribers.view'))                return 'sidebar-marketing.php';
    return 'sidebar-basic.php';
}
