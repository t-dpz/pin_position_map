<div class="pmw" id="<?= htmlspecialchars($id) ?>">
  <?php if ($info): ?>
    <p class="pmw-floor-label">Floor: <strong><?= htmlspecialchars($info['label']) ?></strong></p>

    <div class="pmw-map-area">
      <div class="pmw-wrapper">
        <div class="pmw-canvas-wrap">
          <canvas class="pmw-canvas"></canvas>
          <div class="pmw-pin" hidden>
            <svg viewBox="0 0 28 28" xmlns="http://www.w3.org/2000/svg">
              <circle cx="14" cy="11" r="8" fill="#e03131" stroke="#fff" stroke-width="2"/>
              <line x1="14" y1="19" x2="14" y2="27" stroke="#e03131" stroke-width="2.5"/>
            </svg>
          </div>
        </div>
      </div>
      <div class="pmw-zoom-controls">
        <button type="button" class="pmw-zoom-in">+</button>
        <span class="pmw-zoom-pct">100%</span>
        <button type="button" class="pmw-zoom-out">−</button>
        <button type="button" class="pmw-zoom-reset">Zoom to fit</button>
      </div>
    </div>
    <script>
      PinMapWidget.attachViewer(document.getElementById(<?= json_encode($id) ?>), {
        baseUrl: <?= json_encode(PIN_WIDGET_BASE_URL) ?>,
        floor: <?= json_encode($floor) ?>,
        x: <?= json_encode($x) ?>,
        y: <?= json_encode($y) ?>
      });
    </script>
  <?php else: ?>
    <p class="pmw-floor-label pmw-floor-unknown">
      Floor "<?= htmlspecialchars($floor) ?>" is no longer configured — its map can't be displayed.
    </p>
  <?php endif; ?>
</div>
