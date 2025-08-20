<?php
// admin/partials/sidebar.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

$esc  = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$href = fn(string $p='') => $esc(base_url($p));

$MENU = [
  ['label'=>'Dashboard', 'icon'=>'ri-home-line', 'url'=>'admin/index.php'],

  ['label'=>'Product', 'icon'=>'ri-store-3-line', 'children'=>[
      ['label'=>'Products',          'url'=>'admin/products/manage-products.php'],
      ['label'=>'Add New Products',  'url'=>'admin/products/add-product.php'],
      ['label'=>'Manage Products',   'url'=>'admin/products/manage-products.php'],
  ]],

  ['label'=>'Users', 'icon'=>'ri-user-3-line', 'children'=>[
      ['label'=>'Newsletters',       'url'=>'admin/newsletters.php'],
      ['label'=>'All Users',         'url'=>'admin/users/all-users.php'],
      ['label'=>'Add New User',      'url'=>'admin/users/add-new-user.php'],
      ['label'=>'Subscribers',       'url'=>'admin/subscribers/all.php'],
  ]],

  ['label'=>'Roles', 'icon'=>'ri-user-3-line', 'children'=>[
      ['label'=>'All Roles',         'url'=>'admin/roles/role.php'],
      ['label'=>'Create Role',       'url'=>'admin/roles/create-role.php'],
  ]],

  ['label'=>'Orders', 'icon'=>'ri-archive-line', 'children'=>[
      ['label'=>'Order List',        'url'=>'admin/orders/index.php'],
      ['label'=>'Order Detail',      'url'=>'admin/orders/view.php'],
      ['label'=>'Order Tracking',    'url'=>'admin/orders/order-tracking.php'],
  ]],

  ['label'=>'Coupons', 'icon'=>'ri-price-tag-3-line', 'children'=>[
      ['label'=>'Coupon List',       'url'=>'admin/coupons/coupon-list.php'],
      ['label'=>'Create Coupon',     'url'=>'admin/coupons/create-coupon.php'],
  ]],

  ['label'=>'Product Review', 'icon'=>'ri-star-line', 'url'=>'admin/reviews/product-review.php'],
  ['label'=>'Support Ticket', 'icon'=>'ri-phone-line','url'=>'admin/support/support-ticket.php'],

  ['label'=>'Settings', 'icon'=>'ri-settings-line', 'children'=>[
      ['label'=>'Profile Setting',   'url'=>'admin/settings/profile-setting.php'],
  ]],

  ['label'=>'List Page', 'icon'=>'ri-list-check', 'url'=>'admin/list-page.php'],
  ['label'=>'Admin Users', 'icon'=>'ri-shield-user-line', 'url'=>'admin/admins/index.php'],
];

function render_menu(array $items, callable $href): void {
    foreach ($items as $it) {
        $label = $it['label'] ?? 'Item';
        $icon  = $it['icon']  ?? 'ri-checkbox-blank-line';
        $url   = $it['url']   ?? '#';
        $kids  = $it['children'] ?? null;

        if ($kids && is_array($kids)) {
            echo '<li class="sidebar-list">';
            echo '  <a class="linear-icon-link sidebar-link sidebar-title" href="javascript:void(0)">';
            echo '    <i class="'.$icon.'"></i><span>'.$label.'</span>';
            echo '  </a>';
            echo '  <ul class="sidebar-submenu">';
            foreach ($kids as $c) {
                $clabel = $c['label'] ?? 'Item';
                $curl   = $c['url']   ?? '#';
                echo '    <li><a href="'.$href($curl).'">'.$clabel.'</a></li>';
            }
            echo '  </ul>';
            echo '</li>';
        } else {
            echo '<li class="sidebar-list">';
            echo '  <a class="sidebar-link sidebar-title link-nav" href="'.$href($url).'">';
            echo '    <i class="'.$icon.'"></i><span>'.$label.'</span>';
            echo '  </a>';
            echo '</li>';
        }
    }

    // Logout
    echo '<li class="sidebar-list">';
    echo '  <a data-bs-toggle="modal" data-bs-target="#staticBackdrop" class="sidebar-link sidebar-title link-nav" href="javascript:void(0)">';
    echo '    <i class="ri-logout-box-line"></i><span>Log out</span>';
    echo '  </a>';
    echo '</li>';
}
?>
<!-- Page Sidebar Start-->
<div class="sidebar-wrapper">
  <div>
    <div class="logo-wrapper logo-wrapper-center">
      <a href="<?= $href('admin/index.php') ?>" title="Dashboard">
        <img class="img-fluid" style="max-width:100px;filter:brightness(0) invert(1);" src="<?= $href('images/logo.svg') ?>" alt="logo">
      </a>
      <div class="back-btn"><i class="fa fa-angle-left"></i></div>
      <div class="toggle-sidebar"><i class="ri-apps-line status_toggle middle sidebar-toggle"></i></div>
    </div>

    <nav class="sidebar-main">
      <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
      <div id="sidebar-menu">
        <ul class="sidebar-links" id="simple-bar">
          <li class="back-btn"></li>
          <?php render_menu($MENU, $href); ?>
        </ul>
      </div>
    </nav>
  </div>
</div>
<!-- Page Sidebar Ends-->
