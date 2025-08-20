<?php
// admin/products/attributes_api.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../partials/auth.php';
require_admin();

header('Content-Type: application/json');

$catId = (int)($_GET['category_id'] ?? 0);
if ($catId <= 0) { echo json_encode(['occasion'=>[], 'length'=>[], 'style'=>[]]); exit; }

$stmt = $pdo->prepare("
  SELECT a.id, a.value, t.code AS type_code
  FROM attributes a
  JOIN attribute_types t ON t.id = a.type_id
  JOIN category_attribute_allowed caa ON caa.attribute_id = a.id
  WHERE caa.category_id = ?
  ORDER BY t.code, a.value
");
$stmt->execute([$catId]);

$out = ['occasion'=>[], 'length'=>[], 'style'=>[]];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $key = strtolower((string)$r['type_code']);
  if (!isset($out[$key])) $out[$key] = [];
  $out[$key][] = ['id'=>(int)$r['id'], 'value'=>$r['value']];
}
echo json_encode($out);
