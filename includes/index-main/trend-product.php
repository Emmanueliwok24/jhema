      <section class="products-grid container">
        <h2
          class="section-title text-uppercase text-center mb-1 mb-md-3 pb-xl-2 mb-xl-4"
        >
          Our Trendy <strong>Products</strong>
        </h2>

        <ul
          class="nav nav-tabs mb-3 text-uppercase justify-content-center"
          id="collections-tab"
          role="tablist"
        >

           <?php foreach ($menu as $m): ?>
      <div class="dropdown">
        <a   class="nav-link nav-link_underscore"
              id="collections-tab-2-trigger"
              data-bs-toggle="tab"
              href="index.php?slug=<?= urlencode($p['slug']) ?>&cur=<?= urlencode($display) ?>"
              role="tab"
              aria-controls="<?= urlencode($p['slug']) ?>&cur=<?= urlencode($display) ?>"
              aria-selected="true"><?= htmlspecialchars($m['name']) ?></a>
        <!-- Keep your small hover menu but we’ll also show all attributes in sidebar -->

      </div>
    <?php endforeach; ?>

        </ul>

        <div class="tab-content pt-2" id="collections-tab-content">

          <div class="tab-pane fade show active"
            id="index.php?slug=<?=urlencode($p['slug']) ?>&cur=<?= urlencode($display) ?>"
            role="<?= urlencode($p['slug']) ?>&cur=<?= urlencode($display) ?>"
            aria-labelledby="collections-tab-1-trigger">
            <div class="row">
              <div class="products-grid row row-cols-2 row-cols-md-4">
        <?php foreach ($products as $p):
          $converted = convert_price((float)$p['base_price'], $p['base_currency_code'], $display, $curMap);
          $symbol = $curMap[$display]['symbol'];
        ?>
          <div class="product-card-wrapper">
            <div class="product-card  mb-3 mb-md-4 mb-xxl-5">
              <div class="pc__img-wrapper">
                <div class="swiper-container background-img js-swiper-slider" data-settings='{"resizeObserver": true}'>
                  <div class="swiper-wrapper">
                    <div class="swiper-slid">

                      <a href="product.php?slug=<?= urlencode($p['slug']) ?>&cur=<?= urlencode($display) ?>">
                        <?php if (!empty($p['image_path'])): ?>
                          <img  src="<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" width="330" height="400"  class="pc__img">
                           <?php else: ?><span class="muted">No Image</span><?php endif; ?>
                      </a>
                    </div><!-- /.pc__img-wrapper -->

                  </div>
                  <span class="pc__img-prev"><svg width="7" height="11" viewbox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_prev_sm"></use></svg></span>
                  <span class="pc__img-next"><svg width="7" height="11" viewbox="0 0 7 11" xmlns="http://www.w3.org/2000/svg"><use href="#icon_next_sm"></use></svg></span>
                </div>
                <button class="pc__atc btn anim_appear-bottom btn position-absolute border-0 text-uppercase fw-medium js-add-cart js-open-aside" data-aside="cartDrawer" title="Add To Cart">Add To Cart</button>
              </div>

              <div class="pc__info position-relative px-2">
                <p class="pc__category"><?= htmlspecialchars($p['cat_name'] ?? '') ?></p>
                <h6 class="pc__title"><a href="product.php"><?= htmlspecialchars($p['name']) ?></a></h6>
                <P> <?= htmlspecialchars($p['sku']) ?></P>
                <div class="product-card__price d-flex">
                  <span class="money price"><?= $symbol . price_display($converted) ?></span>
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
        <?php endforeach; ?>
              </div>
            </div>
            <!-- /.row -->
            <div class="text-center mt-2">
              <a
                class="btn-link btn-link_lg default-underline text-uppercase fw-medium"
                href="./shop.php"
                >Discover More</a
              >
            </div>
          </div>

          <!-- /.tab-pane fade show-->

          <!-- /.tab-pane fade show-->
        </div>
        <!-- /.tab-content pt-2 -->
      </section>
      <!-- /.products-grid -->