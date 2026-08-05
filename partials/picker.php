<div class="pmw" id="<?= htmlspecialchars($id) ?>">
  <div class="pmw-field">
    <label for="<?= htmlspecialchars($id) ?>-floor">Floor</label>
    <select id="<?= htmlspecialchars($id) ?>-floor" class="pmw-floor-select">
      <option value="">Select a floor…</option>
      <?php foreach ($floors as $key => $f): ?>
        <option value="<?= htmlspecialchars((string)$key) ?>" <?= (string)$floor === (string)$key ? 'selected' : '' ?>>
          <?= htmlspecialchars($f['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="pmw-map-area" style="display:<?= $floor !== '' ? 'block' : 'none' ?>">
    <div class="pmw-wrapper">
      <div class="pmw-canvas-wrap">
        <canvas class="pmw-canvas"></canvas>
        <div class="pmw-pin" hidden>
          <svg viewBox="0 0 28 28" xmlns="http://www.w3.org/2000/svg">
            <circle cx="14" cy="11" r="8" fill="#3b5bdb" stroke="#fff" stroke-width="2"/>
            <line x1="14" y1="19" x2="14" y2="27" stroke="#3b5bdb" stroke-width="2.5"/>
          </svg>
        </div>
      </div>
    </div>
    <div class="pmw-zoom-controls">
      <button type="button" class="pmw-zoom-in">+</button>
      <span class="pmw-zoom-pct">100%</span>
      <button type="button" class="pmw-zoom-out">−</button>
      <button type="button" class="pmw-zoom-reset">Reset</button>
    </div>
    <p class="pmw-hint">Click anywhere on the map to place a pin. It saves automatically.</p>
    <p class="pmw-status" aria-live="polite"></p>
  </div>
</div>
<script>
  PinMapWidget.attachPicker(document.getElementById(<?= json_encode($id) ?>), {
    baseUrl: <?= json_encode(PIN_WIDGET_BASE_URL) ?>,
    issueId: <?= (int)$issueId ?>,
    floor: <?= json_encode($floor) ?>,
    x: <?= $x === '' ? 'null' : json_encode((float)$x) ?>,
    y: <?= $y === '' ? 'null' : json_encode((float)$y) ?>
  });
</script>
