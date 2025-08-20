<?php
// includes/shipping.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

/**
 * Base rate per KG by country.
 * - Nigeria (NG) => 1500 / kg
 * - Others       => 5000 / kg
 * You can expand to a DB table later; this is fast + deterministic.
 */
if (!function_exists('shipping_perkg_for')) {
  function shipping_perkg_for(PDO $pdo, string $countryCode): float {
    $cc = strtoupper(trim($countryCode));
    if ($cc === 'NG') return 1500.0;
    return 5000.0;
  }
}

if (!function_exists('calculate_shipping_linear')) {
  function calculate_shipping_linear(float $weightKg, string $countryCode, ?PDO $pdo = null): float {
    $perKg = shipping_perkg_for($pdo ?? $GLOBALS['pdo'], $countryCode);
    $w = max(0, $weightKg);
    return round($perKg * $w, 2); // fractional accurate
  }
}

if (!function_exists('nigeria_states')) {
  function nigeria_states(): array {
    return [
      "Abia","Adamawa","Akwa Ibom","Anambra","Bauchi","Bayelsa","Benue","Borno","Cross River",
      "Delta","Ebonyi","Edo","Ekiti","Enugu","FCT","Gombe","Imo","Jigawa","Kaduna","Kano",
      "Katsina","Kebbi","Kogi","Kwara","Lagos","Nasarawa","Niger","Ogun","Ondo","Osun","Oyo",
      "Plateau","Rivers","Sokoto","Taraba","Yobe","Zamfara"
    ];
  }
}
