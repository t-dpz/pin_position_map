<?php
require __DIR__ . '/db.php';

$id   = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM ppm_plans WHERE id = ?');
$stmt->execute([$id]);
$plan = $stmt->fetch();
if (!$plan) { header('Location: index.php'); exit; }

$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_point') {
        $px    = (float)($_POST['plan_x'] ?? 0);
        $py    = (float)($_POST['plan_y'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        if ($px >= 0 && $px <= 1 && $py >= 0 && $py <= 1) {
            $s = db()->prepare('INSERT INTO ppm_calibration (plan_id, label, plan_x, plan_y) VALUES (?,?,?,?)');
            $s->execute([$id, $label, $px, $py]);
        }
        header('Location: plan_calibrate.php?id=' . $id); exit;
    }

    if ($action === 'set_gps') {
        $point_id = (int)($_POST['point_id'] ?? 0);
        $lat      = (float)($_POST['lat'] ?? 0);
        $lng      = (float)($_POST['lng'] ?? 0);
        if ($point_id && $lat && $lng) {
            $s = db()->prepare(
                'UPDATE ppm_calibration SET lat=?, lng=? WHERE id=? AND plan_id=?'
            );
            $s->execute([$lat, $lng, $point_id, $id]);
        }
        header('Location: plan_calibrate.php?id=' . $id); exit;
    }

    if ($action === 'delete_point') {
        $point_id = (int)($_POST['point_id'] ?? 0);
        if ($point_id) {
            $s = db()->prepare('DELETE FROM ppm_calibration WHERE id=? AND plan_id=?');
            $s->execute([$point_id, $id]);
        }
        header('Location: plan_calibrate.php?id=' . $id); exit;
    }
}

$stmt = db()->prepare('SELECT * FROM ppm_calibration WHERE plan_id=? ORDER BY created_at ASC');
$stmt->execute([$id]);
$points = $stmt->fetchAll();

$completeCount = count(array_filter($points, fn($p) => $p['lat'] !== null));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Calibrate — <?= htmlspecialchars($plan['name']) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
  .viewer-layout { display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; align-items: start; }
  @media (max-width: 900px) { .viewer-layout { grid-template-columns: 1fr; } }

  #add-point-form { display: none; }

  .calib-point {
    display: flex; align-items: flex-start; gap: .75rem;
    padding: .6rem 0; border-bottom: 1px solid #eee;
  }
  .calib-point:last-child { border-bottom: none; }
  .calib-dot {
    width: 18px; height: 18px; border-radius: 50%; flex-shrink: 0; margin-top: 2px;
    border: 2px solid #fff; box-shadow: 0 0 0 2px currentColor;
  }
  .calib-dot.complete { background: #2f9e44; color: #2f9e44; }
  .calib-dot.pending  { background: #f08c00; color: #f08c00; }

  .calib-actions { display: flex; gap: .4rem; margin-top: .4rem; flex-wrap: wrap; }

  /* Marker on the plan for calibration points */
  .calib-marker {
    position: absolute;
    width: 18px; height: 18px;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px currentColor, 0 2px 4px rgba(0,0,0,.3);
    pointer-events: none;
    font-size: 10px;
    color: #888;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
  }
  .calib-marker.complete { background: #2f9e44; color: #2f9e44; }
  .calib-marker.pending  { background: #f08c00; color: #f08c00; }
  .calib-marker span { color: #fff; }

  .steps { counter-reset: step; }
  .step { display: flex; gap: .75rem; margin-bottom: .75rem; align-items: flex-start; }
  .step::before {
    counter-increment: step;
    content: counter(step);
    background: #3b5bdb; color: #fff;
    border-radius: 50%; width: 22px; height: 22px; min-width: 22px;
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; font-weight: 700; margin-top: 1px;
  }
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
    <h1>Calibrate GPS — <?= htmlspecialchars($plan['name']) ?></h1>
    <a href="plan_view.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">&larr; Back to plan</a>
  </div>

  <?php if ($completeCount >= 2): ?>
    <div class="alert alert-ok" style="margin-bottom:1rem">
      <?= $completeCount ?> GPS point<?= $completeCount > 1 ? 's' : '' ?> recorded — "Locate me" is active on this plan.
    </div>
  <?php else: ?>
    <div class="alert" style="background:#fff3bf;border:1px solid #ffe066;color:#7d5a00;margin-bottom:1rem">
      Add at least 2 control points with GPS coordinates to enable auto-location.
      <?= $completeCount === 1 ? '1 point recorded, need 1 more.' : '' ?>
    </div>
  <?php endif; ?>

  <div class="card" style="padding:1rem;margin-bottom:1.25rem">
    <div class="steps">
      <div class="step"><div>Click a recognizable spot on the plan below to mark it, add a label, and save.</div></div>
      <div class="step"><div>Walk to that exact spot in real life, open this page on your phone, and tap <strong>Record GPS</strong> next to the point.</div></div>
      <div class="step"><div>Repeat for at least one more point (more points = better accuracy).</div></div>
    </div>
  </div>

  <div class="viewer-layout">
    <div>
      <div id="plan-wrapper">
        <div id="plan-canvas">
          <img id="plan-img"
               src="<?= htmlspecialchars($plan['render_path']) ?>"
               alt="<?= htmlspecialchars($plan['name']) ?>">
          <!-- Existing calibration markers -->
          <?php foreach ($points as $i => $pt): ?>
          <div class="calib-marker <?= $pt['lat'] !== null ? 'complete' : 'pending' ?>"
               style="left:<?= ($pt['plan_x']*100) ?>%;top:<?= ($pt['plan_y']*100) ?>%">
            <span><?= $i+1 ?></span>
          </div>
          <?php endforeach; ?>
          <!-- New point marker (shown after click) -->
          <div id="new-calib-marker" class="calib-marker pending" style="display:none">
            <span>+</span>
          </div>
        </div>
      </div>
      <div class="zoom-controls">
        <button id="btn-zoom-in"    class="btn btn-ghost btn-sm">+</button>
        <span   id="zoom-pct">100%</span>
        <button id="btn-zoom-out"   class="btn btn-ghost btn-sm">−</button>
        <button id="btn-zoom-reset" class="btn btn-ghost btn-sm">Reset</button>
        <span style="font-size:.78rem;color:#888;margin-left:.4rem">Scroll to zoom · drag to pan · click to mark</span>
      </div>
      <p id="plan-hint" style="font-size:.82rem;color:#555;margin-top:.4rem">
        Click the plan to place a calibration marker.
      </p>
    </div>

    <div>
      <!-- New point form -->
      <form id="add-point-form" method="post">
        <input type="hidden" name="action"  value="add_point">
        <input type="hidden" name="plan_id" value="<?= $id ?>">
        <input type="hidden" id="calib_x"  name="plan_x" value="">
        <input type="hidden" id="calib_y"  name="plan_y" value="">
        <div class="card">
          <h2>New control point</h2>
          <div class="field">
            <label for="calib-label">Label <small>(e.g. "Front door")</small></label>
            <input type="text" id="calib-label" name="label" placeholder="Recognisable landmark" required>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Save point</button>
          <button type="button" id="cancel-calib" class="btn btn-ghost btn-sm" style="margin-left:.4rem">Cancel</button>
        </div>
      </form>

      <!-- Existing points -->
      <div class="card">
        <h2>Control points</h2>
        <?php if (empty($points)): ?>
          <p style="color:#888;font-size:.875rem">No points yet — click the plan to add one.</p>
        <?php else: ?>
          <?php foreach ($points as $i => $pt): ?>
          <div class="calib-point">
            <div class="calib-dot <?= $pt['lat'] !== null ? 'complete' : 'pending' ?>"></div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:.875rem">
                <?= $i+1 ?>. <?= htmlspecialchars($pt['label'] ?: '(unlabelled)') ?>
              </div>
              <div style="font-size:.78rem;color:#888;margin-top:.15rem">
                Plan: <?= number_format($pt['plan_x']*100, 1) ?>%, <?= number_format($pt['plan_y']*100, 1) ?>%
              </div>
              <?php if ($pt['lat'] !== null): ?>
                <div style="font-size:.78rem;color:#2f9e44;margin-top:.1rem">
                  GPS: <?= round($pt['lat'], 6) ?>, <?= round($pt['lng'], 6) ?>
                </div>
              <?php else: ?>
                <div style="font-size:.78rem;color:#f08c00;margin-top:.1rem">GPS not yet recorded</div>
              <?php endif; ?>

              <div class="calib-actions">
                <?php if ($pt['lat'] === null): ?>
                <button class="btn btn-primary btn-sm record-gps-btn"
                        data-point-id="<?= $pt['id'] ?>">
                  Record GPS here
                </button>
                <?php else: ?>
                <button class="btn btn-ghost btn-sm record-gps-btn"
                        data-point-id="<?= $pt['id'] ?>">
                  Update GPS
                </button>
                <?php endif; ?>

                <form method="post" style="display:inline"
                      onsubmit="return confirm('Delete this point?')">
                  <input type="hidden" name="action"   value="delete_point">
                  <input type="hidden" name="plan_id"  value="<?= $id ?>">
                  <input type="hidden" name="point_id" value="<?= $pt['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Hidden GPS recording form (submitted by JS) -->
<form id="gps-form" method="post" style="display:none">
  <input type="hidden" name="action"   value="set_gps">
  <input type="hidden" name="plan_id"  value="<?= $id ?>">
  <input type="hidden" id="gps-point-id" name="point_id" value="">
  <input type="hidden" id="gps-lat"       name="lat"      value="">
  <input type="hidden" id="gps-lng"       name="lng"      value="">
</form>

<script>
// ── Zoom / pan (same as plan_view.php) ──────────────────────────────────────
const wrapper  = document.getElementById('plan-wrapper');
const canvas   = document.getElementById('plan-canvas');
const zoomPctEl = document.getElementById('zoom-pct');

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
wrapper.addEventListener('wheel', (e) => {
  e.preventDefault();
  const r = wrapper.getBoundingClientRect();
  zoomToward(zoom * (e.deltaY > 0 ? 1 / ZOOM_STEP : ZOOM_STEP), e.clientX - r.left, e.clientY - r.top);
}, { passive: false });
document.getElementById('btn-zoom-in').addEventListener('click', () =>
  zoomToward(zoom * ZOOM_STEP, wrapper.clientWidth / 2, wrapper.clientHeight / 2));
document.getElementById('btn-zoom-out').addEventListener('click', () =>
  zoomToward(zoom / ZOOM_STEP, wrapper.clientWidth / 2, wrapper.clientHeight / 2));
document.getElementById('btn-zoom-reset').addEventListener('click', () => {
  zoom = 1; panX = 0; panY = 0; applyTransform();
});
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
    hasDragged = true; wrapper.style.cursor = 'grabbing';
  }
  if (hasDragged && zoom > 1) {
    panX = dragStartPanX + dx; panY = dragStartPanY + dy;
    clampPan(); applyTransform();
  }
});
window.addEventListener('mouseup', () => { isDragging = false; applyTransform(); });

// ── Click to place new calibration marker ───────────────────────────────────
const newMarker   = document.getElementById('new-calib-marker');
const addForm     = document.getElementById('add-point-form');
const cancelBtn   = document.getElementById('cancel-calib');
const calibXInput = document.getElementById('calib_x');
const calibYInput = document.getElementById('calib_y');

wrapper.addEventListener('click', (e) => {
  if (hasDragged) return;
  const r = wrapper.getBoundingClientRect();
  const x = (e.clientX - r.left - panX) / zoom / canvas.offsetWidth;
  const y = (e.clientY - r.top  - panY) / zoom / canvas.offsetHeight;
  if (x < 0 || x > 1 || y < 0 || y > 1) return;

  calibXInput.value   = x.toFixed(8);
  calibYInput.value   = y.toFixed(8);
  newMarker.style.left    = (x * 100) + '%';
  newMarker.style.top     = (y * 100) + '%';
  newMarker.style.display = 'flex';
  addForm.style.display   = 'block';
  document.getElementById('calib-label').focus();
});

cancelBtn.addEventListener('click', () => {
  addForm.style.display   = 'none';
  newMarker.style.display = 'none';
  calibXInput.value = '';
  calibYInput.value = '';
});

// ── GPS recording ────────────────────────────────────────────────────────────
const gpsForm    = document.getElementById('gps-form');
const gpsPointId = document.getElementById('gps-point-id');
const gpsLat     = document.getElementById('gps-lat');
const gpsLng     = document.getElementById('gps-lng');

document.querySelectorAll('.record-gps-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    if (!navigator.geolocation) {
      alert('Geolocation is not supported by this browser.');
      return;
    }
    btn.textContent = 'Getting GPS…';
    btn.disabled    = true;

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        gpsPointId.value = btn.dataset.pointId;
        gpsLat.value     = pos.coords.latitude.toFixed(8);
        gpsLng.value     = pos.coords.longitude.toFixed(8);
        gpsForm.submit();
      },
      (err) => {
        btn.textContent = 'Record GPS here';
        btn.disabled    = false;
        alert('Could not get location: ' + err.message
            + '\n\nMake sure you are on HTTPS and have granted location permission.');
      },
      { enableHighAccuracy: true, timeout: 15000 }
    );
  });
});

applyTransform();
</script>
</body>
</html>
