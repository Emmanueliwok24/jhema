<?php
// includes/cart.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/**
 * Internal: build a human label from variant fields (if cart label not set).
 */
function cart_build_variant_label(?string $size, ?string $color): string {
  $size  = trim((string)($size  ?? ''));
  $color = trim((string)($color ?? ''));
  if ($size !== '' && $color !== '') return "Size: {$size} / Color: {$color}";
  if ($size !== '')  return "Size: {$size}";
  if ($color !== '') return "Color: {$color}";
  return '';
}

/**
 * Fetch items in the user's cart, joined with product and variant info.
 * Returns each row with:
 * - product_id, variant_id, variant_label, quantity
 * - name, slug
 * - unit_price (final per-unit used for totals; pref: cart.unit_price → pv.price → p.base_price)
 * - image_path (best raw db path); image_url (absolute URL via product_image_url())
 * - weight_kg (numeric), weight_kg_text (exact typed string), line_subtotal, line_weight_kg
 */
function cart_get_items(PDO $pdo, int $userId): array {
  $sql = "
    SELECT
      c.product_id,
      c.variant_id,
      c.variant_label,
      c.quantity,
      c.unit_price         AS unit_price_saved,
      c.image_path         AS image_path_saved,

      p.name,
      p.slug,
      p.base_price,
      p.image_path         AS product_image,
      p.featured_variant_id,
      p.featured_image_id,
      p.weight_kg,
      p.weight_kg_tmp      AS weight_kg_text,

      pv.price             AS variant_price,
      pv.size              AS pv_size,
      pv.color             AS pv_color,
      pv.image_path        AS pv_image,

      /* best image path fallback chain */
      COALESCE(
        c.image_path,
        pv.image_path,
        (SELECT pv2.image_path FROM product_variants pv2 WHERE pv2.id = p.featured_variant_id AND pv2.image_path IS NOT NULL LIMIT 1),
        (SELECT pi.image_path  FROM product_images  pi  WHERE pi.id = p.featured_image_id  AND pi.image_path  IS NOT NULL LIMIT 1),
        NULLIF(p.image_path, ''),
        (SELECT pi2.image_path FROM product_images pi2 WHERE pi2.product_id = p.id ORDER BY pi2.is_main DESC, pi2.sort_order ASC, pi2.id ASC LIMIT 1)
      ) AS best_image_path
    FROM cart c
    JOIN products p ON p.id = c.product_id
    LEFT JOIN product_variants pv ON pv.id = c.variant_id
    WHERE c.user_id = ?
    ORDER BY c.id ASC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$userId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($rows as &$r) {
    $q = max(1, (int)$r['quantity']);

    // Unit price preference: saved → variant → product base
    $unit = null;
    if ($r['unit_price_saved'] !== null && $r['unit_price_saved'] !== '') {
      $unit = (float)$r['unit_price_saved'];
    } elseif ($r['variant_price'] !== null && $r['variant_price'] !== '') {
      $unit = (float)$r['variant_price'];
    } else {
      $unit = (float)$r['base_price'];
    }

    // Weight
    $wNum = isset($r['weight_kg']) ? (float)$r['weight_kg'] : 0.0;
    $wTxt = isset($r['weight_kg_text']) ? (string)$r['weight_kg_text'] : '';
    if ($wTxt === '' && $r['weight_kg'] !== null) {
      // synthesize an exact-like text if legacy row has no tmp set
      $tmp = rtrim(rtrim(number_format((float)$r['weight_kg'], 6, '.', ''), '0'), '.');
      $wTxt = ($tmp === '') ? '0' : $tmp;
    }

    // Variant label (prefer cart saved label)
    $vLabel = trim((string)($r['variant_label'] ?? ''));
    if ($vLabel === '') {
      $vLabel = cart_build_variant_label($r['pv_size'] ?? null, $r['pv_color'] ?? null);
    }

    // Image URL
    $rawPath = $r['best_image_path'] ?: ($r['image_path_saved'] ?: ($r['pv_image'] ?: ($r['product_image'] ?: null)));
    $imageUrl = $rawPath ? product_image_url($rawPath) : (rtrim(BASE_URL, '/') . '/images/placeholder.png');

    $r['quantity']        = $q;
    $r['unit_price']      = $unit;
    $r['variant_label']   = $vLabel;
    $r['image_path']      = $rawPath;     // keep raw for debugging/back-compat
    $r['image_url']       = $imageUrl;    // <-- direct absolute path for front-end
    $r['weight_kg']       = $wNum;        // numeric
    $r['weight_kg_text']  = $wTxt;        // exact typed for display
    $r['line_subtotal']   = $q * $unit;
    $r['line_weight_kg']  = $q * $wNum;
  }
  unset($r);

  return $rows;
}

/**
 * Cart totals:
 * Returns [$items, $subtotal, $totalWeight, $count]
 * - $subtotal: sum of line_subtotal (uses unit_price per row)
 * - $totalWeight: exact numeric sum (no UI rounding here)
 */
function cart_totals(PDO $pdo, int $userId): array {
  $items = cart_get_items($pdo, $userId);
  $subtotal = 0.0; $totalWeight = 0.0; $count = 0;

  foreach ($items as $r) {
    $subtotal    += (float)$r['line_subtotal'];
    $totalWeight += (float)$r['line_weight_kg'];
    $count       += (int)$r['quantity'];
  }
  return [$items, $subtotal, $totalWeight, $count];
}

/** Exact raw total weight (no UI rounding) — useful for shipping maths. */
function cart_total_weight_raw(PDO $pdo, int $userId): float {
  $items = cart_get_items($pdo, $userId);
  $w = 0.0; foreach ($items as $r) { $w += (float)$r['line_weight_kg']; }
  return $w;
}

/** Resolve product id by slug (returns null if not found). */
function cart_find_product_id_by_slug(PDO $pdo, string $slug): ?int {
  $st = $pdo->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
  $st->execute([$slug]);
  $pid = $st->fetchColumn();
  return $pid ? (int)$pid : null;
}

/**
 * Add to cart by slug (+ optional variant).
 * $variantId: pass null for simple products; an integer id for a selected variant.
 * $variantLabel: optional human label (e.g., "Size: M"); auto-built from variant if empty.
 * - Stores unit_price at add time (if not supplied) to lock-in the price user saw.
 * Returns [ok(bool), err(?string), product_id(?int), variant_id(?int)]
 */
function cart_add_by_slug(PDO $pdo, int $userId, string $slug, int $qty, ?int $variantId = null, ?string $variantLabel = null): array {
  try {
    $qty = max(1, (int)$qty);
    $pid = cart_find_product_id_by_slug($pdo, $slug);
    if (!$pid) return [false, 'Product not found', null, null];

    // Get product base info
    $p = $pdo->prepare("SELECT id, base_price, image_path, featured_variant_id, featured_image_id FROM products WHERE id = ? LIMIT 1");
    $p->execute([$pid]);
    $product = $p->fetch(PDO::FETCH_ASSOC);
    if (!$product) return [false, 'Product not found', null, null];

    $unitPrice = null;
    $cartImage = null;
    $label     = trim((string)($variantLabel ?? ''));

    // If variant provided, validate and pick price/image
    if ($variantId) {
      $v = $pdo->prepare("SELECT id, product_id, size, color, price, image_path FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1");
      $v->execute([$variantId, $pid]);
      $variant = $v->fetch(PDO::FETCH_ASSOC);
      if (!$variant) return [false, 'Variant not found for product', null, null];

      // Price pref: variant.price → product.base_price
      if ($variant['price'] !== null && $variant['price'] !== '') {
        $unitPrice = (float)$variant['price'];
      } else {
        $unitPrice = (float)$product['base_price'];
      }

      // Label
      if ($label === '') {
        $label = cart_build_variant_label($variant['size'] ?? null, $variant['color'] ?? null);
      }

      // Image preference: variant.image_path
      if (!empty($variant['image_path'])) {
        $cartImage = $variant['image_path'];
      }
    }

    // Fallback price for simple products or missing variant.price
    if ($unitPrice === null) {
      $unitPrice = (float)$product['base_price'];
    }

    // If no image decided from variant, leave null; frontend will get best via cart_get_items()
    // (We still store null here so cart rows remain slim.)

    // Upsert by (user_id, product_id, variant_id) using NULL-safe equality
    $sel = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND (variant_id <=> ?) LIMIT 1");
    $sel->execute([$userId, $pid, $variantId]);
    $found = $sel->fetch(PDO::FETCH_ASSOC);

    if ($found) {
      $u = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
      $u->execute([$qty, (int)$found['id']]);
    } else {
      $i = $pdo->prepare("
        INSERT INTO cart (user_id, product_id, variant_id, variant_label, quantity, unit_price, image_path)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      $i->execute([$userId, $pid, $variantId, ($label !== '' ? $label : null), $qty, $unitPrice, $cartImage]);
    }

    return [true, null, $pid, ($variantId ?: null)];
  } catch (Throwable $e) {
    return [false, $e->getMessage(), null, null];
  }
}

/** Set quantity (delete if <= 0). Targeted by (user, product, variant). Returns [ok, err] */
function cart_update_qty(PDO $pdo, int $userId, int $productId, ?int $variantId, int $qty): array {
  try {
    if ($qty <= 0) {
      $d = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ? AND (variant_id <=> ?)");
      $d->execute([$userId, $productId, $variantId]);
      return [true, null];
    }
    $u = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ? AND (variant_id <=> ?)");
    $u->execute([$qty, $userId, $productId, $variantId]);
    if ($u->rowCount() === 0) {
      // optional: insert if not exists (defensive)
      $i = $pdo->prepare("
        INSERT INTO cart (user_id, product_id, variant_id, quantity)
        VALUES (?, ?, ?, ?)
      ");
      $i->execute([$userId, $productId, $variantId, $qty]);
    }
    return [true, null];
  } catch (Throwable $e) {
    return [false, $e->getMessage()];
  }
}

/** Remove a line by (user, product, variant). Returns [ok, err] */
function cart_remove(PDO $pdo, int $userId, int $productId, ?int $variantId): array {
  try {
    $d = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ? AND (variant_id <=> ?)");
    $d->execute([$userId, $productId, $variantId]);
    return [true, null];
  } catch (Throwable $e) {
    return [false, $e->getMessage()];
  }
}
