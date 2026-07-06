<?php
require __DIR__ . '/db.php';

$id   = (int)($_GET['id'] ?? 0);
$plan = db()->prepare('SELECT * FROM ppm_plans WHERE id = ?')->execute([$id])
    ? db()->prepare('SELECT * FROM ppm_plans WHERE id = ?') : null;

$stmt = db()->prepare('SELECT * FROM ppm_plans WHERE id = ?');
$stmt->execute([$id]);
$plan = $stmt->fetch();
if (!$plan) { header('Location: index.php'); exit; }

$issues = db()->prepare(
    'SELECT * FROM ppm_issues WHERE plan_id = ? ORDER BY created_at DESC'
);
$issues->execute([$id]);
$issues = $issues->fetchAll();

$msg = $_GET['msg'] ?? '';

// Calibration points with GPS recorded (for "Locate me")
$calibStmt = db()->prepare(
    'SELECT plan_x, plan_y, lat, lng FROM ppm_calibration WHERE plan_id=? AND lat IS NOT NULL ORDER BY created_at ASC'
);
$calibStmt->execute([$id]);
$calibPoints = $calibStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($plan['name']) ?> — Pin Position Map</title>
<link rel="stylesheet" href="assets/style.css">
<style>
  .viewer-layout { display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start; }
  @media (max-width: 900px) { .viewer-layout { grid-template-columns: 1fr; } }
  #issue-form { display: none; }
</style>
</head>
<body>
<header>
  <span class="brand">Pin Position Map</span>
  <a href="index.php">Plans</a>
  <a href="manager.php">Manager view</a>
</header>
<div class="container">
  <div class="flex-between" style="margin-bottom:1rem">
    <h1><?= htmlspecialchars($plan['name']) ?></h1>
    <a href="index.php" class="btn btn-ghost btn-sm">&larr; All plans</a>
  </div>

  <?php if ($msg === 'created'): ?>
    <div class="alert alert-ok">Issue created successfully.</div>
  <?php endif; ?>

  <div class="viewer-layout">
    <!-- Plan image with pin overlay -->
    <div>
      <div id="plan-wrapper">
        <div id="plan-canvas">
          <img id="plan-img"
               src="<?= htmlspecialchars($plan['render_path']) ?>"
               alt="<?= htmlspecialchars($plan['name']) ?>">
          <!-- Existing issue pins -->
          <?php foreach ($issues as $iss): ?>
          <a href="issue_view.php?id=<?= $iss['id'] ?>"
             class="pin-marker pin-existing"
             title="<?= htmlspecialchars($iss['title']) ?>"
             style="left:<?= ($iss['pin_x']*100) ?>%;top:<?= ($iss['pin_y']*100) ?>%">
            <svg viewBox="0 0 28 28" xmlns="http://www.w3.org/2000/svg">
              <circle cx="14" cy="11" r="8" fill="#e03131" stroke="#fff" stroke-width="2"/>
              <line x1="14" y1="19" x2="14" y2="27" stroke="#e03131" stroke-width="2.5"/>
            </svg>
          </a>
          <?php endforeach; ?>
          <!-- New pin (shown after click) -->
          <div id="new-pin" class="pin-marker pin-new" style="display:none">
            <svg viewBox="0 0 28 28" xmlns="http://www.w3.org/2000/svg">
              <circle cx="14" cy="11" r="8" fill="#3b5bdb" stroke="#fff" stroke-width="2"/>
              <line x1="14" y1="19" x2="14" y2="27" stroke="#3b5bdb" stroke-width="2.5"/>
            </svg>
          </div>
        </div>
      </div>
      <div class="zoom-controls">
        <button id="btn-zoom-in"    class="btn btn-ghost btn-sm">+</button>
        <span   id="zoom-pct">100%</span>
        <button id="btn-zoom-out"   class="btn btn-ghost btn-sm">−</button>
        <button id="btn-zoom-reset" class="btn btn-ghost btn-sm">Reset</button>
        <?php if (count($calibPoints) >= 2): ?>
        <button id="btn-locate" class="btn btn-primary btn-sm" style="margin-left:auto">&#9678; Locate me</button>
        <?php endif; ?>
      </div>
      <p id="plan-hint">Click anywhere on the plan to place a pin and report an issue.</p>
    </div>

    <!-- Issue creation form (appears after a click) -->
    <div>
      <form id="issue-form" method="post" action="issue_new.php">
        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
        <input type="hidden" id="pin_x" name="pin_x" value="">
        <input type="hidden" id="pin_y" name="pin_y" value="">

        <div class="card">
          <h2>New issue</h2>
          <p style="font-size:.8rem;color:#888;margin-bottom:.9rem">
            Pin placed at <code id="pin-coords">—</code>
          </p>
          <div class="field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required placeholder="Short description">
          </div>
          <div class="field">
            <label for="description">Details <small>(optional)</small></label>
            <textarea id="description" name="description" placeholder="More context…"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Submit issue</button>
          <button type="button" id="cancel-pin" class="btn btn-ghost" style="margin-left:.4rem">Cancel</button>
        </div>
      </form>

      <!-- Issues list -->
      <?php if ($issues): ?>
      <div class="card" style="margin-top:<?= empty($issues) ? '0' : '1.25rem' ?>">
        <h2>Issues on this plan</h2>
        <table>
          <thead>
            <tr><th>#</th><th>Title</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php foreach ($issues as $iss): ?>
            <tr>
              <td><?= $iss['id'] ?></td>
              <td><a href="issue_view.php?id=<?= $iss['id'] ?>"><?= htmlspecialchars($iss['title']) ?></a></td>
              <td><span class="badge badge-<?= $iss['status'] ?>"><?= str_replace('_', ' ', $iss['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const wrapper    = document.getElementById('plan-wrapper');
const canvas     = document.getElementById('plan-canvas');
const newPin     = document.getElementById('new-pin');
const form       = document.getElementById('issue-form');
const cancelBtn  = document.getElementById('cancel-pin');
const coordsEl   = document.getElementById('pin-coords');
const pinXInput  = document.getElementById('pin_x');
const pinYInput  = document.getElementById('pin_y');
const zoomPctEl  = document.getElementById('zoom-pct');

const ZOOM_MIN = 1, ZOOM_MAX = 8, ZOOM_STEP = 1.3, DRAG_THRESHOLD = 4;
let zoom = 1, panX = 0, panY = 0;
let isDragging = false, hasDragged = false;
let dragStartX = 0, dragStartY = 0, dragStartPanX = 0, dragStartPanY = 0;

function clampPan() {
  const wW = wrapper.clientWidth,  wH = wrapper.clientHeight;
  const cW = canvas.offsetWidth,   cH = canvas.offsetHeight;
  panX = cW * zoom <= wW ? 0 : Math.min(0, Math.max(panX, wW - cW * zoom));
  panY = cH * zoom <= wH ? 0 : Math.min(0, Math.max(panY, wH - cH * zoom));
}

function applyTransform() {
  canvas.style.transform = `translate(${panX}px,${panY}px) scale(${zoom})`;
  zoomPctEl.textContent  = Math.round(zoom * 100) + '%';
  wrapper.style.cursor   = zoom > 1 ? 'grab' : 'crosshair';
}

function zoomToward(newZoom, pivotX, pivotY) {
  newZoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, newZoom));
  panX = pivotX - (pivotX - panX) * (newZoom / zoom);
  panY = pivotY - (pivotY - panY) * (newZoom / zoom);
  zoom = newZoom;
  clampPan();
  applyTransform();
}

// Scroll to zoom
wrapper.addEventListener('wheel', (e) => {
  e.preventDefault();
  const r = wrapper.getBoundingClientRect();
  zoomToward(zoom * (e.deltaY > 0 ? 1 / ZOOM_STEP : ZOOM_STEP), e.clientX - r.left, e.clientY - r.top);
}, { passive: false });

// Zoom buttons
document.getElementById('btn-zoom-in').addEventListener('click', () =>
  zoomToward(zoom * ZOOM_STEP, wrapper.clientWidth / 2, wrapper.clientHeight / 2));
document.getElementById('btn-zoom-out').addEventListener('click', () =>
  zoomToward(zoom / ZOOM_STEP, wrapper.clientWidth / 2, wrapper.clientHeight / 2));
document.getElementById('btn-zoom-reset').addEventListener('click', () => {
  zoom = 1; panX = 0; panY = 0; applyTransform();
});

// Drag to pan
wrapper.addEventListener('mousedown', (e) => {
  isDragging = true; hasDragged = false;
  dragStartX = e.clientX; dragStartY = e.clientY;
  dragStartPanX = panX; dragStartPanY = panY;
  e.preventDefault();
});
window.addEventListener('mousemove', (e) => {
  if (!isDragging) return;
  const dx = e.clientX - dragStartX, dy = e.clientY - dragStartY;
  if (!hasDragged && Math.hypot(dx, dy) > DRAG_THRESHOLD) {
    hasDragged = true;
    wrapper.style.cursor = 'grabbing';
  }
  if (hasDragged && zoom > 1) {
    panX = dragStartPanX + dx;
    panY = dragStartPanY + dy;
    clampPan();
    applyTransform();
  }
});
window.addEventListener('mouseup', () => {
  isDragging = false;
  applyTransform(); // restore cursor
});

// Click to place pin
wrapper.addEventListener('click', (e) => {
  if (hasDragged) return;
  if (e.target.closest('a.pin-marker')) return;

  const r = wrapper.getBoundingClientRect();
  const x = (e.clientX - r.left - panX) / zoom / canvas.offsetWidth;
  const y = (e.clientY - r.top  - panY) / zoom / canvas.offsetHeight;
  if (x < 0 || x > 1 || y < 0 || y > 1) return;

  pinXInput.value = x.toFixed(8);
  pinYInput.value = y.toFixed(8);
  newPin.style.left    = (x * 100) + '%';
  newPin.style.top     = (y * 100) + '%';
  newPin.style.display = 'block';
  coordsEl.textContent = x.toFixed(4) + ', ' + y.toFixed(4);
  form.style.display   = 'block';
  document.getElementById('title').focus();
});

cancelBtn.addEventListener('click', () => {
  form.style.display   = 'none';
  newPin.style.display = 'none';
  pinXInput.value = '';
  pinYInput.value = '';
});

// ── GPS auto-locate ──────────────────────────────────────────────────────────
<?php if (count($calibPoints) >= 2): ?>
const calibPoints = <?= json_encode(array_map(fn($p) => [
  'plan_x' => (float)$p['plan_x'],
  'plan_y' => (float)$p['plan_y'],
  'lat'    => (float)$p['lat'],
  'lng'    => (float)$p['lng'],
], $calibPoints)) ?>;

function solveSimilarity(pts) {
  // Least-squares similarity transform: GPS (lat,lng) → plan (x,y)
  // Similarity = scale + rotation + translation (4 params: A, B, tx, ty)
  //   x_i = A*lat_i - B*lng_i + tx
  //   y_i = B*lat_i + A*lng_i + ty
  // Center GPS coords first for numerical stability.
  const latMean = pts.reduce((s, p) => s + p.lat, 0) / pts.length;
  const lngMean = pts.reduce((s, p) => s + p.lng, 0) / pts.length;

  let SLL = 0, SL = 0, SG = 0, SxLyG = 0, SyLxG = 0, Sx = 0, Sy = 0;
  for (const p of pts) {
    const L = p.lat - latMean, G = p.lng - lngMean;
    const X = p.plan_x,        Y = p.plan_y;
    SLL   += L*L + G*G;
    SL    += L;   SG    += G;
    SxLyG += X*L + Y*G;
    SyLxG += Y*L - X*G;
    Sx    += X;   Sy    += Y;
  }

  // M^T M * [A, B, tx, ty]^T = M^T b
  const n = pts.length;
  const M = [
    [SLL,  0,   SL,  SG,  SxLyG],
    [0,   SLL, -SG,  SL,  SyLxG],
    [SL,  -SG,  n,   0,   Sx   ],
    [SG,   SL,  0,   n,   Sy   ],
  ];

  // Gaussian elimination with partial pivoting
  for (let col = 0; col < 4; col++) {
    let maxRow = col;
    for (let row = col+1; row < 4; row++)
      if (Math.abs(M[row][col]) > Math.abs(M[maxRow][col])) maxRow = row;
    [M[col], M[maxRow]] = [M[maxRow], M[col]];
    for (let row = col+1; row < 4; row++) {
      const f = M[row][col] / M[col][col];
      for (let k = col; k <= 4; k++) M[row][k] -= f * M[col][k];
    }
  }
  const r = [0, 0, 0, 0];
  for (let row = 3; row >= 0; row--) {
    r[row] = M[row][4];
    for (let col = row+1; col < 4; col++) r[row] -= M[row][col] * r[col];
    r[row] /= M[row][row];
  }
  // r = [A, B, tx_centered, ty_centered]
  // Convert back to uncentered GPS:
  //   x = A*(lat-latMean) - B*(lng-lngMean) + tx'
  //     = A*lat - B*lng + (tx' - A*latMean + B*lngMean)
  const A = r[0], B = r[1];
  return {
    A, B,
    tx: r[2] - A * latMean + B * lngMean,
    ty: r[3] - B * latMean - A * lngMean,
  };
}

const locateBtn = document.getElementById('btn-locate');
const transform = solveSimilarity(calibPoints);

locateBtn.addEventListener('click', () => {
  if (!navigator.geolocation) {
    alert('Geolocation is not supported by this browser.');
    return;
  }
  locateBtn.textContent = 'Locating…';
  locateBtn.disabled    = true;

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      locateBtn.textContent = '⊙ Locate me';
      locateBtn.disabled    = false;

      const { A, B, tx, ty } = transform;
      const x = A * pos.coords.latitude  - B * pos.coords.longitude + tx;
      const y = B * pos.coords.latitude  + A * pos.coords.longitude + ty;

      if (x < 0 || x > 1 || y < 0 || y > 1) {
        alert('Your GPS position appears to be outside this floor plan.\n'
            + 'Accuracy: ±' + Math.round(pos.coords.accuracy) + ' m');
        return;
      }

      pinXInput.value = x.toFixed(8);
      pinYInput.value = y.toFixed(8);
      newPin.style.left    = (x * 100) + '%';
      newPin.style.top     = (y * 100) + '%';
      newPin.style.display = 'block';
      coordsEl.textContent = x.toFixed(4) + ', ' + y.toFixed(4);
      form.style.display   = 'block';

      const acc = Math.round(pos.coords.accuracy);
      document.getElementById('title').placeholder = 'GPS accuracy: ±' + acc + ' m — adjust pin if needed';
      document.getElementById('title').focus();
    },
    (err) => {
      locateBtn.textContent = '⊙ Locate me';
      locateBtn.disabled    = false;
      alert('Could not get location: ' + err.message);
    },
    { enableHighAccuracy: true, timeout: 15000 }
  );
});
<?php endif; ?>

applyTransform();
</script>
</body>
</html>
