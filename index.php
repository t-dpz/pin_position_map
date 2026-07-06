<?php
require __DIR__ . '/db.php';

$plans = db()->query(
    'SELECT p.*, COUNT(i.id) AS issue_count
     FROM ppm_plans p
     LEFT JOIN ppm_issues i ON i.plan_id = p.id
     GROUP BY p.id
     ORDER BY p.created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pin Position Map</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <span class="brand">Pin Position Map</span>
  <a href="index.php">Plans</a>
  <a href="manager.php">Manager view</a>
  <a href="plan_upload.php" class="btn btn-primary btn-sm" style="margin-left:auto">+ Upload plan</a>
</header>
<div class="container">
  <h1>Floor plans</h1>
  <?php if (empty($plans)): ?>
    <div class="card" style="text-align:center;padding:2.5rem;color:#888">
      No floor plans yet. <a href="plan_upload.php">Upload the first one.</a>
    </div>
  <?php else: ?>
  <div class="plan-grid">
    <?php foreach ($plans as $p): ?>
    <div class="plan-card">
      <a href="plan_view.php?id=<?= $p['id'] ?>">
        <img src="<?= htmlspecialchars($p['render_path']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
      </a>
      <div class="plan-card-body">
        <h3><?= htmlspecialchars($p['name']) ?></h3>
        <div class="plan-card-meta">
          <?= (int)$p['issue_count'] ?> issue<?= $p['issue_count'] != 1 ? 's' : '' ?>
          &middot; <?= date('d M Y', strtotime($p['created_at'])) ?>
        </div>
        <div style="display:flex;gap:.4rem;align-items:center">
          <a href="plan_view.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">Open plan</a>
          <a href="plan_calibrate.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" title="Calibrate GPS">GPS</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
