  <!-- Header Type 1 -->
    <header id="header" class="header header_sticky">
      <div class="container">
        <div class="header-desk header-desk_type_1">
          <div class="logo">
            <a href="././">
              <img
                src="./images/logo.svg"
                alt="jhema"
                class="logo__image d-block"
              />
            </a>
          </div>
          <!-- /.logo -->

          <nav class="navigation">
            <ul class="navigation__list list-unstyled d-flex">
              <li class="navigation__item">
                <a href="./" class="navigation__link">Home</a>

                <!-- /.box-menu -->
              </li>
              <li class="navigation__item">
                <a href="./shop.php" class="navigation__link">Shop</a>

              </li>
              <li class="navigation__item">
                <a href="./blog.html" class="navigation__link">Blog</a>

                <!-- /.box-menu -->
              </li>

              <li class="navigation__item">
                <a href="./about.html" class="navigation__link">About</a>
              </li>
              <li class="navigation__item">
                <a href="./contact.html" class="navigation__link">Contact</a>
              </li>
            </ul>
            <!-- /.navigation__list -->
          </nav>
          <!-- /.navigation -->

          <div class="header-tools d-flex align-items-center">
            <div class="header-tools__item hover-container">
              <div class="js-hover__open position-relative">
                <a class="js-search-popup search-field__actor" href="#">
                  <svg
                    class="d-block"
                    width="20"
                    height="20"
                    viewBox="0 0 20 20"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    <use href="#icon_search" />
                  </svg>
                  <i class="btn-icon btn-close-lg"></i>
                </a>
              </div>

              <div class="search-popup js-hidden-content">
                <form
                  action="./search_result.html"
                  method="GET"
                  class="search-field container"
                >
                  <p class="text-uppercase text-secondary fw-medium mb-4">
                    What are you looking for?
                  </p>
                  <div class="position-relative">
                    <input
                      class="search-field__input search-popup__input w-100 fw-medium"
                      type="text"
                      name="search-keyword"
                      placeholder="Search products"
                    />
                    <button class="btn-icon search-popup__submit" type="submit">
                      <svg
                        class="d-block"
                        width="20"
                        height="20"
                        viewBox="0 0 20 20"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                      >
                        <use href="#icon_search" />
                      </svg>
                    </button>
                    <button
                      class="btn-icon btn-close-lg search-popup__reset"
                      type="reset"
                    ></button>
                  </div>

                  <div class="search-popup__results">
                    <div class="sub-menu search-suggestion">
                      <h6 class="sub-menu__title fs-base">Quicklinks</h6>
                      <ul class="sub-menu__list list-unstyled">
                        <li class="sub-menu__item">
                          <a
                            href="./shop.php"
                            class="menu-link menu-link_us-s"
                            >New Arrivals</a
                          >
                        </li>
                        <li class="sub-menu__item">
                          <a href="#" class="menu-link menu-link_us-s"
                            >Dresses</a
                          >
                        </li>
                        <li class="sub-menu__item">
                          <a
                            href="./shop.php"
                            class="menu-link menu-link_us-s"
                            >Accessories</a
                          >
                        </li>
                        <li class="sub-menu__item">
                          <a href="#" class="menu-link menu-link_us-s"
                            >Footwear</a
                          >
                        </li>
                        <li class="sub-menu__item">
                          <a href="#" class="menu-link menu-link_us-s"
                            >Sweatshirt</a
                          >
                        </li>
                      </ul>
                    </div>

                    <div class="search-result row row-cols-5"></div>
                  </div>
                </form>
                <!-- /.header-search -->
              </div>
              <!-- /.search-popup -->
            </div>
            <!-- /.header-tools__item hover-container -->

            <div class="header-tools__item hover-container">
              <a
                class="header-tools__item js-open-aside"
                href="#"
                data-aside="customerForms"
              >
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 20 20"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <use href="#icon_user" />
                </svg>
              </a>
            </div>

            <a class="header-tools__item" href="./account_wishlist.html">
              <svg
                width="20"
                height="20"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <use href="#icon_heart" />
              </svg>
            </a>

            <a
              href="#"
              class="header-tools__item header-tools__cart js-open-aside"
              data-aside="cartDrawer"
            >
              <svg
                class="d-block"
                width="20"
                height="20"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <use href="#icon_cart" />
              </svg>
              <span
                class="cart-amount d-block position-absolute js-cart-items-count"
                >3</span
              >
            </a>

            <a
              class="header-tools__item"
              href="#"
              data-bs-toggle="modal"
              data-bs-target="#siteMap"
            >
              <svg
                class="nav-icon"
                width="25"
                height="18"
                viewBox="0 0 25 18"
                xmlns="http://www.w3.org/2000/svg"
              >
                <use href="#icon_nav" />
              </svg>
            </a>
          </div>
          <!-- /.header__tools -->
        </div>
        <!-- /.header-desk header-desk_type_1 -->
      </div>
      <!-- /.container -->
    </header>
    <!-- End Header Type 1 -->