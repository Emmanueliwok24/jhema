      <section class="products-carousel container">
        <h2
          class="section-title text-uppercase text-center mb-4 pb-xl-2 mb-xl-4"
        >
          Limited <strong>Edition</strong>
        </h2>

        <div id="product_carousel" class="position-relative">
          <div
            class="swiper-container js-swiper-slider"
            data-settings='{
            "autoplay": {
              "delay": 5000
            },
            "slidesPerView": 4,
            "slidesPerGroup": 4,
            "effect": "none",
            "loop": true,
            "pagination": {
              "el": "#product_carousel .products-pagination",
              "type": "bullets",
              "clickable": true
            },
            "navigation": {
              "nextEl": "#product_carousel .products-carousel__next",
              "prevEl": "#product_carousel .products-carousel__prev"
            },
            "breakpoints": {
              "320": {
                "slidesPerView": 2,
                "slidesPerGroup": 2,
                "spaceBetween": 14
              },
              "768": {
                "slidesPerView": 3,
                "slidesPerGroup": 3,
                "spaceBetween": 24
              },
              "992": {
                "slidesPerView": 4,
                "slidesPerGroup": 1,
                "spaceBetween": 30
              }
            }
          }'
          >
            <div class="swiper-wrapper">
              <div class="swiper-slide product-card">
                <div class="pc__img-wrapper">
                  <a href="./product.php">
                    <img
                      loading="lazy"
                      src="./images/home/demo1/product-0-1.jpg"
                      width="330"
                      height="400"
                      alt="Cropped Faux leather Jacket"
                      class="pc__img"
                    />
                  </a>
                  <button
                    class="pc__atc btn anim_appear-bottom btn position-absolute border-0 text-uppercase fw-medium js-add-cart js-open-aside"
                    data-aside="cartDrawer"
                    title="Add To Cart"
                  >
                    Add To Cart
                  </button>
                </div>

                <div class="pc__info position-relative">
                  <p class="pc__category">Dresses</p>
                  <h6 class="pc__title">
                    <a href="./product.php">Hub Accent Mirror</a>
                  </h6>
                  <div class="product-card__price d-flex">
                    <span class="money price">$17</span>
                  </div>

                  <button
                    class="pc__btn-wl position-absolute top-0 end-0 bg-transparent border-0 js-add-wishlist"
                    title="Add To Wishlist"
                  >
                    <svg
                      width="16"
                      height="16"
                      viewBox="0 0 20 20"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <use href="#icon_heart" />
                    </svg>
                  </button>
                </div>
              </div>


            </div>
            <!-- /.swiper-wrapper -->
          </div>
          <!-- /.swiper-container js-swiper-slider -->

          <div
            class="products-carousel__prev position-absolute top-50 d-flex align-items-center justify-content-center"
          >
            <svg
              width="25"
              height="25"
              viewBox="0 0 25 25"
              xmlns="http://www.w3.org/2000/svg"
            >
              <use href="#icon_prev_md" />
            </svg>
          </div>
          <!-- /.products-carousel__prev -->
          <div
            class="products-carousel__next position-absolute top-50 d-flex align-items-center justify-content-center"
          >
            <svg
              width="25"
              height="25"
              viewBox="0 0 25 25"
              xmlns="http://www.w3.org/2000/svg"
            >
              <use href="#icon_next_md" />
            </svg>
          </div>
          <!-- /.products-carousel__next -->

          <div
            class="products-pagination mt-4 mb-5 d-flex align-items-center justify-content-center"
          ></div>
          <!-- /.products-pagination -->
        </div>
        <!-- /.position-relative -->
      </section>
      <!-- /.products-carousel container -->