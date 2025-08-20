<?php
// api/shipping/states.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/shipping.php';
header('Content-Type: application/json');
$country = strtoupper(trim((string)($_GET['country'] ?? '')));
if ($country !== 'NG') { echo json_encode(['success'=>true,'states'=>[]]); exit; }
echo json_encode(['success'=>true,'states'=>nigeria_states()]);
