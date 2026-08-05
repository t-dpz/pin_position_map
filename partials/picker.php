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
          <svg viewBox="0 0 24 32" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="pmw-pin-grad-<?= htmlspecialchars($id) ?>" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#6c8bff"/>
                <stop offset="100%" stop-color="#3b5bdb"/>
              </linearGradient>
            </defs>
            <path d="M12 0C5.373 0 0 5.373 0 12c0 9 12 20 12 20s12-11 12-20C24 5.373 18.627 0 12 0z"
                  fill="url(#pmw-pin-grad-<?= htmlspecialchars($id) ?>)" stroke="#fff" stroke-width="1.5"/>
            <circle cx="12" cy="12" r="4.5" fill="#fff"/>
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
