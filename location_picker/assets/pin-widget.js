/**
 * PinMapWidget — renders a floor-plan PDF onto a canvas with zoom/pan, and
 * either lets the user click to drop a pin (picker) or displays a fixed pin
 * read-only (viewer). Shared by partials/picker.php and partials/viewer.php.
 */
window.PinMapWidget = (function () {
  'use strict';

  const PDFJS_WORKER_SRC = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  const RENDER_SCALE = 2;   // resolution the PDF page is rasterised at
  const ZOOM_MIN = 1, ZOOM_MAX = 8, ZOOM_STEP = 1.3, DRAG_THRESHOLD = 4;
  const VIEWER_INITIAL_ZOOM = 3; // start zoomed in on the pin; user can zoom out for context

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
      apply(); // restore cursor
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

  function attachPicker(root, { baseUrl }) {
    const select = root.querySelector('.pmw-floor-select');
    const area   = root.querySelector('.pmw-map-area');
    const canvas = root.querySelector('.pmw-canvas');
    const xInput = root.querySelector('.pmw-x-input');
    const yInput = root.querySelector('.pmw-y-input');

    const initialX = xInput.dataset.initial !== undefined ? parseFloat(xInput.dataset.initial) : null;
    const initialY = yInput.dataset.initial !== undefined ? parseFloat(yInput.dataset.initial) : null;

    let controls = null;

    async function loadFloor(floor, restore) {
      area.style.display = 'block';
      controls = null;
      xInput.value = '';
      yInput.value = '';

      await renderPdf(canvas, baseUrl + '/map.php?floor=' + encodeURIComponent(floor));

      controls = initZoomPan(root, {
        interactive: true,
        onPick(x, y) {
          xInput.value = x.toFixed(8);
          yInput.value = y.toFixed(8);
          controls.setPin(x, y);
        },
      });

      if (restore && initialX !== null && initialY !== null) {
        controls.setPin(initialX, initialY);
        xInput.value = initialX.toFixed(8);
        yInput.value = initialY.toFixed(8);
      }
    }

    select.addEventListener('change', () => {
      if (!select.value) {
        area.style.display = 'none';
        xInput.value = '';
        yInput.value = '';
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
