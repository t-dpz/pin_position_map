window.PinMapWidget = (function () {
  'use strict';

  const PDFJS_WORKER_SRC = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  const RENDER_SCALE = 2;
  const ZOOM_MIN = 1, ZOOM_MAX = 8, ZOOM_STEP = 1.3, DRAG_THRESHOLD = 4;
  const VIEWER_INITIAL_ZOOM = 3;

  let workerReady = false;
  function ensureWorker() {
    if (!workerReady && window.pdfjsLib) {
      pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_SRC;
      workerReady = true;
    }
  }

  async function renderPdf(canvas, pdfUrl) {
    ensureWorker();
    const pdf  = await pdfjsLib.getDocument(pdfUrl).promise;
    const page = await pdf.getPage(1);
    const vp   = page.getViewport({ scale: RENDER_SCALE });
    canvas.width  = vp.width;
    canvas.height = vp.height;
    await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
  }

  function initZoomPan(root, { interactive, onPick }) {
    const wrapper    = root.querySelector('.pmw-wrapper');
    const canvasWrap = root.querySelector('.pmw-canvas-wrap');
    const pin        = root.querySelector('.pmw-pin');
    const pctEl      = root.querySelector('.pmw-zoom-pct');

    let zoom = 1, panX = 0, panY = 0;
    let dragging = false, dragged = false;
    let startX = 0, startY = 0, startPanX = 0, startPanY = 0;

    function clampPan() {
      const wW = wrapper.clientWidth,  wH = wrapper.clientHeight;
      const cW = canvasWrap.offsetWidth, cH = canvasWrap.offsetHeight;
      panX = cW * zoom <= wW ? 0 : Math.min(0, Math.max(panX, wW - cW * zoom));
      panY = cH * zoom <= wH ? 0 : Math.min(0, Math.max(panY, wH - cH * zoom));
    }

    function apply() {
      canvasWrap.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoom + ')';
      if (pctEl) pctEl.textContent = Math.round(zoom * 100) + '%';
      wrapper.style.cursor = zoom > 1 ? 'grab' : (interactive ? 'crosshair' : 'default');
      pin.style.setProperty('--pin-scale', 1 / zoom);
    }

    function zoomTo(newZoom, pivotX, pivotY) {
      newZoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, newZoom));
      panX = pivotX - (pivotX - panX) * (newZoom / zoom);
      panY = pivotY - (pivotY - panY) * (newZoom / zoom);
      zoom = newZoom;
      clampPan();
      apply();
    }

    wrapper.addEventListener('wheel', (e) => {
      e.preventDefault();
      const r = wrapper.getBoundingClientRect();
      zoomTo(zoom * (e.deltaY > 0 ? 1 / ZOOM_STEP : ZOOM_STEP), e.clientX - r.left, e.clientY - r.top);
    }, { passive: false });

    root.querySelectorAll('.pmw-zoom-in').forEach((b) => b.addEventListener('click', () =>
      zoomTo(zoom * ZOOM_STEP, wrapper.clientWidth / 2, wrapper.clientHeight / 2)));
    root.querySelectorAll('.pmw-zoom-out').forEach((b) => b.addEventListener('click', () =>
      zoomTo(zoom / ZOOM_STEP, wrapper.clientWidth / 2, wrapper.clientHeight / 2)));
    root.querySelectorAll('.pmw-zoom-reset').forEach((b) => b.addEventListener('click', () => {
      zoom = 1; panX = 0; panY = 0; apply();
    }));

    wrapper.addEventListener('mousedown', (e) => {
      dragging = true; dragged = false;
      startX = e.clientX; startY = e.clientY;
      startPanX = panX; startPanY = panY;
      e.preventDefault();
    });
    window.addEventListener('mousemove', (e) => {
      if (!dragging) return;
      const dx = e.clientX - startX, dy = e.clientY - startY;
      if (!dragged && Math.hypot(dx, dy) > DRAG_THRESHOLD) dragged = true;
      if (dragged && zoom > 1) {
        panX = startPanX + dx;
        panY = startPanY + dy;
        clampPan();
        apply();
      }
    });
    window.addEventListener('mouseup', () => {
      if (!dragging) return;
      dragging = false;
      apply();
    });

    if (interactive) {
      wrapper.addEventListener('click', (e) => {
        if (dragged) return;
        const r = wrapper.getBoundingClientRect();
        const x = (e.clientX - r.left - panX) / zoom / canvasWrap.offsetWidth;
        const y = (e.clientY - r.top  - panY) / zoom / canvasWrap.offsetHeight;
        if (x < 0 || x > 1 || y < 0 || y > 1) return;
        onPick(x, y);
      });
    }

    apply();

    return {
      setPin(x, y) {
        pin.style.left = (x * 100) + '%';
        pin.style.top  = (y * 100) + '%';
        pin.hidden = false;
      },
      clearPin() {
        pin.hidden = true;
      },
      centerOn(x, y, targetZoom) {
        zoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, targetZoom));
        const wW = wrapper.clientWidth, wH = wrapper.clientHeight;
        const cW = canvasWrap.offsetWidth, cH = canvasWrap.offsetHeight;
        panX = wW / 2 - x * cW * zoom;
        panY = wH / 2 - y * cH * zoom;
        clampPan();
        apply();
      },
    };
  }

  function attachPicker(root, { baseUrl, issueId, floor, x, y }) {
    const select = root.querySelector('.pmw-floor-select');
    const area   = root.querySelector('.pmw-map-area');
    const canvas = root.querySelector('.pmw-canvas');
    const status = root.querySelector('.pmw-status');

    let controls = null;

    function setStatus(text, cls) {
      if (!status) return;
      status.textContent = text;
      status.className = 'pmw-status' + (cls ? ' ' + cls : '');
    }

    async function save(floorKey, px, py) {
      setStatus('Saving…');
      try {
        const res = await fetch(baseUrl + '/save_location.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            issue_id: issueId,
            floor: floorKey,
            x: px.toFixed(8),
            y: py.toFixed(8),
          }),
        });
        if (!res.ok) throw new Error('save failed');
        setStatus('Location saved.', 'pmw-status-ok');
      } catch (e) {
        setStatus('Could not save the location — try again.', 'pmw-status-error');
      }
    }

    async function loadFloor(floorKey, restore) {
      area.style.display = 'block';
      controls = null;
      setStatus('');

      await renderPdf(canvas, baseUrl + '/map.php?floor=' + encodeURIComponent(floorKey));

      controls = initZoomPan(root, {
        interactive: true,
        onPick(px, py) {
          controls.setPin(px, py);
          save(floorKey, px, py);
        },
      });

      if (restore && x !== null && y !== null) {
        controls.setPin(x, y);
      }
    }

    select.addEventListener('change', () => {
      if (!select.value) {
        area.style.display = 'none';
        return;
      }
      loadFloor(select.value, false);
    });

    if (select.value) loadFloor(select.value, true);
  }

  function attachViewer(root, { baseUrl, floor, x, y }) {
    const canvas = root.querySelector('.pmw-canvas');
    renderPdf(canvas, baseUrl + '/map.php?floor=' + encodeURIComponent(floor)).then(() => {
      const controls = initZoomPan(root, { interactive: false, onPick() {} });
      controls.setPin(x, y);
      controls.centerOn(x, y, VIEWER_INITIAL_ZOOM);
    });
  }

  return { attachPicker, attachViewer };
})();
