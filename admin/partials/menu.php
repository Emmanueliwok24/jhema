<?php
// admin/partials/menu.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

// DO NOT require auth.php here (auth.php will include this file when it needs it).
// can() MUST already be defined by the time you include this file.

$MENU = [
  ['label'=>'Dashboard', 'icon'=>'ri-home-line', 'url'=>'admin/index.php', 'perm'=>'dashboard.view'],

  ['label'=>'Product', 'icon'=>'ri-store-3-line', 'perm'=>'catalog.view', 'children'=>[
      ['label'=>'Products',          'url'=>'admin/products/manage-products.php', 'perm'=>'products.view'],
      ['label'=>'Add New Products',  'url'=>'admin/products/add-product.php',     'perm'=>'products.create'],
      ['label'=>'Manage Products',   'url'=>'admin/products/manage-products.php', 'perm'=>'products.manage'],
  ]],

  ['label'=>'Users', 'icon'=>'ri-user-3-line', 'perm'=>'users.view', 'children'=>[
      ['label'=>'Newsletters',       'url'=>'admin/newsletters.php',             'perm'=>'users.view'],
      ['label'=>'All Users',         'url'=>'admin/users/all-users.php',         'perm'=>'users.view'],
      ['label'=>'Add New User',      'url'=>'admin/users/add-new-user.php',      'perm'=>'users.create'],
      ['label'=>'Subscribers',       'url'=>'admin/subscribers/all.php',         'perm'=>'subscribers.view'],
  ]],

  ['label'=>'Roles', 'icon'=>'ri-user-3-line', 'perm'=>'roles.view', 'children'=>[
      ['label'=>'All Roles',         'url'=>'admin/roles/role.php',              'perm'=>'roles.view'],
      ['label'=>'Create Role',       'url'=>'admin/roles/create-role.php',       'perm'=>'roles.create'],
  ]],

  ['label'=>'Orders', 'icon'=>'ri-archive-line', 'perm'=>'orders.view', 'children'=>[
      ['label'=>'Order List',        'url'=>'admin/orders/order-list.php',       'perm'=>'orders.view'],
      ['label'=>'Order Detail',      'url'=>'admin/orders/order-detail.php',     'perm'=>'orders.view'],
      ['label'=>'Order Tracking',    'url'=>'admin/orders/order-tracking.php',   'perm'=>'orders.view'],
  ]],

  ['label'=>'Coupons', 'icon'=>'ri-price-tag-3-line', 'perm'=>'coupons.view', 'children'=>[
      ['label'=>'Coupon List',       'url'=>'admin/coupons/coupon-list.php',     'perm'=>'coupons.view'],
      ['label'=>'Create Coupon',     'url'=>'admin/coupons/create-coupon.php',   'perm'=>'coupons.create'],
  ]],

  ['label'=>'Product Review', 'icon'=>'ri-star-line', 'url'=>'admin/reviews/product-review.php', 'perm'=>'reviews.view'],
  ['label'=>'Support Ticket', 'icon'=>'ri-phone-line','url'=>'admin/support/support-ticket.php', 'perm'=>'support.view'],

  ['label'=>'Settings', 'icon'=>'ri-settings-line', 'perm'=>'settings.profile', 'children'=>[
      ['label'=>'Profile Setting',   'url'=>'admin/settings/profile-setting.php', 'perm'=>'settings.profile'],
  ]],

  ['label'=>'List Page', 'icon'=>'ri-list-check', 'url'=>'admin/list-page.php', 'perm'=>'lists.view'],

  ['label'=>'Admin Users', 'icon'=>'ri-shield-user-line', 'url'=>'admin/admins/index.php', 'perm'=>'admins.manage'],
];

/** Depth-first search for the first link the current admin can access */
function first_accessible_url(array $items): ?string {
    foreach ($items as $it) {
        $perm = $it['perm'] ?? null;
        if ($perm && function_exists('can') && !can($perm)) continue;

        if (!empty($it['children']) && is_array($it['children'])) {
            $u = first_accessible_url($it['children']);
            if ($u) return $u;
        } else {
            if (!empty($it['url'])) return (string)$it['url'];
        }
    }
    return null;
}
