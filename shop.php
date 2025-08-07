<?php include("includes/head.php"); ?>
<?php include("includes/svg.php"); ?>
<?php include("includes/mobile-header.php"); ?>
<?php include("includes/header.php"); ?>





 <main class=" position-relative">
   <?php include("scroll_categories.php"); ?>
    <div class="mb-md-1 pb-xl-5"></div>

   <section class="shop-main container d-flex  ">
     <!-- shop sidebar -->

     <?php include("includes/shop/sidebar.php"); ?>

     <div class="shop-list flex-grow-1">
       <!-- slide show -->
       <?php include("includes/shop/slideshow.php"); ?>


       <div class="mb-3 pb-2 pb-xl-3"></div>

        <div class="d-flex justify-content-between mb-4 pb-md-2">
          <div class="breadcrumb mb-0 d-none d-md-block flex-grow-1">
            <a href="#" class="menu-link menu-link_us-s text-uppercase fw-medium">Home</a>
            <span class="breadcrumb-separator menu-link fw-medium ps-1 pe-1">/</span>
            <a href="#" class="menu-link menu-link_us-s text-uppercase fw-medium">The Shop</a>
          </div><!-- /.breadcrumb -->

          <div class="shop-acs d-flex align-items-center  justify-content-between justify-content-md-end flex-grow-1">
            <select class="shop-acs__select form-select w-auto border-0 py-0 order-1 order-md-0" aria-label="Sort Items" name="total-number">
              <option selected="">Default Sorting</option>
              <option value="1">Featured</option>
              <option value="2">Best selling</option>
              <option value="3">Alphabetically, A-Z</option>
              <option value="3">Alphabetically, Z-A</option>
              <option value="3">Price, low to high</option>
              <option value="3">Price, high to low</option>
              <option value="3">Date, old to new</option>
              <option value="3">Date, new to old</option>
            </select>

            <div class="shop-asc__seprator mx-3 bg-light d-none d-md-block order-md-0"></div>

            <div class="col-size align-items-center order-1 d-none d-lg-flex">
              <span class="text-uppercase fw-medium me-2">View</span>
              <button class="btn-link fw-medium me-2 js-cols-size" data-target="products-grid" data-cols="2">2</button>
              <button class="btn-link fw-medium me-2 js-cols-size" data-target="products-grid" data-cols="3">3</button>
              <button class="btn-link fw-medium js-cols-size" data-target="products-grid" data-cols="4">4</button>
            </div><!-- /.col-size -->

            <div class="shop-filter d-flex align-items-center order-0 order-md-3 d-lg-none">
              <button class="btn-link btn-link_f d-flex align-items-center ps-0 js-open-aside" data-aside="shopFilter">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-filter me-2" viewBox="0 0 16 16">
                  <path d="M6 10.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5m-2-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m-2-3a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5"/>
                </svg>

              <span class="text-uppercase fw-medium d-inline-block align-middle">Filter</span>
              </button>
            </div><!-- /.col-size d-flex align-items-center ms-auto ms-md-3 -->
          </div><!-- /.shop-acs -->
        </div><!-- /.d-flex justify-content-between -->

        <div class="products-grid row row-cols-2 row-cols-md-3" id="products-grid">
          <div class="product-card-wrapper">
            <div class="product-card mb-3 mb-md-4 mb-xxl-5">
              <div class="pc__img-wrapper">
                <div class="swiper-container background-img js-swiper-slider" data-settings='{"resizeObserver": true}'>
                  <div class="swiper-wrapper">
                    <div class="swiper-slide">
                      <a href="product.php"><img loading="lazy" src="./images/products/product_1.jpg" width="330" height="400" alt="Cropped Faux leather Jacket" class="pc__img"></a>
                    </div><!-- /.pc__img-wrapper -->

                  </div>
                  <span class="pc__img-prev"><svg width="7" height="11" viewbox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_prev_sm"></use></svg></span>
                  <span class="pc__img-next"><svg width="7" height="11" viewbox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_next_sm"></use></svg></span>
                </div>
                <button class="pc__atc btn anim_appear-bottom btn position-absolute border-0 text-uppercase fw-medium js-add-cart js-open-aside" data-aside="cartDrawer" title="Add To Cart">Add To Cart</button>
              </div>

              <div class="pc__info position-relative">
                <p class="pc__category">Dresses</p>
                <h6 class="pc__title"><a href="product.php">Cropped Faux Leather Jacket</a></h6>
                <div class="product-card__price d-flex">
                  <span class="money price">$29</span>
                </div>
                <div class="product-card__review d-flex align-items-center">
                  <div class="reviews-group d-flex">
                    <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                    <svg class="review-star" viewbox="0 0 9 9" xmlns="http://www.w3.org/2000/svg"><use href="#icon_star"></use></svg>
                  </div>
                  <span class="reviews-note text-lowercase text-secondary ms-1">8k+ reviews</span>
                </div>

                <button class="pc__btn-wl position-absolute top-0 end-0 bg-transparent border-0 js-add-wishlist" title="Add To Wishlist">
                  <svg width="16" height="16" viewbox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><use href="#icon_heart"></use></svg>
                </button>
              </div>
            </div>
          </div>


        </div><!-- /.products-grid row -->

        <nav class="shop-pages d-flex justify-content-between mt-3" aria-label="Page navigation">
          <a href="#" class="btn-link d-inline-flex align-items-center">
            <svg class="me-1" width="7" height="11" viewbox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_prev_sm"></use></svg>
            <span class="fw-medium">PREV</span>
          </a>
          <ul class="pagination mb-0">
            <li class="page-item"><a class="btn-link px-1 mx-2 btn-link_active" href="#">1</a></li>
            <li class="page-item"><a class="btn-link px-1 mx-2" href="#">2</a></li>
            <li class="page-item"><a class="btn-link px-1 mx-2" href="#">3</a></li>
            <li class="page-item"><a class="btn-link px-1 mx-2" href="#">4</a></li>
          </ul>
          <a href="#" class="btn-link d-inline-flex align-items-center">
            <span class="fw-medium me-1">NEXT</span>
            <svg width="7" height="11" viewbox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_next_sm"></use></svg>
          </a>
        </nav>
      </div>
    </section><!-- /.shop-main container -->
</main>
  <div class="mb-5 pb-xl-5"></div>

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




<?php include("includes/scroll.php"); ?>


<!-- script footer -->
<?php include("includes/script-footer.php"); ?>

