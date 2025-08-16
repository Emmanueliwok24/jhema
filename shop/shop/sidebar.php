 <div class="shop-sidebar side-sticky bg-body "id="shopFilter">
        <div class="aside-header d-flex d-lg-none align-items-center">
          <h3 class="text-uppercase fs-6 mb-0">Filter By</h3>
          <button class="btn-close-lg js-close-aside btn-close-aside ms-auto"></button>
        </div><!-- /.aside-header -->

        <div class="pt-4 pt-lg-0"></div>

        <div  id="categories-list">
          <div class="accordion-item mb-4 pb-3">
            <h5 class="accordion-header " id="accordion-heading-11">
              <button class="accordion-button   p-0 border-0 fs-5 text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-filter-1" aria-expanded="true" aria-controls="accordion-filter-1" >
                Product Categories
                <svg class="accordion-button__icon type2" viewbox="0 0 10 6" xmlns="http://www.w3.org/2000/svg">
                  <g aria-hidden="true" stroke="none" fill-rule="evenodd">
                    <path d="M5.35668 0.159286C5.16235 -0.053094 4.83769 -0.0530941 4.64287 0.159286L0.147611 5.05963C-0.0492049 5.27473 -0.049205 5.62357 0.147611 5.83813C0.344427 6.05323 0.664108 6.05323 0.860924 5.83813L5 1.32706L9.13858 5.83867C9.33589 6.05378 9.65507 6.05378 9.85239 5.83867C10.0492 5.62357 10.0492 5.27473 9.85239 5.06018L5.35668 0.159286Z"></path>
                  </g>
                </svg>
              </button>
            </h5>
            <div id="accordion-filter-1" class="accordion-collapse collapse show border-0" aria-labelledby="accordion-heading-11" data-bs-parent="#categories-list">
              <div class="accordion-body px-0 pb-0 pt-3">
                 <div class="list-group list-group-flush">


            <a class="<?= !$category_slug ? 'active' : '' ?> list-group-item list-group-item-action border-0 p-2 filter-btn" href="<?= BASE_URL ?>shop/shop.php?cur=<?= urlencode($display) ?>">All</a>
          <?php foreach ($menu as $m): ?>
            <a class="<?= ($category_slug===$m['slug']) ? 'active' : '' ?> list-group-item list-group-item-action border-0 p-2 filter-btn"
               href="<?= BASE_URL ?>shop/shop.php?cat=<?= urlencode($m['slug']) ?>&cur=<?= urlencode($display) ?>">
              <?= htmlspecialchars($m['name']) ?>
            </a>
          <?php endforeach; ?>
              </div>
              </div>
            </div>
          </div><!-- /.accordion-item -->
        </div><!-- /.accordion-item -->


           <?php
        // Find the currently selected category to display its attributes
        $current = null;
        foreach ($menu as $m) if ($category_slug && $m['slug']===$category_slug) $current = $m;
        // If none selected, show a helpful hint and also allow quick access to a “global view” of attribute picks per cat below
      ?>

      <?php if ($current): ?>
        <?php $allowed = $current['allowed']; ?>
        <?php if (!empty($allowed['occasion'])): ?>
          <div class="sidegroup">
            <h4>Occasion</h4>
            <div class="chips">
              <?php foreach ($allowed['occasion'] as $o): ?>
                <a class="chip" href="shop/shop.php?cat=<?= urlencode($current['slug']) ?>&occasion=<?= urlencode($o['value']) ?>&length=<?= urlencode($length ?? '') ?>&style=<?= urlencode($style ?? '') ?>&cur=<?= urlencode($display) ?>">
                  <?= htmlspecialchars($o['value']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($allowed['length'])): ?>
          <div class="sidegroup">
            <h4>Length</h4>
            <div class="chips">
              <?php foreach ($allowed['length'] as $l): ?>
                <a class="chip" href="<?= BASE_URL ?>shop/shop.php?cat=<?= urlencode($current['slug']) ?>&length=<?= urlencode($l['value']) ?>&occasion=<?= urlencode($occasion ?? '') ?>&style=<?= urlencode($style ?? '') ?>&cur=<?= urlencode($display) ?>">
                  <?= htmlspecialchars($l['value']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($allowed['style'])): ?>
          <div class="sidegroup">
            <h4>Style</h4>
            <div class="chips">
              <?php foreach ($allowed['style'] as $s): ?>
                <a class="chip" href="<?= BASE_URL ?>shop/shop.php?cat=<?= urlencode($current['slug']) ?>&style=<?= urlencode($s['value']) ?>&occasion=<?= urlencode($occasion ?? '') ?>&length=<?= urlencode($length ?? '') ?>&cur=<?= urlencode($display) ?>">
                  <?= htmlspecialchars($s['value']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="sidegroup">
          <h4>Tips</h4>
          <div class="sidehint">Pick a category first to see all its Occasions / Lengths / Styles here.</div>
        </div>
      <?php endif; ?>

        <div  id="color-filters">
          <div class="accordion-item mb-4 pb-3">
            <h5 class="accordion-header" id="accordion-heading-1">
              <button class="accordion-button p-0 border-0 fs-5 text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-filter-2" aria-expanded="true" aria-controls="accordion-filter-2">
                Color
                <svg class="accordion-button__icon type2" viewbox="0 0 10 6" xmlns="http://www.w3.org/2000/svg">
                  <g aria-hidden="true" stroke="none" fill-rule="evenodd">
                    <path d="M5.35668 0.159286C5.16235 -0.053094 4.83769 -0.0530941 4.64287 0.159286L0.147611 5.05963C-0.0492049 5.27473 -0.049205 5.62357 0.147611 5.83813C0.344427 6.05323 0.664108 6.05323 0.860924 5.83813L5 1.32706L9.13858 5.83867C9.33589 6.05378 9.65507 6.05378 9.85239 5.83867C10.0492 5.62357 10.0492 5.27473 9.85239 5.06018L5.35668 0.159286Z"></path>
                  </g>
                </svg>
              </button>
            </h5>
            <div id="accordion-filter-2" class="accordion-collapse collapse show border-0" aria-labelledby="accordion-heading-1" data-bs-parent="#color-filters">
              <div class="accordion-body px-0 pb-0">
                <div class="d-flex flex-wrap ">
                  <a href="#" class=" list-group-item active   ">Black</a>

                </div>
              </div>
            </div>
          </div><!-- /.accordion-item -->
        </div><!-- /.accordion -->


        <div  id="size-filters">
          <div class="accordion-item mb-4 pb-3">
            <h5 class="accordion-header" id="accordion-heading-size">
              <button class="accordion-button p-0 border-0 fs-5 text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-filter-size" aria-expanded="true" aria-controls="accordion-filter-size">
                Sizes
                <svg class="accordion-button__icon type2" viewbox="0 0 10 6" xmlns="http://www.w3.org/2000/svg">
                  <g aria-hidden="true" stroke="none" fill-rule="evenodd">
                    <path d="M5.35668 0.159286C5.16235 -0.053094 4.83769 -0.0530941 4.64287 0.159286L0.147611 5.05963C-0.0492049 5.27473 -0.049205 5.62357 0.147611 5.83813C0.344427 6.05323 0.664108 6.05323 0.860924 5.83813L5 1.32706L9.13858 5.83867C9.33589 6.05378 9.65507 6.05378 9.85239 5.83867C10.0492 5.62357 10.0492 5.27473 9.85239 5.06018L5.35668 0.159286Z"></path>
                  </g>
                </svg>
              </button>
            </h5>
            <div id="accordion-filter-size" class="accordion-collapse collapse show border-0" aria-labelledby="accordion-heading-size" data-bs-parent="#size-filters">
              <div class="accordion-body px-0 pb-0">
                <div class="d-flex flex-wrap ">
                  <a href="#" class="swatch-size list-group-item mb-3 me-3 js-filter">XS</a>
                  <a href="#" class="swatch-size list-group-item mb-3 me-3 js-filter">S</a>
                  <a href="#" class="swatch-size list-group-item mb-3 me-3 js-filter">M</a>
                  <a href="#" class="swatch-size list-group-item mb-3 me-3 js-filter">L</a>
                  <a href="#" class="swatch-size list-group-item mb-3 me-3 js-filter">XL</a>
                  <a href="#" class="swatch-size list-group-item mb-3 me-3 js-filter">XXL</a>
                </div>
              </div>
            </div>
          </div><!-- /.accordion-item -->
        </div><!-- /.accordion -->





        <div  id="price-filters">
          <div class="accordion-item mb-4">
            <h5 class="accordion-header mb-2" id="accordion-heading-price">
              <button class="accordion-button p-0 border-0 fs-5 text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-filter-price" aria-expanded="true" aria-controls="accordion-filter-price">
                Price
                <svg class="accordion-button__icon type2" viewbox="0 0 10 6" xmlns="http://www.w3.org/2000/svg">
                  <g aria-hidden="true" stroke="none" fill-rule="evenodd">
                    <path d="M5.35668 0.159286C5.16235 -0.053094 4.83769 -0.0530941 4.64287 0.159286L0.147611 5.05963C-0.0492049 5.27473 -0.049205 5.62357 0.147611 5.83813C0.344427 6.05323 0.664108 6.05323 0.860924 5.83813L5 1.32706L9.13858 5.83867C9.33589 6.05378 9.65507 6.05378 9.85239 5.83867C10.0492 5.62357 10.0492 5.27473 9.85239 5.06018L5.35668 0.159286Z"></path>
                  </g>
                </svg>
              </button>
            </h5>
            <div id="accordion-filter-price" class="accordion-collapse collapse show border-0" aria-labelledby="accordion-heading-price" data-bs-parent="#price-filters">
              <input class="price-range-slider" type="text" name="price_range" value="" data-slider-min="10" data-slider-max="1000" data-slider-step="5" data-slider-value="[250,450]" data-currency="$">
              <div class="price-range__info d-flex align-items-center mt-2">
                <div class="me-auto">
                  <span class="text-secondary">Min Price: </span>
                  <span class="price-range__min">$250</span>
                </div>
                <div>
                  <span class="text-secondary">Max Price: </span>
                  <span class="price-range__max">$450</span>
                </div>
              </div>
            </div>
          </div><!-- /.accordion-item -->
        </div><!-- /.accordion -->
      </div><!-- /.shop-sidebar -->


