<?php
require __DIR__ . '/db.php';

$issues = pin_db()->query('SELECT * FROM pin_demo_issues ORDER BY created_at DESC')->fetchAll();
$msg    = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Issues — location_picker demo</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <span class="brand">location_picker demo</span>
  <a href="issue_new.php">Report issue</a>
  <a href="issue_list.php">Issue list</a>
</header>
<div class="container">
  <div class="flex-between" style="margin-bottom:1rem">
    <h1>Issues</h1>
    <a href="issue_new.php" class="btn btn-primary btn-sm">+ Report issue</a>
  </div>

  <?php if ($msg === 'created'): ?>
    <div class="alert alert-ok">Issue created successfully.</div>
  <?php endif; ?>

  <div class="card">
    <?php if (!$issues): ?>
      <p style="color:#888">No issues yet.</p>
    <?php else: ?>
    <table>
      <thead>
        <tr><th>#</th><th>Title</th><th>Floor</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($issues as $iss):
          $loc   = pin_get_location($iss['id']);
          $floor = $loc ? pin_floor_info($loc['floor']) : null;
        ?>
        <tr>
          <td><?= (int)$iss['id'] ?></td>
          <td><a href="issue_view.php?id=<?= $iss['id'] ?>"><?= htmlspecialchars($iss['title']) ?></a></td>
          <td><?= $floor ? htmlspecialchars($floor['label']) : '<span style="color:#aaa">—</span>' ?></td>
          <td><span class="badge badge-<?= $iss['status'] ?>"><?= str_replace('_', ' ', $iss['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
