// Product data



// Currency conversion rates (example rates)
const currencyRates = {
  NGN: 1,
  USD: 0.0012,
  EUR: 0.0011
};

// Current state
let currentState = {
  selectedColor: 'blue',
  selectedSize: 'M',
  currency: 'NGN'
};

// DOM elements
const productImage = document.getElementById('productImage');
const productPrice = document.getElementById('productPrice');
const currencySelect = document.getElementById('currencySelect');
const displayCurrency = document.getElementById('displayCurrency');
const addToCartBtn = document.getElementById('addToCartBtn');
const toast = document.getElementById('toast');

// Initialize the page
function init() {
  updatePrice();
  setupEventListeners();
}

// Setup event listeners
function setupEventListeners() {
  // Color selection
  const colorButtons = document.querySelectorAll('.color-btn');
  colorButtons.forEach(btn => {
    btn.addEventListener('click', () => selectColor(btn));
  });

  // Size selection
  const sizeButtons = document.querySelectorAll('.size-btn');
  sizeButtons.forEach(btn => {
    btn.addEventListener('click', () => selectSize(btn));
  });

  // Currency selection
  currencySelect.addEventListener('change', (e) => {
    currentState.currency = e.target.value;
    displayCurrency.value = e.target.value;
    updatePrice();
  });

  displayCurrency.addEventListener('change', (e) => {
    currentState.currency = e.target.value;
    currencySelect.value = e.target.value;
    updatePrice();
  });

  // Add to cart
  addToCartBtn.addEventListener('click', addToCart);
}

// Select color
function selectColor(button) {
  // Remove active class from all color buttons
  document.querySelectorAll('.color-btn').forEach(btn => {
    btn.classList.remove('active');
  });

  // Add active class to clicked button
  button.classList.add('active');

  // Update current state
  const color = button.dataset.color;
  currentState.selectedColor = color;

  // Update product image
  productImage.src = productData[color].image;
  productImage.alt = `${productData[color].name} Bloomfield Floral Midi Shirt`;

  // Update price
  updatePrice();
}

// Select size
function selectSize(button) {
  // Remove active class from all size buttons
  document.querySelectorAll('.size-btn').forEach(btn => {
    btn.classList.remove('active');
  });

  // Add active class to clicked button
  button.classList.add('active');

  // Update current state
  currentState.selectedSize = button.dataset.size;
}

// Format currency
function formatCurrency(amount, currency) {
  const convertedAmount = amount * currencyRates[currency];

  const currencySymbols = {
    NGN: '₦',
    USD: '$',
    EUR: '€'
  };

  const symbol = currencySymbols[currency] || currency;

  return `${symbol}${convertedAmount.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`;
}

// Update price display
function updatePrice() {
  const selectedProduct = productData[currentState.selectedColor];
  const formattedPrice = formatCurrency(selectedProduct.price, currentState.currency);
  productPrice.textContent = formattedPrice;
}

// Add to cart function
function addToCart() {
  const selectedProduct = productData[currentState.selectedColor];
  const message = `${selectedProduct.name} Bloomfield Floral Midi Shirt (${currentState.selectedSize}) added to your cart.`;

  showToast('Added to Cart', message);
}

// Show toast notification
function showToast(title, message) {
  const toastTitle = toast.querySelector('.toast-title');
  const toastMessage = toast.querySelector('.toast-message');

  toastTitle.textContent = title;
  toastMessage.textContent = message;

  // Show toast
  toast.classList.remove('hidden');

  // Hide toast after 3 seconds
  setTimeout(() => {
    toast.classList.add('slide-out');
    setTimeout(() => {
      toast.classList.add('hidden');
      toast.classList.remove('slide-out');
    }, 300);
  }, 3000);
}

// Category button functionality
document.querySelectorAll('.category-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    // Remove active state from all categories
    document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
    // Add active state to clicked category
    btn.classList.add('active');

    if (btn.textContent.includes('Add Product')) {
      showToast('Add Product', 'Redirect to add product page');
    } else {
      showToast('Category Selected', `Browsing ${btn.textContent} category`);
    }
  });
});

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', init);

