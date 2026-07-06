<?php
require __DIR__ . '/db.php';

// Filter by plan or status
$filterPlan   = (int)($_GET['plan_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$allowed = ['', 'open', 'in_progress', 'resolved'];
if (!in_array($filterStatus, $allowed, true)) $filterStatus = '';

$where  = [];
$params = [];
if ($filterPlan)   { $where[] = 'i.plan_id = ?'; $params[] = $filterPlan; }
if ($filterStatus) { $where[] = 'i.status = ?';  $params[] = $filterStatus; }

$sql = 'SELECT i.*, p.name AS plan_name
        FROM ppm_issues i JOIN ppm_plans p ON p.id = i.plan_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY i.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$issues = $stmt->fetchAll();

$plans = db()->query('SELECT id, name FROM ppm_plans ORDER BY name')->fetchAll();

$counts = db()->query(
    "SELECT status, COUNT(*) AS n FROM ppm_issues GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manager view — Pin Position Map</title>
<link rel="stylesheet" href="assets/style.css">
<style>
  .stat-bar { display:flex; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
  .stat { background:#fff; border-radius:8px; padding:.75rem 1.25rem; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .stat .n { font-size:1.6rem; font-weight:700; line-height:1; }
  .stat .l { font-size:.75rem; color:#888; text-transform:uppercase; letter-spacing:.04em; margin-top:.2rem; }

  .issue-row { display:grid; grid-template-columns:90px 1fr auto; gap:1rem; align-items:center;
               padding:.65rem .75rem; border-bottom:1px solid #eee; }
  .issue-row:last-child { border-bottom:none; }
  .issue-row:hover { background:#fafbff; }
  .issue-row .crop-thumb { line-height:0; border-radius:4px; overflow:hidden; border:1px solid #ddd; }
  .issue-row .crop-thumb img { width:90px; height:68px; object-fit:cover; display:block; }
</style>
</head>
<body>
<header>
  <span class="brand">Pin Position Map</span>
  <a href="index.php">Plans</a>
  <a href="manager.php">Manager view</a>
</header>
<div class="container">
  <h1>Manager view</h1>

  <!-- Summary stats -->
  <div class="stat-bar">
    <div class="stat">
      <div class="n"><?= array_sum($counts) ?></div>
      <div class="l">Total</div>
    </div>
    <div class="stat">
      <div class="n" style="color:#b45309"><?= $counts['open'] ?? 0 ?></div>
      <div class="l">Open</div>
    </div>
    <div class="stat">
      <div class="n" style="color:#1864ab"><?= $counts['in_progress'] ?? 0 ?></div>
      <div class="l">In progress</div>
    </div>
    <div class="stat">
      <div class="n" style="color:#1a6b28"><?= $counts['resolved'] ?? 0 ?></div>
      <div class="l">Resolved</div>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" class="card" style="padding:1rem;margin-bottom:1rem;display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
    <div>
      <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Plan</label>
      <select name="plan_id" style="padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px;font-size:.875rem">
        <option value="0">All plans</option>
        <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $filterPlan === (int)$p['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Status</label>
      <select name="status" style="padding:.35rem .6rem;border:1px solid #ccc;border-radius:5px;font-size:.875rem">
        <option value="">All statuses</option>
        <?php foreach (['open','in_progress','resolved'] as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= str_replace('_',' ',$s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <?php if ($filterPlan || $filterStatus): ?>
      <a href="manager.php" class="btn btn-ghost btn-sm">Clear</a>
    <?php endif; ?>
  </form>

  <!-- Issues list -->
  <div class="card" style="padding:0">
    <?php if (empty($issues)): ?>
      <p style="padding:2rem;text-align:center;color:#888">No issues match the current filter.</p>
    <?php else: ?>
      <?php foreach ($issues as $iss): ?>
      <div class="issue-row">
        <div class="crop-thumb">
          <a href="issue_view.php?id=<?= $iss['id'] ?>">
            <img src="crop.php?issue_id=<?= $iss['id'] ?>&size=200"
                 loading="lazy"
                 alt="pin location">
          </a>
        </div>
        <div>
          <div style="font-weight:600;margin-bottom:.2rem">
            <a href="issue_view.php?id=<?= $iss['id'] ?>" style="color:inherit;text-decoration:none">
              <?= htmlspecialchars($iss['title']) ?>
            </a>
          </div>
          <div style="font-size:.78rem;color:#888">
            <?= htmlspecialchars($iss['plan_name']) ?>
            &middot; <?= date('d M Y', strtotime($iss['created_at'])) ?>
          </div>
          <?php if ($iss['description']): ?>
          <div style="font-size:.82rem;color:#555;margin-top:.25rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
            <?= htmlspecialchars($iss['description']) ?>
          </div>
          <?php endif; ?>
        </div>
        <div style="text-align:right;white-space:nowrap">
          <span class="badge badge-<?= $iss['status'] ?>"><?= str_replace('_',' ',$iss['status']) ?></span><br>
          <a href="issue_view.php?id=<?= $iss['id'] ?>" class="btn btn-ghost btn-sm" style="margin-top:.35rem">View</a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
