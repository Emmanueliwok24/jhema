<?php
// includes/wishlist.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
if (file_exists(__DIR__ . '/auth.php')) { require_once __DIR__ . '/auth.php'; }

/* ----------------------- Helpers ----------------------- */
function wl_json(array $data, int $code = 200): void {
  // Only attempt header ops if headers aren't already sent
  if (!headers_sent()) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    // Hide X-Powered-By if possible
    if (function_exists('header_remove')) { @header_remove('X-Powered-By'); }
  }
  echo json_encode($data);
  exit;
}

function wl_user_id(): ?int {
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}
function wl_find_product_id(PDO $pdo, string $slug): ?int {
  $st = $pdo->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
  $st->execute([$slug]);
  $id = $st->fetchColumn();
  return $id ? (int)$id : null;
}
function wl_add(PDO $pdo, int $uid, int $pid): bool {
  $st = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id, created_at) VALUES (?, ?, NOW())");
  return $st->execute([$uid, $pid]);
}
function wl_remove(PDO $pdo, int $uid, int $pid): bool {
  $st = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
  return $st->execute([$uid, $pid]);
}
function wl_has(PDO $pdo, int $uid, int $pid): bool {
  $st = $pdo->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
  $st->execute([$uid, $pid]);
  return (bool)$st->fetchColumn();
}
function wl_count(PDO $pdo, int $uid): int {
  $st = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
  $st->execute([$uid]);
  return (int)$st->fetchColumn();
}

/**
 * Back-compat: normalize legacy AJAX params:
 * - action / wishlist_action / op
 * - product_slug / slug / product
 */
function wl_normalize_request(): array {
  $action = $_POST['wishlist_action'] ?? $_POST['action'] ?? $_POST['op'] ?? '';
  $action = strtolower((string)$action);
  $aliases = [
    'add_to_wishlist'     => 'add',
    'remove_from_wishlist'=> 'remove',
    'toggle_wishlist'     => 'toggle',
    'list_wishlist'       => 'list',
    'get'                 => 'list'
  ];
  if (isset($aliases[$action])) $action = $aliases[$action];
  $slug = $_POST['product_slug'] ?? $_POST['slug'] ?? $_POST['product'] ?? '';
  return [$action, trim((string)$slug)];
}

/* ----------------------- API ----------------------- */
if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  (isset($_POST['wishlist_action']) || isset($_POST['action']) || isset($_POST['op']))
) {
  // Only inside the API branch, attempt to remove headers if still possible
  if (!headers_sent() && function_exists('header_remove')) { @header_remove('X-Powered-By'); }

  // Optional CSRF (skip safely if you don't use it elsewhere)
  if (function_exists('csrf_check') && !csrf_check($_POST['csrf_token'] ?? '')) {
    wl_json(['ok' => false, 'msg' => 'Invalid session. Please refresh the page.'], 400);
  }

  if (!isset($pdo) || !$pdo instanceof PDO) {
    wl_json(['ok' => false, 'msg' => 'Server unavailable.'], 503);
  }

  $uid = wl_user_id();
  if (!$uid) {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : (function_exists('base_url') ? rtrim(base_url(''), '/') : '');
    $redir = $_SERVER['HTTP_REFERER'] ?? ($base . '/');
    $login = $base . '/account/auth.php?tab=login&redirect=' . rawurlencode($redir);
    wl_json(['ok' => false, 'login' => true, 'login_url' => $login], 401);
  }

  [$act, $slug] = wl_normalize_request();

  if ($act !== 'list' && $slug === '') {
    wl_json(['ok' => false, 'msg' => 'Missing product.'], 400);
  }

  if ($act !== 'list') {
    $pid = wl_find_product_id($pdo, $slug);
    if (!$pid) wl_json(['ok' => false, 'msg' => 'Product not found.'], 404);
  }

  if ($act === 'add') {
    wl_add($pdo, $uid, $pid);
    wl_json(['ok' => true, 'action' => 'added', 'count' => wl_count($pdo, $uid)]);
  } elseif ($act === 'remove') {
    wl_remove($pdo, $uid, $pid);
    wl_json(['ok' => true, 'action' => 'removed', 'count' => wl_count($pdo, $uid)]);
  } elseif ($act === 'toggle') {
    if (wl_has($pdo, $uid, $pid)) {
      wl_remove($pdo, $uid, $pid);
      wl_json(['ok' => true, 'action' => 'removed', 'count' => wl_count($pdo, $uid)]);
    } else {
      wl_add($pdo, $uid, $pid);
      wl_json(['ok' => true, 'action' => 'added', 'count' => wl_count($pdo, $uid)]);
    }
  } elseif ($act === 'list') {
    $st = $pdo->prepare("
      SELECT p.slug
      FROM wishlist w
      JOIN products p ON p.id = w.product_id
      WHERE w.user_id = ?
    ");
    $st->execute([$uid]);
    $slugs = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    wl_json(['ok' => true, 'items' => $slugs, 'count' => count($slugs)]);
  }

  wl_json(['ok' => false, 'msg' => 'Unknown action.'], 400);
}

/* ----------------------- Front-end JS (emitted once) ----------------------- */
if (!defined('WISHLIST_SCRIPT_EMITTED')) {
  define('WISHLIST_SCRIPT_EMITTED', true);

  $base = defined('BASE_URL')
            ? rtrim(BASE_URL, '/')
            : (function_exists('base_url') ? rtrim(base_url(''), '/') : '');
  $endpoint = $base . '/includes/wishlist.php';
  $csrf     = function_exists('csrf_token') ? csrf_token() : '';
  ?>
  <script>
  (function () {
    "use strict";
    if (window.__WISHLIST_BOUND__) return;
    window.__WISHLIST_BOUND__ = true;

    const EP   = <?= json_encode($endpoint) ?>;
    const CSRF = <?= json_encode($csrf) ?>;

    /* ------------- Utilities ------------- */
    function post(data) {
      const fd = new FormData();
      Object.entries(data).forEach(([k, v]) => fd.append(k, v));
      return fetch(EP, { method: 'POST', credentials: 'same-origin', body: fd })
        .then(r => r.json().catch(() => ({ ok: false, msg: 'Bad response' })))
        .catch(() => ({ ok: false, msg: 'Network error' }));
    }

    function toast(msg) {
      try {
        const el = document.createElement('div');
        el.className = 'position-fixed top-0 start-50 translate-middle-x alert alert-info shadow py-2 px-3';
        el.style.zIndex = 3000;
        el.style.pointerEvents = 'none';
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 1400);
      } catch (e) {}
    }

    function updateBadge(count) {
      const n = Number(count) || 0;
      document.querySelectorAll('[data-wishlist-count], .wishlist-amount').forEach(b => {
        b.textContent = n;
        if ('hidden' in b) b.hidden = !n;
      });
    }

    function setHeartIcon(btn, inWL) {
      btn.classList.toggle('active', !!inWL);
      btn.dataset.inWishlist = inWL ? '1' : '0';
      btn.setAttribute('aria-pressed', inWL ? 'true' : 'false');
      const use = btn.querySelector('use');
      if (use) use.setAttribute('href', inWL ? '#icon_heart_fill' : '#icon_heart');
      const addLabel = btn.getAttribute('data-label-add') || 'Add To Wishlist';
      const rmLabel  = btn.getAttribute('data-label-remove') || 'Remove From Wishlist';
      const labelEl  = btn.querySelector('.js-wl-label');
      if (labelEl) labelEl.textContent = inWL ? rmLabel : addLabel;
      else if (btn.hasAttribute('title')) btn.setAttribute('title', inWL ? rmLabel : addLabel);
    }

    function setAllForSlug(slug, inWL) {
      const safe = (window.CSS && CSS.escape) ? CSS.escape(slug) : slug.replace(/"/g, '\\"');
      document.querySelectorAll(`[data-product="${safe}"]`).forEach(btn => setHeartIcon(btn, inWL));
    }

    function onWishlistPage() {
      return document.body.classList.contains('wishlist-page') || !!document.getElementById('wl-empty');
    }

    function removeWishlistCardForBtn(btn) {
      const card = btn.closest('.product-card-wrapper');
      if (card) card.remove();
      if (!document.querySelector('.product-card-wrapper')) {
        const empty = document.getElementById('wl-empty');
        if (empty) empty.classList.remove('d-none');
      }
    }

    function handleClick(e) {
      const btn = e.target.closest('.js-add-wishlist, .pc__btn-wl, .js-remove-wishlist');
      if (!btn) return;

      const slug = btn.dataset.product;
      if (!slug) return;

      e.preventDefault();

      const isRemoveBtn = btn.classList.contains('js-remove-wishlist');
      const inWL = btn.dataset.inWishlist === '1' || btn.classList.contains('active') || btn.classList.contains('is-in-wishlist');
      const action = isRemoveBtn ? 'remove' : (inWL ? 'remove' : 'add');

      btn.disabled = true;
      post({ wishlist_action: action, product_slug: slug, csrf_token: CSRF })
        .then(res => {
          if (res && res.login && res.login_url) { window.location.href = res.login_url; return; }
          if (!res || !res.ok) { toast(res && res.msg ? res.msg : 'Could not update wishlist'); return; }

          if (action === 'remove') {
            setAllForSlug(slug, false);
            toast('Removed from wishlist');
            if (onWishlistPage()) removeWishlistCardForBtn(btn);
          } else {
            setAllForSlug(slug, true);
            toast('Added to wishlist');
          }
          if (typeof res.count !== 'undefined') updateBadge(res.count);
        })
        .finally(() => { btn.disabled = false; });
    }
    document.addEventListener('click', handleClick);

    function initialSync() {
      post({ wishlist_action: 'list', csrf_token: CSRF }).then(res => {
        if (!res || !res.ok) return;
        const set = new Set(res.items || []);
        document.querySelectorAll('[data-product]').forEach(btn => {
          const slug = btn.dataset.product;
          if (slug && set.has(slug)) setHeartIcon(btn, true);
        });
        if (typeof res.count !== 'undefined') updateBadge(res.count);
      });
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initialSync);
    } else {
      initialSync();
    }
  })();
  </script>
  <?php
}
// No closing PHP tag to avoid accidental output
