<?php
require __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = pin_db()->prepare('SELECT * FROM pin_demo_issues WHERE id = ?');
$stmt->execute([$id]);
$issue = $stmt->fetch();
if (!$issue) { header('Location: issue_list.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Set location — <?= htmlspecialchars($issue['title']) ?></title>
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
    <h1>Set location — <?= htmlspecialchars($issue['title']) ?></h1>
    <a href="issue_view.php?id=<?= $issue['id'] ?>" class="btn btn-ghost btn-sm">&larr; Back to issue</a>
  </div>

  <div class="card">
    <?php pin_render_picker(['issue_id' => $issue['id']]); ?>
  </div>
</div>
</body>
</html>
