<?php
require __DIR__ . '/lib.php';

header('Content-Type: application/json');

$issueId = (int)($_POST['issue_id'] ?? 0);
$floor   = (string)($_POST['floor'] ?? '');
$x       = $_POST['x'] ?? '';
$y       = $_POST['y'] ?? '';

if ($issueId <= 0 || !pin_floor_info($floor) || $x === '' || $y === '' || (float)$x < 0 || (float)$x > 1 || (float)$y < 0 || (float)$y > 1) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

pin_save_location($issueId, $floor, (float)$x, (float)$y);
echo json_encode(['ok' => true]);
