<div class="pmw" id="<?= htmlspecialchars($id) ?>">
  <?php if ($info): ?>
    <p class="pmw-floor-label">Floor: <strong><?= htmlspecialchars($info['label']) ?></strong></p>

    <div class="pmw-map-area">
      <div class="pmw-wrapper">
        <div class="pmw-canvas-wrap">
          <canvas class="pmw-canvas"></canvas>
          <div class="pmw-pin" hidden>
            <svg viewBox="0 0 24 32" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="pmw-pin-grad-<?= htmlspecialchars($id) ?>" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#ff8787"/>
                  <stop offset="100%" stop-color="#e03131"/>
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
