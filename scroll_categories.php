<!-- Bootstrap CSS -->

<style>
  .marquee-wrapper {
    overflow: hidden;
    background-color: #fff;
    position: sticky;
    width: 100%;
    z-index: 8;
    border-top:.5px solid #1b1b1b40;
    border-bottom:.5px solid #1b1b1b40;
  }

  .marquee-scroll {
    display: inline-block;
    white-space: nowrap;
    overflow:hidden;
    animation: scroll-left 100s linear infinite;
  }

  @keyframes scroll-left {
    0% {
      transform: translateX(0%);
    }
    100% {
      transform: translateX(-50%);
    }
  }

  .category-item {
    display: inline-block;
    margin-right: 16px;
    padding: 8px 18px;
    font-size: 0.95rem;
    font-weight: 500;
    text-transform: capitalize;
    color: #1b1b1b;
    text-decoration: none;
    transition: all 0.3s ease;
  }

  .category-item:hover {
    color: #fff;
    background-color: #1b1b1b;
    border-color: #1b1b1b;
  }

  @media screen and (max-width: 976px) {
    .category-item {
      font-size: 0.85rem;
      padding: 6px 14px;
      margin-right: 10px;
    }
 .marquee-wrapper{
      position:static;
    }

  }

</style>

<!-- Smooth Marquee-Style Category Bar -->
<div class="marquee-wrapper py-2 w-100 " style="top: 0; ">
  <div class="marquee-scroll">
    <!-- Repeat twice for seamless infinite scroll -->
    <span>
      <a href="#" class="category-item">All</a>
      <a href="#" class="category-item">Shoes</a>
      <a href="#" class="category-item">Clothing</a>
      <a href="#" class="category-item">Watches</a>
      <a href="#" class="category-item">Bags</a>
      <a href="#" class="category-item">Jewelry</a>
      <a href="#" class="category-item">Hats</a>
      <a href="#" class="category-item">Sunglasses</a>
      <a href="#" class="category-item">Belts</a>
      <a href="#" class="category-item">Accessories</a>
      <a href="#" class="category-item">Featured</a>
      <a href="#" class="category-item">Trending</a>
      <a href="#" class="category-item">New Arrivals</a>
      <a href="#" class="category-item">Limited Edition</a>
      <a href="#" class="category-item">Men</a>
      <a href="#" class="category-item">Women</a>
      <a href="#" class="category-item">Luxury</a>
      <a href="#" class="category-item">Casual</a>
      <a href="#" class="category-item">Essentials</a>
      <a href="#" class="category-item">Gifts</a>
    </span>
     <span >
      <!-- Duplicate for infinite effect -->
      <a href="#" class="category-item">All</a>
      <a href="#" class="category-item">Shoes</a>
      <a href="#" class="category-item">Clothing</a>
      <a href="#" class="category-item">Watches</a>
      <a href="#" class="category-item">Bags</a>
      <a href="#" class="category-item">Jewelry</a>
      <a href="#" class="category-item">Hats</a>
      <a href="#" class="category-item">Sunglasses</a>
      <a href="#" class="category-item">Belts</a>
      <a href="#" class="category-item">Accessories</a>
      <a href="#" class="category-item">Featured</a>
      <a href="#" class="category-item">Trending</a>
      <a href="#" class="category-item">New Arrivals</a>
      <a href="#" class="category-item">Limited Edition</a>
      <a href="#" class="category-item">Men</a>
      <a href="#" class="category-item">Women</a>
      <a href="#" class="category-item">Luxury</a>
      <a href="#" class="category-item">Casual</a>
      <a href="#" class="category-item">Essentials</a>
      <a href="#" class="category-item">Gifts</a>
    </span>

  </div>
</div>
