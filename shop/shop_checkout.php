<?php
// shop/shop_checkout.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_user.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/shipping.php';
require_once __DIR__ . '/../includes/payment_config.php';

require_user(); // user must be logged in

$userId = (int)($_SESSION['user_id'] ?? 0);
[$items, $subtotal, $weight] = cart_totals($pdo, $userId);

// Prefill from session (don’t fetch DB to avoid touching other files)
$prefill_name  = (string)($_SESSION['user_name']  ?? '');
$prefill_email = (string)($_SESSION['user_email'] ?? '');

// Posted shipping fields
$country = $_POST['country'] ?? 'NG';
$state   = $_POST['state']   ?? '';
$addr1   = trim((string)($_POST['address_line1'] ?? ''));
$addr2   = trim((string)($_POST['address_line2'] ?? ''));
$city    = trim((string)($_POST['city'] ?? ''));
$zipcode = trim((string)($_POST['zipcode'] ?? ''));
$phone   = trim((string)($_POST['phone'] ?? ''));
$payment_method = $_POST['payment_method'] ?? 'flutterwave';

// Calculate shipping and total
$shipping = $items ? calculate_shipping_linear((float)$weight, $country, $pdo) : 0.0;
$total    = (float)$subtotal + (float)$shipping;

// Handle PAY NOW
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
  if (!$items) { header('Location: ' . BASE_URL . 'shop/shop_cart.php'); exit; }

  try {
    $pdo->beginTransaction();

    $txRef = 'JHEMA-' . time() . '-' . bin2hex(random_bytes(3));
    $ins = $pdo->prepare("
      INSERT INTO orders
        (user_id, subtotal, shipping, total, country_code, state, address_line1, address_line2, city, zipcode, phone, weight_kg, currency, flw_tx_ref, status)
      VALUES
        (?,       ?,        ?,       ?,     ?,            ?,     ?,             ?,             ?,    ?,       ?,     ?,         ?,        ?,          'pending')
    ");
    $ins->execute([
      $userId, $subtotal, $shipping, $total,
      $country, $state, $addr1, $addr2, $city, $zipcode, $phone,
      $weight, FLW_CURRENCY, $txRef
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, name, price, quantity, weight_kg, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $it) {
      $qty  = (int)$it['quantity'];
      $line = $qty * (float)$it['base_price'];
      $insItem->execute([
        $orderId,
        (int)$it['product_id'],
        (string)$it['name'],
        (float)$it['base_price'],
        $qty,
        (float)$it['weight_kg'],
        $line
      ]);
    }

    $pdo->commit();

    // Flutterwave live (existing)
    if ($payment_method === 'flutterwave') {
      if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
      $_SESSION['pay_ctx'] = [
        'tx_ref'   => $txRef,
        'amount'   => $total,
        'email'    => $prefill_email ?: 'customer@example.com',
        'name'     => $prefill_name  ?: 'Customer',
        'order_id' => $orderId,
      ];
      header('Location: ' . BASE_URL . 'checkout/pay.php'); // your existing flow
      exit;
    }

    // Cash on Delivery: no extra integration; show order page (status=pending).
    if ($payment_method === 'cod') {
      header('Location: ' . BASE_URL . 'account/orderreceived.php?id=' . $orderId);
      exit;
    }

    // Other gateways intentionally not wired here to avoid touching other files.
    // For now, route them through Flutterwave (so UX isn't broken) OR block on client side.
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $_SESSION['pay_ctx'] = [
      'tx_ref'   => $txRef,
      'amount'   => $total,
      'email'    => $prefill_email ?: 'customer@example.com',
      'name'     => $prefill_name  ?: 'Customer',
      'order_id' => $orderId,
    ];
    header('Location: ' . BASE_URL . 'checkout/pay.php');
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo 'Order creation failed. Please try again.';
    exit;
  }
}

include("../includes/head.php");
include("../includes/svg.php");
// With the mobile-header fix (no auth include inside it), we can safely include both headers again:
include("../includes/mobile-header.php");
include("../includes/header.php");
?>
<style>
  .pay-card{
    border:1px solid #e6e6e6;border-radius:12px;padding:14px;display:flex;align-items:center;gap:12px;
    background:#fff;cursor:pointer
  }
  .pay-card.disabled{opacity:.55;cursor:not-allowed}
  .pay-card input[type=radio]{transform:translateY(1px)}
  .pay-badges{display:flex;gap:8px;flex-wrap:wrap}
  .pay-badge{border:1px solid #ddd;border-radius:6px;padding:3px 6px;font-size:12px}
  .muted{color:#6b6b6b}
</style>

<main class="position-relative">
  <?php include("../scroll_categories.php"); ?>
  <div class="mb-4 pb-4"></div>

  <section class="shop-checkout container">
    <h2 class="page-title">Shipping and Checkout</h2>

    <div class="checkout-steps">
      <a href="<?= BASE_URL ?>shop/shop_cart.php" class="checkout-steps__item">
        <span class="checkout-steps__item-number">01</span>
        <span class="checkout-steps__item-title"><span>Shopping Bag</span><em>Manage Your Items List</em></span>
      </a>
      <span class="checkout-steps__item active">
        <span class="checkout-steps__item-number">02</span>
        <span class="checkout-steps__item-title"><span>Shipping and Checkout</span><em>Checkout Your Items List</em></span>
      </span>
      <span class="checkout-steps__item">
        <span class="checkout-steps__item-number">03</span>
        <span class="checkout-steps__item-title"><span>Confirmation</span><em>Review And Submit Your Order</em></span>
      </span>
    </div>

    <?php if (!$items): ?>
      <div class="alert alert-info">Your cart is empty.</div>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>shop/shop.php">Back to Shop</a>
    <?php else: ?>
    <form method="post" class="checkout-form" id="checkoutForm" novalidate>
      <div class="row">
        <!-- Left: Customer + Shipping -->
        <div class="col-lg-7">
          <div class="billing-info__wrapper">
            <h4>Contact</h4>
            <div class="row">
              <div class="col-md-6">
                <div class="form-floating my-3">
                  <input type="text" class="form-control" id="co_name" name="co_name"
                         value="<?= htmlspecialchars($prefill_name) ?>" placeholder="Full name *" required>
                  <label for="co_name">Full name *</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating my-3">
                  <input type="email" class="form-control" id="co_email" name="co_email"
                         value="<?= htmlspecialchars($prefill_email) ?>" placeholder="Email *" required>
                  <label for="co_email">Email *</label>
                </div>
              </div>
            </div>

            <h4 class="mt-4">Shipping Address</h4>
            <div class="row">
              <div class="col-md-12">
                <div class="form-floating my-3">
                  <select class="form-select" id="checkout_country" name="country" required></select>
                  <label for="checkout_country">Country / Region *</label>
                </div>
              </div>

              <!-- NG / US / CA get dropdown; others get a free-text Region field -->
              <div class="col-md-6" id="stateDropdownWrap" style="display:none">
                <div class="form-floating my-3">
                  <select class="form-select" id="checkout_state" name="state"></select>
                  <label for="checkout_state" id="lbl_state">State / Province</label>
                </div>
              </div>
              <div class="col-md-6" id="regionTextWrap" style="display:none">
                <div class="form-floating my-3">
                  <input type="text" class="form-control" id="checkout_region_text" name="state"
                         value="<?= htmlspecialchars($state) ?>" placeholder="Region / State">
                  <label for="checkout_region_text">Region / State</label>
                </div>
              </div>

              <div class="col-md-12">
                <div class="form-floating my-3">
                  <input type="text" class="form-control" id="checkout_address1" name="address_line1"
                         value="<?= htmlspecialchars($addr1) ?>" placeholder="Street Address *" required>
                  <label for="checkout_address1">Street Address *</label>
                </div>
              </div>
              <div class="col-md-12">
                <div class="form-floating my-3">
                  <input type="text" class="form-control" id="checkout_address2" name="address_line2"
                         value="<?= htmlspecialchars($addr2) ?>" placeholder="Apartment, suite, etc.">
                  <label for="checkout_address2">Apartment, suite, etc. (optional)</label>
                </div>
              </div>

              <div class="col-md-5">
                <div class="form-floating my-3">
                  <input type="text" class="form-control" id="checkout_city" name="city"
                         value="<?= htmlspecialchars($city) ?>" placeholder="City *" required>
                  <label for="checkout_city">City *</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-floating my-3">
                  <input type="text" class="form-control" id="checkout_zipcode" name="zipcode"
                         value="<?= htmlspecialchars($zipcode) ?>" placeholder="Postcode / ZIP">
                  <label for="checkout_zipcode">Postcode / ZIP</label>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-floating my-3">
                  <input type="tel" class="form-control" id="checkout_phone" name="phone"
                         value="<?= htmlspecialchars($phone) ?>" placeholder="Phone *" required
                         pattern="[\d\s+\-()]{6,}">
                  <label for="checkout_phone">Phone *</label>
                </div>
              </div>
            </div>
          </div>

          <h4 class="mt-4">Payment</h4>

          <div class="d-flex flex-column gap-2">
            <label class="pay-card">
              <input type="radio" name="payment_method" value="flutterwave" <?= $payment_method==='flutterwave'?'checked':''; ?>>
              <span>Flutterwave</span>
              <span class="muted ms-2">(Cards, transfers, & more)</span>
            </label>

            <label class="pay-card disabled" title="Coming soon">
              <input type="radio" name="payment_method" value="paystack" disabled>
              <span>Paystack</span>
              <span class="pay-badges"><span class="pay-badge">Coming soon</span></span>
            </label>

            <label class="pay-card disabled" title="Coming soon">
              <input type="radio" name="payment_method" value="opay" disabled>
              <span>OPay</span>
              <span class="pay-badges"><span class="pay-badge">Coming soon</span></span>
            </label>

            <label class="pay-card disabled" title="Coming soon">
              <input type="radio" name="payment_method" value="applepay" disabled>
              <span>Apple&nbsp;Pay</span>
              <span class="pay-badges"><span class="pay-badge">Coming soon</span></span>
            </label>

            <label class="pay-card disabled" title="Coming soon">
              <input type="radio" name="payment_method" value="googlepay" disabled>
              <span>Google&nbsp;Pay</span>
              <span class="pay-badges"><span class="pay-badge">Coming soon</span></span>
            </label>

            <label class="pay-card">
              <input type="radio" name="payment_method" value="cod" <?= $payment_method==='cod'?'checked':''; ?>>
              <span>Cash on Delivery</span>
            </label>
          </div>
        </div>

        <!-- Right: Order summary -->
        <div class="col-lg-5">
          <div class="checkout__totals-wrapper">
            <div class="sticky-content">
              <div class="checkout__totals">
                <h3>Your Order</h3>
                <table class="checkout-cart-items">
                  <thead><tr><th>PRODUCT</th><th>SUBTOTAL</th></tr></thead>
                  <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr>
                      <td><?= htmlspecialchars($it['name']) ?> × <?= (int)$it['quantity'] ?></td>
                      <td>₦<?= number_format((float)$it['base_price'] * (int)$it['quantity'],2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <table class="checkout-totals">
                  <tbody>
                    <tr><th>SUBTOTAL</th><td id="subtotalRow">₦<?= number_format($subtotal,2) ?></td></tr>
                    <tr><th>WEIGHT</th><td><?= number_format($weight,2) ?> kg</td></tr>
                    <tr><th>SHIPPING</th><td id="shipRow">₦<?= number_format($shipping,2) ?> (<?= $country==='NG'?'₦1,500':'₦5,000' ?>/kg)</td></tr>
                    <tr><th>TOTAL</th><td id="totalRow">₦<?= number_format($total,2) ?></td></tr>
                  </tbody>
                </table>
              </div>

              <button class="btn btn-primary btn-checkout w-100 mt-2" name="pay_now" value="1">PAY NOW</button>
            </div>
          </div>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </section>
</main>

<div class="mb-5 pb-xl-5"></div>
<?php include("../includes/footer.php"); ?>
<?php include("../includes/mobile-footer.php"); ?>
<?php include("../includes/aside-form.php"); ?>
<?php include("../includes/cart-aside.php"); ?>
<?php include("../includes/sitemap-nav.php"); ?>
<?php include("../includes/scroll.php"); ?>
<?php include("../includes/script-footer.php"); ?>

<script>
// ===== Countries (ISO) =====
const COUNTRIES = [
  ["AF","Afghanistan"],["AL","Albania"],["DZ","Algeria"],["AS","American Samoa"],["AD","Andorra"],
  ["AO","Angola"],["AI","Anguilla"],["AG","Antigua and Barbuda"],["AR","Argentina"],["AM","Armenia"],
  ["AW","Aruba"],["AU","Australia"],["AT","Austria"],["AZ","Azerbaijan"],["BS","Bahamas"],
  ["BH","Bahrain"],["BD","Bangladesh"],["BB","Barbados"],["BY","Belarus"],["BE","Belgium"],
  ["BZ","Belize"],["BJ","Benin"],["BM","Bermuda"],["BT","Bhutan"],["BO","Bolivia"],
  ["BA","Bosnia and Herzegovina"],["BW","Botswana"],["BR","Brazil"],["BN","Brunei Darussalam"],
  ["BG","Bulgaria"],["BF","Burkina Faso"],["BI","Burundi"],["KH","Cambodia"],["CM","Cameroon"],
  ["CA","Canada"],["CV","Cape Verde"],["KY","Cayman Islands"],["CF","Central African Republic"],
  ["TD","Chad"],["CL","Chile"],["CN","China"],["CX","Christmas Island"],["CO","Colombia"],
  ["KM","Comoros"],["CG","Congo"],["CD","Congo, the Democratic Republic of the"],["CR","Costa Rica"],
  ["CI","Côte d’Ivoire"],["HR","Croatia"],["CU","Cuba"],["CY","Cyprus"],["CZ","Czechia"],
  ["DK","Denmark"],["DJ","Djibouti"],["DM","Dominica"],["DO","Dominican Republic"],
  ["EC","Ecuador"],["EG","Egypt"],["SV","El Salvador"],["GQ","Equatorial Guinea"],["ER","Eritrea"],
  ["EE","Estonia"],["SZ","Eswatini"],["ET","Ethiopia"],["FJ","Fiji"],["FI","Finland"],
  ["FR","France"],["GF","French Guiana"],["PF","French Polynesia"],["GA","Gabon"],["GM","Gambia"],
  ["GE","Georgia"],["DE","Germany"],["GH","Ghana"],["GI","Gibraltar"],["GR","Greece"],
  ["GL","Greenland"],["GD","Grenada"],["GP","Guadeloupe"],["GU","Guam"],["GT","Guatemala"],
  ["GG","Guernsey"],["GN","Guinea"],["GW","Guinea-Bissau"],["GY","Guyana"],["HT","Haiti"],
  ["HN","Honduras"],["HK","Hong Kong"],["HU","Hungary"],["IS","Iceland"],["IN","India"],
  ["ID","Indonesia"],["IR","Iran"],["IQ","Iraq"],["IE","Ireland"],["IM","Isle of Man"],
  ["IL","Israel"],["IT","Italy"],["JM","Jamaica"],["JP","Japan"],["JE","Jersey"],["JO","Jordan"],
  ["KZ","Kazakhstan"],["KE","Kenya"],["KI","Kiribati"],["KW","Kuwait"],["KG","Kyrgyzstan"],
  ["LA","Lao PDR"],["LV","Latvia"],["LB","Lebanon"],["LS","Lesotho"],["LR","Liberia"],
  ["LY","Libya"],["LI","Liechtenstein"],["LT","Lithuania"],["LU","Luxembourg"],["MO","Macao"],
  ["MG","Madagascar"],["MW","Malawi"],["MY","Malaysia"],["MV","Maldives"],["ML","Mali"],
  ["MT","Malta"],["MH","Marshall Islands"],["MQ","Martinique"],["MR","Mauritania"],["MU","Mauritius"],
  ["YT","Mayotte"],["MX","Mexico"],["FM","Micronesia"],["MD","Moldova"],["MC","Monaco"],
  ["MN","Mongolia"],["ME","Montenegro"],["MS","Montserrat"],["MA","Morocco"],["MZ","Mozambique"],
  ["MM","Myanmar"],["NA","Namibia"],["NR","Nauru"],["NP","Nepal"],["NL","Netherlands"],
  ["NC","New Caledonia"],["NZ","New Zealand"],["NI","Nicaragua"],["NE","Niger"],["NG","Nigeria"],
  ["KP","North Korea"],["MK","North Macedonia"],["NO","Norway"],["OM","Oman"],["PK","Pakistan"],
  ["PW","Palau"],["PA","Panama"],["PG","Papua New Guinea"],["PY","Paraguay"],["PE","Peru"],
  ["PH","Philippines"],["PL","Poland"],["PT","Portugal"],["PR","Puerto Rico"],["QA","Qatar"],
  ["RE","Réunion"],["RO","Romania"],["RU","Russia"],["RW","Rwanda"],["KN","Saint Kitts and Nevis"],
  ["LC","Saint Lucia"],["VC","Saint Vincent and the Grenadines"],["WS","Samoa"],["SM","San Marino"],
  ["ST","Sao Tome and Principe"],["SA","Saudi Arabia"],["SN","Senegal"],["RS","Serbia"],
  ["SC","Seychelles"],["SL","Sierra Leone"],["SG","Singapore"],["SK","Slovakia"],["SI","Slovenia"],
  ["SB","Solomon Islands"],["SO","Somalia"],["ZA","South Africa"],["KR","South Korea"],
  ["SS","South Sudan"],["ES","Spain"],["LK","Sri Lanka"],["SD","Sudan"],["SR","Suriname"],
  ["SE","Sweden"],["CH","Switzerland"],["SY","Syria"],["TW","Taiwan"],["TJ","Tajikistan"],
  ["TZ","Tanzania"],["TH","Thailand"],["TL","Timor-Leste"],["TG","Togo"],["TO","Tonga"],
  ["TT","Trinidad and Tobago"],["TN","Tunisia"],["TR","Türkiye"],["TM","Turkmenistan"],
  ["TC","Turks and Caicos"],["UG","Uganda"],["UA","Ukraine"],["AE","United Arab Emirates"],
  ["GB","United Kingdom"],["US","United States"],["UY","Uruguay"],["UZ","Uzbekistan"],
  ["VU","Vanuatu"],["VE","Venezuela"],["VN","Viet Nam"],["VG","Virgin Islands, British"],
  ["VI","Virgin Islands, U.S."],["EH","Western Sahara"],["YE","Yemen"],["ZM","Zambia"],["ZW","Zimbabwe"]
];

// States/Provinces for NG/US/CA
const NG_STATES = [
  "Abia","Adamawa","Akwa Ibom","Anambra","Bauchi","Bayelsa","Benue","Borno","Cross River",
  "Delta","Ebonyi","Edo","Ekiti","Enugu","FCT","Gombe","Imo","Jigawa","Kaduna","Kano","Katsina",
  "Kebbi","Kogi","Kwara","Lagos","Nasarawa","Niger","Ogun","Ondo","Osun","Oyo","Plateau",
  "Rivers","Sokoto","Taraba","Yobe","Zamfara"
];

const US_STATES = [
  "Alabama","Alaska","Arizona","Arkansas","California","Colorado","Connecticut","Delaware","District of Columbia",
  "Florida","Georgia","Hawaii","Idaho","Illinois","Indiana","Iowa","Kansas","Kentucky","Louisiana","Maine",
  "Maryland","Massachusetts","Michigan","Minnesota","Mississippi","Missouri","Montana","Nebraska","Nevada",
  "New Hampshire","New Jersey","New Mexico","New York","North Carolina","North Dakota","Ohio","Oklahoma",
  "Oregon","Pennsylvania","Rhode Island","South Carolina","South Dakota","Tennessee","Texas","Utah","Vermont",
  "Virginia","Washington","West Virginia","Wisconsin","Wyoming"
];

const CA_PROVINCES = [
  "Alberta","British Columbia","Manitoba","New Brunswick","Newfoundland and Labrador","Nova Scotia",
  "Ontario","Prince Edward Island","Quebec","Saskatchewan","Northwest Territories","Nunavut","Yukon"
];

const countrySel = document.getElementById('checkout_country');
const stateWrap  = document.getElementById('stateDropdownWrap');
const regionWrap = document.getElementById('regionTextWrap');
const stateSel   = document.getElementById('checkout_state');
const lblState   = document.getElementById('lbl_state');

function buildCountrySelect(defaultCode){
  countrySel.innerHTML = '';
  COUNTRIES.forEach(([code,name])=>{
    const opt = document.createElement('option');
    opt.value = code;
    opt.textContent = name;
    if (code === defaultCode) opt.selected = true;
    countrySel.appendChild(opt);
  });
}

// Populate states for selected country
async function fillRegion(countryCode){
  if (countryCode === 'NG') {
    lblState.textContent = 'State (Nigeria)';
    stateSel.innerHTML = '';
    // Use your API for NG states (matches your existing implementation)
    try {
      const r = await fetch('<?= BASE_URL ?>api/shipping/states.php?country=NG');
      const j = await r.json();
      (j.states || NG_STATES).forEach(s=>{
        const o = document.createElement('option'); o.value=s; o.textContent=s; stateSel.appendChild(o);
      });
    } catch(e) {
      NG_STATES.forEach(s=>{
        const o = document.createElement('option'); o.value=s; o.textContent=s; stateSel.appendChild(o);
      });
    }
    stateWrap.style.display = ''; regionWrap.style.display='none';
  } else if (countryCode === 'US') {
    lblState.textContent = 'State (United States)';
    stateSel.innerHTML = '';
    US_STATES.forEach(s=>{
      const o = document.createElement('option'); o.value=s; o.textContent=s; stateSel.appendChild(o);
    });
    stateWrap.style.display = ''; regionWrap.style.display='none';
  } else if (countryCode === 'CA') {
    lblState.textContent = 'Province / Territory (Canada)';
    stateSel.innerHTML = '';
    CA_PROVINCES.forEach(s=>{
      const o = document.createElement('option'); o.value=s; o.textContent=s; stateSel.appendChild(o);
    });
    stateWrap.style.display = ''; regionWrap.style.display='none';
  } else {
    // Free text for the rest of the world
    stateWrap.style.display = 'none';
    regionWrap.style.display = '';
  }
}

async function recalcShipping(countryCode){
  const fd = new FormData();
  fd.append('country', countryCode);
  fd.append('weight', <?= json_encode((float)$weight) ?>);
  const r = await fetch('<?= BASE_URL ?>api/shipping/calc.php', {method:'POST', body: fd});
  const j = await r.json();
  if (j.success) {
    document.getElementById('shipRow').textContent = j.formattedShippingCost + (countryCode==='NG'?' (₦1,500/kg)':' (₦5,000/kg)');
    const newTotal = (<?= json_encode((float)$subtotal) ?> + j.shippingCost).toFixed(2);
    document.getElementById('totalRow').textContent = '₦' + Number(newTotal).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2});
  }
}

countrySel?.addEventListener('change', async (e)=>{
  const cc = e.target.value;
  await fillRegion(cc);
  await recalcShipping(cc);
});

// Init
buildCountrySelect(<?= json_encode((string)$country) ?>);
fillRegion(<?= json_encode((string)$country) ?>);

// Basic client-side guard: require name, email, country, phone
document.getElementById('checkoutForm')?.addEventListener('submit', (e)=>{
  const req = ['co_name','co_email','checkout_country','checkout_address1','checkout_city','checkout_phone'];
  for (const id of req){
    const el = document.getElementById(id);
    if (!el || !el.value.trim()){
      e.preventDefault();
      alert('Please complete the required fields.');
      el?.focus();
      return false;
    }
  }
});
</script>
