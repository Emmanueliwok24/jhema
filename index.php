<?php include("includes/head.php"); ?>
<?php include("includes/svg.php"); ?>
<?php include("includes/mobile-header.php"); ?>
<?php include("includes/header.php"); ?>





<main class=" position-relative">
   <?php include("scroll_categories.php"); ?>

  <!-- slide Show -->
      <?php include("index-main/slide-show.php"); ?>


      <div class="mb-3 pb-3 mb-md-4 pb-md-4 mb-xl-5 pb-xl-5"></div>
      <div class="pb-1"></div>

      <!-- Shop by collection -->
      <section
        class="collections-grid collections-grid_masonry"
        id="section-collections-grid_masonry"
      >
        <div class="container h-md-100">
          <div class="row h-md-100">
            <div class="col-lg-6 h-md-100">
              <div class="collection-grid__item position-relative h-md-100">
                <div
                  class="background-img"
                  style="
                    background-image: url('./images/collection_grid_1.jpg');
                  "
                ></div>
                <div
                  class="content_abs content_bottom content_left content_bottom-md content_left-md"
                >
                  <p class="text-uppercase mb-1">Hot List</p>
                  <h3 class="text-uppercase">
                    <strong>Women</strong> Collection
                  </h3>
                  <a
                    href="<?= BASE_URL ?>shop/shop.php"
                    class="btn-link default-underline text-uppercase fw-medium"
                    >Shop Now</a
                  >
                </div>
                <!-- /.content_abs content_bottom content_left content_bottom-md content_left-md -->
              </div>
            </div>
            <!-- /.col-md-6 -->

            <div class="col-lg-6 d-flex flex-column">
              <div
                class="collection-grid__item position-relative flex-grow-1 mb-lg-4"
              >
                <div
                  class="background-img"
                  style="
                    background-image: url('./images/collection_grid_2.jpg');
                  "
                ></div>
                <div
                  class="content_abs content_bottom content_left content_bottom-md content_left-md"
                >
                  <p class="text-uppercase mb-1">Hot List</p>
                  <h3 class="text-uppercase">
                    <strong>Men</strong> Collection
                  </h3>
                  <a
                    href="<?= BASE_URL ?>shop/shop.php"
                    class="btn-link default-underline text-uppercase fw-medium"
                    >Shop Now</a
                  >
                </div>
                <!-- /.content_abs content_bottom content_left content_bottom-md content_left-md -->
              </div>
              <div class="position-relative flex-grow-1 mt-lg-1">
                <div class="row h-md-100">
                  <div class="col-md-6 h-md-100">
                    <div
                      class="collection-grid__item h-md-100 position-relative"
                    >
                      <div
                        class="background-img"
                        style="
                          background-image: url('./images/collection_grid_3.jpg');
                        "
                      ></div>
                      <div
                        class="content_abs content_bottom content_left content_bottom-md content_left-md"
                      >
                        <p class="text-uppercase mb-1">Hot List</p>
                        <h3 class="text-uppercase">
                          <strong>Kids</strong> Collection
                        </h3>
                        <a
                          href="<?= BASE_URL ?>shop/shop.php"
                          class="btn-link default-underline text-uppercase fw-medium"
                          >Shop Now</a
                        >
                      </div>
                      <!-- /.content_abs content_bottom content_left content_bottom-md content_left-md -->
                    </div>
                    <!-- /.collection-grid__item -->
                  </div>

                  <div class="col-md-6 h-md-100">
                    <div
                      class="collection-grid__item h-md-100 position-relative"
                    >
                      <div
                        class="background-img"
                        style="background-color: #f5e6e0"
                      ></div>
                      <div
                        class="content_abs content_bottom content_left content_bottom-md content_left-md"
                      >
                        <h3 class="text-uppercase">
                          <strong>E-Gift</strong> Cards
                        </h3>
                        <p class="mb-1">
                          Surprise someone with the gift they<br />really want.
                        </p>
                        <a
                          href="<?= BASE_URL ?>shop/shop.php"
                          class="btn-link default-underline text-uppercase fw-medium"
                          >Shop Now</a
                        >
                      </div>
                      <!-- /.content_abs content_bottom content_left content_bottom-md content_left-md -->
                    </div>
                    <!-- /.collection-grid__item -->
                  </div>
                </div>
              </div>
            </div>
            <!-- /.col-md-6 -->
          </div>
          <!-- /.row -->
        </div>
        <!-- /.container -->
      </section>
      <!-- /.collections-grid collections-grid_masonry -->

      <div class="mb-4 pb-4 mb-xl-5 pb-xl-5"></div>

      <!-- Trending Products -->
      <?php include("./index-main/trend-product.php"); ?>



      <div class="mb-3 mb-xl-5 pb-1 pb-xl-5"></div>

      <!-- Weekly deal -->
      <section
        class="deal-timer position-relative d-flex align-items-end overflow-hidden"
        style="background-color: #ebebeb"
      >
        <div
          class="background-img"
          style="background-image: url('./images/collection_grid_1.jpg')"
        ></div>

        <div class="deal-timer-wrapper container position-relative">
          <div class="deal-timer__content pb-2 mb-3 pb-xl-5 mb-xl-3 mb-xxl-5">
            <p class="text_dash text-uppercase text-red fw-medium">
              Deal of the week
            </p>
            <h3 class="h1 text-uppercase">
              <strong>Spring</strong> Collection
            </h3>
            <a
              href="<?= BASE_URL ?>shop/shop.php"
              class="btn-link default-underline text-uppercase fw-medium mt-3"
              >Shop Now</a
            >
          </div>

          <div
            class="position-relative d-flex align-items-center text-center pt-xxl-4 js-countdown"
            data-date="18-5-2024"
            data-time="06:50"
          >
            <div class="day countdown-unit">
              <span class="countdown-num d-block"></span>
              <span class="countdown-word fw-bold text-uppercase text-secondary"
                >Days</span
              >
            </div>

            <div class="hour countdown-unit">
              <span class="countdown-num d-block"></span>
              <span class="countdown-word fw-bold text-uppercase text-secondary"
                >Hours</span
              >
            </div>

            <div class="min countdown-unit">
              <span class="countdown-num d-block"></span>
              <span class="countdown-word fw-bold text-uppercase text-secondary"
                >Mins</span
              >
            </div>

            <div class="sec countdown-unit">
              <span class="countdown-num d-block"></span>
              <span class="countdown-word fw-bold text-uppercase text-secondary"
                >Sec</span
              >
            </div>
          </div>
        </div>
        <!-- /.deal-timer-wrapper -->
      </section>
      <!-- /.deal-timer -->

      <div class="mb-3 mb-xl-5 pb-1 pb-xl-5"></div>

      <section class="grid-banner container mb-3">
        <div class="row">
          <div class="col-md-6">
            <div
              class="grid-banner__item grid-banner__item_rect position-relative mb-3"
            >
              <div
                class="background-img"
                style="background-image: url('./images/banner_1.jpg')"
              ></div>
              <div
                class="content_abs content_bottom content_left content_bottom-lg content_left-lg"
              >
                <h6 class="text-uppercase text-white fw-medium mb-3">
                  Starting At $19
                </h6>
                <h3 class="text-white mb-3">Women's T-Shirts</h3>
                <a
                  href="<?= BASE_URL ?>shop/shop.php"
                  class="btn-link default-underline text-uppercase text-white fw-medium"
                  >Shop Now</a
                >
              </div>
              <!-- /.content_abs content_bottom content_left content_bottom-md content_left-md -->
            </div>
          </div>
          <!-- /.col-md-6 -->

          <div class="col-md-6">
            <div
              class="grid-banner__item grid-banner__item_rect position-relative mb-3"
            >
              <div
                class="background-img"
                style="background-image: url('./images/banner_2.jpg')"
              ></div>
              <div
                class="content_abs content_bottom content_left content_bottom-lg content_left-lg"
              >
                <h6 class="text-uppercase fw-medium mb-3">Starting At $39</h6>
                <h3 class="mb-3">Men's Sportswear</h3>
                <a
                  href="<?= BASE_URL ?>shop/shop.php"
                  class="btn-link default-underline text-uppercase fw-medium"
                  >Shop Now</a
                >
              </div>
              <!-- /.content_abs content_bottom content_left content_bottom-md content_left-md -->
            </div>
          </div>
          <!-- /.col-md-6 -->
        </div>
        <!-- /.row -->
      </section>
      <!-- /.grid-banner container -->

      <div class="mb-5 pb-1 pb-xl-4"></div>


      <!-- edition -->
             <?php include("index-main/edition.php"); ?>
<!-- /.edition -->


      <section class="service-promotion container mb-md-4 pb-md-4 mb-xl-5">
        <div class="row">
          <div class="col-md-4 text-center mb-5 mb-md-0">
            <div class="service-promotion__icon mb-4">
              <svg
                width="52"
                height="52"
                viewBox="0 0 52 52"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <use href="#icon_shipping" />
              </svg>
            </div>
            <h3 class="service-promotion__title h5 text-uppercase">
              Fast And Free Delivery
            </h3>
            <p class="service-promotion__content text-secondary">
              Free delivery for all orders over $140
            </p>
          </div>
          <!-- /.col-md-4 text-center-->

          <div class="col-md-4 text-center mb-5 mb-md-0">
            <div class="service-promotion__icon mb-4">
              <svg
                width="53"
                height="52"
                viewBox="0 0 53 52"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <use href="#icon_headphone" />
              </svg>
            </div>
            <h3 class="service-promotion__title h5 text-uppercase">
              24/7 Customer Support
            </h3>
            <p class="service-promotion__content text-secondary">
              Friendly 24/7 customer support
            </p>
          </div>
          <!-- /.col-md-4 text-center-->

          <div class="col-md-4 text-center mb-4 pb-1 mb-md-0">
            <div class="service-promotion__icon mb-4">
              <svg
                width="52"
                height="52"
                viewBox="0 0 52 52"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <use href="#icon_shield" />
              </svg>
            </div>
            <h3 class="service-promotion__title h5 text-uppercase">
              Money Back Guarantee
            </h3>
            <p class="service-promotion__content text-secondary">
              We return money within 30 days
            </p>
          </div>
          <!-- /.col-md-4 text-center-->
        </div>
        <!-- /.row -->
      </section>
      <!-- /.service-promotion container -->
    </main>
<!-- footer -->
<?php include("includes/footer.php"); ?>

<!-- End Footer Type 1 -->
<?php include("includes/mobile-footer.php"); ?>

<!-- form -->
<?php include("includes/aside-form.php"); ?>



<!-- aside cart -->
<?php include("includes/cart-aside.php"); ?>


<!-- sitemap -->
<?php include("includes/sitemap-nav.php"); ?>


<!-- newsletter -->
<?php include("includes/newsletter.php"); ?>

<?php include("includes/scroll.php"); ?>


<!-- script footer -->
<?php include("includes/script-footer.php"); ?>

