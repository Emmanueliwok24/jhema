<?php
// admin/index.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/partials/functions.php';
require_once __DIR__ . '/partials/auth.php';
require_admin();

$showFallback = false;

// If admin cannot see the dashboard, try to send them to the first allowed page.
// If they have no allowed pages, show a minimal screen with Logout.
if (!can('dashboard.view')) {
    require_once __DIR__ . '/partials/menu.php'; // defines $MENU + first_accessible_url()
    $first = first_accessible_url($MENU);
    if ($first) {
        redirect(base_url($first));
    } else {
        $showFallback = true;
    }
}

include __DIR__ . "/partials/head.php";
?>
<!-- page-wrapper Start-->
<div class="page-wrapper compact-wrapper" id="pageWrapper">
  <!-- page header -->
  <?php include __DIR__ . "/partials/page-header.php"; ?>

  <!-- Page Body Start-->
  <div class="page-body-wrapper">
    <?php include __DIR__ . "/partials/sidebar.php"; ?>

    <!-- index body start -->
    <div class="page-body">
      <div class="container-fluid">
        <?php if ($showFallback): ?>
          <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
              <div class="card o-hidden card-hover border-0 shadow-sm">
                <div class="card-body text-center py-5">
                  <h4 class="mb-2">No pages assigned to your account</h4>
                  <p class="text-muted mb-4">
                    Please contact a super admin to grant you access.
                  </p>
                  <a class="btn btn-outline-secondary me-2"
                     data-bs-toggle="modal" data-bs-target="#staticBackdrop"
                     href="javascript:void(0)">
                    Log out
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="row">
            <!-- ===== Your original dashboard content (unchanged) ===== -->
            <div class="col-sm-6 col-xxl-3 col-lg-6">
              <div class="main-tiles border-5 border-0 card-hover card o-hidden">
                <div class="custome-1-bg b-r-4 card-body">
                  <div class="media align-items-center static-top-widget">
                    <div class="media-body p-0">
                      <span class="m-0">Total Revenue</span>
                      <h4 class="mb-0 counter">$6659
                        <span class="badge badge-light-primary grow">
                          <i data-feather="trending-up"></i>8.5%</span>
                      </h4>
                    </div>
                    <div class="align-self-center text-center">
                      <i class="ri-database-2-line"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-sm-6 col-xxl-3 col-lg-6">
              <div class="main-tiles border-5 card-hover border-0 card o-hidden">
                <div class="custome-2-bg b-r-4 card-body">
                  <div class="media static-top-widget">
                    <div class="media-body p-0">
                      <span class="m-0">Total Orders</span>
                      <h4 class="mb-0 counter">9856
                        <span class="badge badge-light-danger grow">
                          <i data-feather="trending-down"></i>8.5%</span>
                      </h4>
                    </div>
                    <div class="align-self-center text-center">
                      <i class="ri-shopping-bag-3-line"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-sm-6 col-xxl-3 col-lg-6">
              <div class="main-tiles border-5 card-hover border-0  card o-hidden">
                <div class="custome-3-bg b-r-4 card-body">
                  <div class="media static-top-widget">
                    <div class="media-body p-0">
                      <span class="m-0">Total Products</span>
                      <h4 class="mb-0 counter">893
                        <a href="add-new-product.php" class="badge badge-light-secondary grow">ADD NEW</a>
                      </h4>
                    </div>
                    <div class="align-self-center text-center">
                      <i class="ri-chat-3-line"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-sm-6 col-xxl-3 col-lg-6">
              <div class="main-tiles border-5 card-hover border-0 card o-hidden">
                <div class="custome-4-bg b-r-4 card-body">
                  <div class="media static-top-widget">
                    <div class="media-body p-0">
                      <span class="m-0">Total Customers</span>
                      <h4 class="mb-0 counter">4.6k
                        <span class="badge badge-light-success grow">
                          <i data-feather="trending-down"></i>8.5%</span>
                      </h4>
                    </div>
                    <div class="align-self-center text-center">
                      <i class="ri-user-add-line"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Earning chart -->
            <div class="col-xl-6">
              <div class="card o-hidden card-hover">
                <div class="card-header border-0 pb-1">
                  <div class="card-header-title"><h4>Revenue Report</h4></div>
                </div>
                <div class="card-body p-0">
                  <div id="report-chart"></div>
                </div>
              </div>
            </div>

            <!-- Recent orders -->
            <div class="col-xl-6">
              <div class="card o-hidden card-hover">
                <div class="card-header card-header-top card-header--2 px-0 pt-0">
                  <div class="card-header-title"><h4>Recent Orders</h4></div>
                  <div class="best-selling-box d-sm-flex d-none">
                    <span>Short By:</span>
                    <div class="dropdown">
                      <button class="btn p-0 dropdown-toggle" type="button" id="dropdownMenuButton2" data-bs-toggle="dropdown" data-bs-auto-close="true">Today</button>
                      <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton2">
                        <li><a class="dropdown-item" href="#">Action</a></li>
                        <li><a class="dropdown-item" href="#">Another action</a></li>
                        <li><a class="dropdown-item" href="#">Something else here</a></li>
                      </ul>
                    </div>
                  </div>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="best-selling-table table border-0">
                      <tbody>
                        <tr>
                          <td>
                            <div class="best-product-box">
                              <div class="product-name">
                                <h5>DRESS</h5>
                                <h6>#64548</h6>
                              </div>
                            </div>
                          </td>
                          <td>
                            <div class="product-detail-box">
                              <h6>Date Placed</h6>
                              <h5>5/1/22</h5>
                            </div>
                          </td>
                          <td>
                            <div class="product-detail-box">
                              <h6>Price</h6>
                              <h5>$250.00</h5>
                            </div>
                          </td>
                          <td>
                            <div class="product-detail-box">
                              <h6>Order Status</h6>
                              <h5>Completed</h5>
                            </div>
                          </td>
                          <td>
                            <div class="product-detail-box">
                              <h6>Payment</h6>
                              <h5 class="text-danger">Unpaid</h5>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

              </div>
            </div>

            <!-- Earning chart -->
            <div class="col-xxl-4  col-md-6">
              <div class="card o-hidden card-hover">
                <div class="card-header border-0 mb-0">
                  <div class="card-header-title"><h4>Earning</h4></div>
                </div>
                <div class="card-body p-0">
                  <div id="bar-chart-earning"></div>
                </div>
              </div>
            </div>

            <!-- Transactions -->
            <div class="col-xxl-4 col-md-6">
              <div class="card o-hidden card-hover">
                <div class="card-header border-0">
                  <div class="card-header-title"><h4>Transactions</h4></div>
                </div>
                <div class="card-body pt-0">
                  <div class="table-responsive">
                    <table class="user-table transactions-table table border-0">
                      <tbody>
                        <tr>
                          <td>
                            <div class="transactions-icon">
                              <i class="ri-shield-line"></i>
                            </div>
                            <div class="transactions-name">
                              <h6>Wallets</h6>
                              <p>Starbucks</p>
                            </div>
                          </td>
                          <td class="lost">-$74</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Visitors chart -->
            <div class="col-xxl-4 col-md-6">
              <div class="h-100">
                <div class="card o-hidden card-hover">
                  <div class="card-header border-0">
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="card-header-title"><h4>Visitors</h4></div>
                    </div>
                  </div>
                  <div class="card-body pt-0">
                    <div class="pie-chart">
                      <div id="pie-chart-visitors"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /.row -->
        <?php endif; ?>
      </div><!-- /.container-fluid -->
    </div>
    <!-- index body end -->

  </div>
  <!-- Page Body End -->
</div>
<!-- page-wrapper End-->

<?php include __DIR__ . "/partials/logout.php"; ?>
<?php include __DIR__ . "/partials/script-js.php"; ?>
