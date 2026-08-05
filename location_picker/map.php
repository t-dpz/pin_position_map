<?php
require __DIR__ . '/lib.php';

$floor = (string)($_GET['floor'] ?? '');
$info  = pin_floor_info($floor);
if (!$info) { http_response_code(404); exit; }

$path = PIN_MAPS_DIR . '/' . $info['file'];
if (!is_file($path)) { http_response_code(404); exit; }

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=3600');
readfile($path);
