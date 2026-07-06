<?php
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$planId = (int)($_POST['plan_id'] ?? 0);
$title  = trim($_POST['title'] ?? '');
$desc   = trim($_POST['description'] ?? '');
$pinX   = (float)($_POST['pin_x'] ?? -1);
$pinY   = (float)($_POST['pin_y'] ?? -1);

if (!$planId || $title === '' || $pinX < 0 || $pinX > 1 || $pinY < 0 || $pinY > 1) {
    header('Location: index.php?err=invalid'); exit;
}

$stmt = db()->prepare('SELECT id FROM ppm_plans WHERE id = ?');
$stmt->execute([$planId]);
if (!$stmt->fetch()) { header('Location: index.php'); exit; }

db()->prepare(
    'INSERT INTO ppm_issues (plan_id, title, description, pin_x, pin_y) VALUES (?, ?, ?, ?, ?)'
)->execute([$planId, $title, $desc ?: null, $pinX, $pinY]);

header('Location: plan_view.php?id=' . $planId . '&msg=created');
exit;
