<?php
require __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT i.*, p.name AS plan_name, p.render_path, p.render_width, p.render_height, p.id AS plan_id
     FROM ppm_issues i JOIN ppm_plans p ON p.id = i.plan_id
     WHERE i.id = ?'
);
$stmt->execute([$id]);
$issue = $stmt->fetch();
if (!$issue) { header('Location: index.php'); exit; }

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $allowed = ['open', 'in_progress', 'resolved'];
    if (in_array($_POST['status'], $allowed, true)) {
        db()->prepare('UPDATE ppm_issues SET status = ? WHERE id = ?')
           ->execute([$_POST['status'], $id]);
        $issue['status'] = $_POST['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($issue['title']) ?> — Pin Position Map</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <span class="brand">Pin Position Map</span>
  <a href="index.php">Plans</a>
  <a href="manager.php">Manager view</a>
</header>
<div class="container">
  <div class="flex-between" style="margin-bottom:1rem">
    <h1><?= htmlspecialchars($issue['title']) ?></h1>
    <a href="plan_view.php?id=<?= $issue['plan_id'] ?>" class="btn btn-ghost btn-sm">
      &larr; <?= htmlspecialchars($issue['plan_name']) ?>
    </a>
  </div>

  <div class="two-col">
    <!-- Left: details + status -->
    <div>
      <div class="card">
        <div class="issue-meta">
          Plan: <strong><?= htmlspecialchars($issue['plan_name']) ?></strong>
          &middot; Logged <?= date('d M Y H:i', strtotime($issue['created_at'])) ?>
          &middot; Pin: (<?= number_format($issue['pin_x'], 4) ?>, <?= number_format($issue['pin_y'], 4) ?>)
        </div>

        <?php if ($issue['description']): ?>
          <p style="margin-bottom:1rem"><?= nl2br(htmlspecialchars($issue['description'])) ?></p>
        <?php else: ?>
          <p style="color:#999;font-style:italic;margin-bottom:1rem">No additional details.</p>
        <?php endif; ?>

        <form method="post" style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
          <label style="font-size:.85rem;font-weight:600">Status:</label>
          <select name="status" style="padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px;font-size:.875rem">
            <?php foreach (['open','in_progress','resolved'] as $s): ?>
              <option value="<?= $s ?>" <?= $issue['status'] === $s ? 'selected' : '' ?>>
                <?= str_replace('_',' ',$s) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
          <span class="badge badge-<?= $issue['status'] ?>"><?= str_replace('_',' ',$issue['status']) ?></span>
        </form>
      </div>
    </div>

    <!-- Right: zoomed crop -->
    <div>
      <div class="card">
        <h2>Location on plan</h2>
        <p style="font-size:.8rem;color:#888;margin-bottom:.75rem">
          400 × 400 px crop centered on pin
        </p>
        <div class="crop-frame">
          <img src="crop.php?issue_id=<?= $issue['id'] ?>&size=400"
               width="400" height="400"
               alt="Pin location crop"
               style="max-width:100%;height:auto">
        </div>
        <p style="margin-top:.6rem;font-size:.78rem;color:#888">
          <a href="plan_view.php?id=<?= $issue['plan_id'] ?>">View full plan &rarr;</a>
        </p>
      </div>
    </div>
  </div>
</div>
</body>
</html>
