<?php
require __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = pin_db()->prepare('SELECT * FROM pin_demo_issues WHERE id = ?');
$stmt->execute([$id]);
$issue = $stmt->fetch();
if (!$issue) { header('Location: issue_list.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $allowed = ['open', 'in_progress', 'resolved'];
    if (in_array($_POST['status'], $allowed, true)) {
        pin_db()->prepare('UPDATE pin_demo_issues SET status = ? WHERE id = ?')
                 ->execute([$_POST['status'], $id]);
        $issue['status'] = $_POST['status'];
    }
}

$loc = pin_get_location($id);
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($issue['title']) ?> — Pin location demo</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <span class="brand">Pin location demo</span>
  <a href="issue_new.php">Report issue</a>
  <a href="issue_list.php">Issue list</a>
</header>
<div class="container">
  <div class="flex-between" style="margin-bottom:1rem">
    <h1><?= htmlspecialchars($issue['title']) ?></h1>
    <a href="issue_list.php" class="btn btn-ghost btn-sm">&larr; All issues</a>
  </div>

  <?php if ($msg === 'created'): ?>
    <div class="alert alert-ok">Issue created successfully.</div>
  <?php endif; ?>

  <div class="two-col">
    <div>
      <div class="card">
        <div class="issue-meta">
          Logged <?= date('d M Y H:i', strtotime($issue['created_at'])) ?>
        </div>
        <?php if ($issue['description']): ?>
          <p style="margin-bottom:1rem"><?= nl2br(htmlspecialchars($issue['description'])) ?></p>
        <?php else: ?>
          <p style="color:#999;font-style:italic;margin-bottom:1rem">No additional details.</p>
        <?php endif; ?>

        <form method="post" style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
          <label style="font-size:.85rem;font-weight:600">Status:</label>
          <select name="status" style="padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px;font-size:.875rem">
            <?php foreach (['open', 'in_progress', 'resolved'] as $s): ?>
              <option value="<?= $s ?>" <?= $issue['status'] === $s ? 'selected' : '' ?>>
                <?= str_replace('_', ' ', $s) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
          <span class="badge badge-<?= $issue['status'] ?>"><?= str_replace('_', ' ', $issue['status']) ?></span>
        </form>
      </div>
    </div>

    <div>
      <div class="card">
        <h2>Location</h2>
        <?php if ($loc): ?>
          <?php pin_render_viewer(['floor' => $loc['floor'], 'x' => $loc['pin_x'], 'y' => $loc['pin_y']]); ?>
        <?php else: ?>
          <p style="color:#888">No location was pinned for this issue.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
