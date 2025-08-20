<?php
// admin/tools/migrate_orders_schema_safe.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***

require_once __DIR__ . '/../../includes/config.php'; // provides $pdo (PDO)
if (session_status() === PHP_SESSION_NONE) session_start();

/* Force verbose errors so you see real SQL issues */
@restore_error_handler();
@restore_exception_handler();
@ini_set('display_errors','1');
@ini_set('display_startup_errors','1');
error_reporting(E_ALL);

/* Helpers */
function qv(PDO $pdo, string $sql) {
  $st = $pdo->query($sql);
  return $st ? $st->fetch(PDO::FETCH_NUM)[0] ?? '' : '';
}
function db_supports_json(PDO $pdo): bool {
  $v = (string)qv($pdo, 'SELECT VERSION()');
  $isMaria = stripos($v, 'mariadb') !== false;
  if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $v, $m)) { $maj=(int)$m[1]; $min=(int)$m[2]; $pat=(int)$m[3]; } else { return false; }
  if ($isMaria) return ($maj > 10) || ($maj === 10 && ($min > 2 || ($min === 2 && $pat >= 7)));
  return ($maj > 5) || ($maj === 5 && ($min > 7 || ($min === 7 && $pat >= 8)));
}
function col_exists(PDO $pdo, string $table, string $col): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$table, $col]);
  return (bool)$st->fetchColumn();
}
function idx_exists(PDO $pdo, string $table, string $idx): bool {
  $st = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
  $st->execute([$idx]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}
function run(PDO $pdo, string $sql) { $pdo->exec($sql); }

/* Output header */
header('Content-Type: text/plain; charset=utf-8');
echo "== Jhema Orders Schema Migration (safe, no-transactions) ==\n";

try {
  /* Ensure base orders table exists */
  run($pdo, "CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_orders_user` (`user_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $JSON = db_supports_json($pdo) ? 'JSON' : 'LONGTEXT';
  echo "DB VERSION: " . qv($pdo, 'SELECT VERSION()') . "\n";
  echo "JSON SUPPORTED: " . ($JSON === 'JSON' ? 'yes' : 'no (using LONGTEXT)') . "\n";

  /* Columns to add (idempotent) */
  $adds = [
    "order_number"     => "ALTER TABLE `orders` ADD COLUMN `order_number` VARCHAR(40) NULL",
    "subtotal"         => "ALTER TABLE `orders` ADD COLUMN `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0",
    "shipping"         => "ALTER TABLE `orders` ADD COLUMN `shipping` DECIMAL(12,2) NOT NULL DEFAULT 0",
    "total_amount"     => "ALTER TABLE `orders` ADD COLUMN `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0",
    "currency"         => "ALTER TABLE `orders` ADD COLUMN `currency` VARCHAR(8) NOT NULL DEFAULT 'NGN'",
    "status"           => "ALTER TABLE `orders` ADD COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'pending'",
    "payment_method"   => "ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(32) NULL",
    "flw_tx_ref"       => "ALTER TABLE `orders` ADD COLUMN `flw_tx_ref` VARCHAR(64) NULL",
    "address_line1"    => "ALTER TABLE `orders` ADD COLUMN `address_line1` VARCHAR(255) NULL",
    "address_line2"    => "ALTER TABLE `orders` ADD COLUMN `address_line2` VARCHAR(255) NULL",
    "city"             => "ALTER TABLE `orders` ADD COLUMN `city` VARCHAR(120) NULL",
    "state"            => "ALTER TABLE `orders` ADD COLUMN `state` VARCHAR(120) NULL",
    "zipcode"          => "ALTER TABLE `orders` ADD COLUMN `zipcode` VARCHAR(32) NULL",
    "country_code"     => "ALTER TABLE `orders` ADD COLUMN `country_code` VARCHAR(2) NULL",
    "phone"            => "ALTER TABLE `orders` ADD COLUMN `phone` VARCHAR(40) NULL",
    "weight_kg"        => "ALTER TABLE `orders` ADD COLUMN `weight_kg` DECIMAL(10,3) NULL",
    "shipping_method"  => "ALTER TABLE `orders` ADD COLUMN `shipping_method` VARCHAR(64) NULL",
    "tracking_code"    => "ALTER TABLE `orders` ADD COLUMN `tracking_code` VARCHAR(64) NULL",
    "tracking_carrier" => "ALTER TABLE `orders` ADD COLUMN `tracking_carrier` VARCHAR(64) NULL",
    "tracking_url"     => "ALTER TABLE `orders` ADD COLUMN `tracking_url` VARCHAR(255) NULL",
    "admin_note"       => "ALTER TABLE `orders` ADD COLUMN `admin_note` TEXT NULL",
    "created_at"       => "ALTER TABLE `orders` ADD COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
    "updated_at"       => "ALTER TABLE `orders` ADD COLUMN `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
  ];

  foreach ($adds as $c => $sql) {
    if (!col_exists($pdo, 'orders', $c)) {
      echo "Adding column: $c ... ";
      run($pdo, $sql);
      echo "OK\n";
    }
  }

  if (!idx_exists($pdo, 'orders', 'uk_orders_number')) {
    echo "Adding unique index uk_orders_number ... ";
    @run($pdo, "ALTER TABLE `orders` ADD UNIQUE KEY `uk_orders_number` (`order_number`)");
    echo "OK\n";
  }
  if (!idx_exists($pdo, 'orders', 'idx_orders_status')) {
    echo "Adding index idx_orders_status ... ";
    @run($pdo, "ALTER TABLE `orders` ADD KEY `idx_orders_status` (`status`)");
    echo "OK\n";
  }
  if (!idx_exists($pdo, 'orders', 'idx_orders_created')) {
    echo "Adding index idx_orders_created ... ";
    @run($pdo, "ALTER TABLE `orders` ADD KEY `idx_orders_created` (`created_at`)");
    echo "OK\n";
  }

  /* Ensure order_items */
  echo "Ensuring table order_items ... ";
  run($pdo, "CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `quantity` INT NOT NULL DEFAULT 1,
    `weight_kg` DECIMAL(10,3) NULL,
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order` (`order_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "OK\n";

  /* Ensure order_events */
  echo "Ensuring table order_events (meta_json type: $JSON) ... ";
  run($pdo, "CREATE TABLE IF NOT EXISTS `order_events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `actor_admin_id` INT UNSIGNED NULL,
    `from_status` VARCHAR(32) NULL,
    `to_status` VARCHAR(32) NOT NULL,
    `note` TEXT NULL,
    `meta_json` $JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_events_order` (`order_id`),
    KEY `idx_order_events_created` (`created_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "OK\n";

  /* Backfills */
  echo "Backfilling order_number ... ";
  run($pdo, "UPDATE orders
                SET order_number = CONCAT('JHEMA-', LPAD(id, 6, '0'))
              WHERE order_number IS NULL OR order_number = ''");
  echo "OK\n";

  echo "Backfilling currency/status defaults ... ";
  run($pdo, "UPDATE orders SET currency='NGN' WHERE currency IS NULL OR currency = ''");
  run($pdo, "UPDATE orders SET status='pending' WHERE status IS NULL OR status = ''");
  echo "OK\n";

  if (col_exists($pdo, 'orders', 'total')) {
    echo "Backfilling total_amount from legacy total ... ";
    run($pdo, "UPDATE orders
                  SET total_amount = total
                WHERE (total_amount IS NULL OR total_amount = 0)
                  AND total IS NOT NULL");
    echo "OK\n";
  }

  echo "\nDONE: Schema aligned successfully.\n";
} catch (Throwable $e) {
  echo "\nERROR: " . $e->getMessage() . "\n";
  echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
