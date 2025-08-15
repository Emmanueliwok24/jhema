<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

list($currencies, $baseCode) = get_currencies($pdo);
$display = isset($_GET['cur']) ? strtoupper($_GET['cur']) : $baseCode;

$curMap = [];
foreach ($currencies as $c) $curMap[$c['code']] = $c;
if (!isset($curMap[$display])) $display = $baseCode;

$cats = fetch_categories($pdo);

/** Build menu: for each category, show allowed Occasion / Length / Style */
$menu = [];
foreach ($cats as $cat) {
    $allowed = fetch_attributes_by_category($pdo, (int)$cat['id']);
    $menu[] = [
        'id' => $cat['id'],
        'name' => $cat['name'],
        'slug' => $cat['slug'],
        'allowed' => $allowed
    ];
}

function convert_price($amount, $fromCode, $toCode, $curMap) {
    if ($fromCode === $toCode) return $amount;
    $toBase = $amount * (float)$curMap[$fromCode]['rate_to_base'];
    return $toBase / (float)$curMap[$toCode]['rate_to_base'];
}

$category_slug = $_GET['cat'] ?? null;
$occasion = $_GET['occasion'] ?? null;
$length   = $_GET['length'] ?? null;
$style    = $_GET['style'] ?? null;

$where = [];
$params = [];
$joinAttr = '';

if ($category_slug) {
    $where[] = 'c.slug = ?';
    $params[] = $category_slug;
}

$attrFilters = [];
if ($occasion) $attrFilters[] = ['type'=>'occasion','value'=>$occasion];
if ($length)   $attrFilters[] = ['type'=>'length','value'=>$length];
if ($style)    $attrFilters[] = ['type'=>'style','value'=>$style];

if ($attrFilters) {
    $i = 0;
    foreach ($attrFilters as $f) {
        $i++;
        $joinAttr .= "
          JOIN product_attributes pa{$i} ON pa{$i}.product_id = p.id
          JOIN attributes a{$i} ON a{$i}.id = pa{$i}.attribute_id
          JOIN attribute_types t{$i} ON t{$i}.id = a{$i}.type_id AND t{$i}.code = ?
        ";
        $where[] = "a{$i}.value = ?";
        $params[] = $f['type'];
        $params[] = $f['value'];
    }
}

$sql = "
SELECT p.id, p.name, p.slug, p.sku, p.base_price, p.base_currency_code, p.image_path, c.name as cat_name
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
$joinAttr
" . ($where ? "WHERE " . implode(' AND ', $where) : "") . "
ORDER BY p.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>


<!DOCTYPE html>
<html dir="ltr" lang="zxx">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta name="author" content="flexkit" />

    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <!-- Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
      rel="stylesheet"
    />
    <link
      href="https://fonts.googleapis.com/css2?family=Allura&display=swap"
      rel="stylesheet"
    />
    <!-- Swiper JS -->

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Optional: Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">


    <!-- Fonts -->
  <link href="/css2?family=Jost:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
  <link href="/css2-1?family=Allura&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="css/plugins/swiper.min.css" type="text/css" />
  <link rel="stylesheet" href="css/plugins/jquery.fancybox.css" type="text/css">
    <link rel="stylesheet" href="css/style.css" type="text/css" />

    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!--[if lt IE 9]>
      <script src="http://css3-mediaqueries-js.googlecode.com/svn/trunk/css3-mediaqueries.js"></script>
    <![endif]-->

    <!-- Document Title -->
    <title>Home | jhema eCommerce || elevated elegance </title>
    <link rel="stylesheet" href="css/products-styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="shortcut icon" href="./images/favicon.png" type="image/x-icon">

  </head>

  <body>