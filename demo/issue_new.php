<?php
require __DIR__ . '/db.php';

$title       = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$issueId     = isset($_POST['issue_id']) ? (int)$_POST['issue_id'] : random_int(100000, 999999);
$errors      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($title);
    if ($title === '') $errors[] = 'Title is required.';

    if (!$errors) {
        $stmt = pin_db()->prepare('INSERT INTO pin_demo_issues (id, title, description) VALUES (?, ?, ?)');
        $stmt->execute([$issueId, $title, $description !== '' ? $description : null]);

        header('Location: issue_view.php?id=' . $issueId . '&msg=created');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Report an issue — Pin location demo</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <span class="brand">Pin location demo</span>
  <a href="issue_new.php">Report issue</a>
  <a href="issue_list.php">Issue list</a>
</header>
<div class="container">
  <h1>Report an issue</h1>

  <?php if ($errors): ?>
    <div class="alert alert-error"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="post" novalidate>
      <input type="hidden" name="issue_id" value="<?= (int)$issueId ?>">

      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" required
               value="<?= htmlspecialchars($title) ?>" placeholder="e.g. Broken window">
      </div>
      <div class="field">
        <label for="description">Details <small>(optional)</small></label>
        <textarea id="description" name="description"
                  placeholder="More context…"><?= htmlspecialchars($description) ?></textarea>
      </div>

      <div class="field">
        <label>Location <small>(optional)</small></label>
        <?php pin_render_picker(['issue_id' => $issueId]); ?>
      </div>

      <button type="submit" class="btn btn-primary">Submit issue</button>
    </form>
  </div>
</div>
</body>
</html>
