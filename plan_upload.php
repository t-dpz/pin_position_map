<?php
require __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') $error = 'Plan name is required.';

    $file = $_FILES['original'] ?? null;
    if (!$error && (!$file || $file['error'] !== UPLOAD_ERR_OK)) {
        $error = 'Please select a file to upload.';
    }

    if (!$error) {
        $mime = mime_content_type($file['tmp_name']);
        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/bmp', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            $error = 'Only PNG, JPEG, WebP, BMP, or PDF files are accepted.';
        }
    }

    // Validate the client-supplied render PNG (always required)
    $renderB64 = $_POST['render_png'] ?? '';
    $renderBytes = base64_decode($renderB64, true);
    if (!$error && ($renderBytes === false || strlen($renderBytes) < 8)) {
        $error = 'Render image missing — please re-select the file.';
    }
    if (!$error) {
        $info = @getimagesizefromstring($renderBytes);
        if (!$info || $info['mime'] !== 'image/png') {
            $error = 'Render image is not a valid PNG.';
        }
    }

    if (!$error) {
        $slug = bin2hex(random_bytes(8));
        $ext  = $mime === 'application/pdf' ? 'pdf' : pathinfo($file['name'], PATHINFO_EXTENSION);

        $origPath   = 'uploads/originals/' . $slug . '.' . $ext;
        $renderPath = 'uploads/renders/'   . $slug . '.png';

        move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $origPath);
        file_put_contents(__DIR__ . '/' . $renderPath, $renderBytes);

        db()->prepare(
            'INSERT INTO ppm_plans (name, original_path, original_mime, render_path, render_width, render_height)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$name, $origPath, $mime, $renderPath, $info[0], $info[1]]);

        $id = db()->lastInsertId();
        header('Location: plan_view.php?id=' . $id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Upload plan — Pin Position Map</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <span class="brand">Pin Position Map</span>
  <a href="index.php">Plans</a>
  <a href="manager.php">Manager view</a>
</header>
<div class="container">
  <h1>Upload floor plan</h1>
  <div class="card">
    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form id="upload-form" method="post" enctype="multipart/form-data" novalidate>
      <div class="field">
        <label for="name">Plan name</label>
        <input type="text" id="name" name="name" required
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="original">Floor plan file <small>(PNG, JPEG, WebP, BMP, or PDF)</small></label>
        <input type="file" id="original" name="original" accept=".png,.jpg,.jpeg,.webp,.bmp,.pdf" required>
      </div>

      <!-- Hidden field: render PNG sent as base64, populated by JS before submit -->
      <input type="hidden" id="render_png" name="render_png">

      <div id="preview-wrap">
        <p style="margin-bottom:.4rem;font-size:.82rem;font-weight:600;color:#555">Preview (first page for PDFs)</p>
        <canvas id="preview-canvas"></canvas>
        <p id="preview-dims" style="font-size:.78rem;color:#888;margin-top:.3rem"></p>
      </div>

      <div style="margin-top:1.25rem">
        <button type="submit" class="btn btn-primary" id="submit-btn" disabled>Upload plan</button>
        <a href="index.php" class="btn btn-ghost" style="margin-left:.5rem">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc =
  'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const input      = document.getElementById('original');
const canvas     = document.getElementById('preview-canvas');
const wrap       = document.getElementById('preview-wrap');
const dimsEl     = document.getElementById('preview-dims');
const renderField = document.getElementById('render_png');
const submitBtn  = document.getElementById('submit-btn');

input.addEventListener('change', async () => {
  const file = input.files[0];
  if (!file) return;

  wrap.style.display = 'block';
  renderField.value  = '';
  submitBtn.disabled = true;

  const ctx = canvas.getContext('2d');

  if (file.type === 'application/pdf') {
    const arrayBuf = await file.arrayBuffer();
    const pdf  = await pdfjsLib.getDocument({ data: arrayBuf }).promise;
    const page = await pdf.getPage(1);

    // Render at 2× viewport scale so the stored PNG is sharper
    const vp = page.getViewport({ scale: 2.0 });
    canvas.width  = vp.width;
    canvas.height = vp.height;
    await page.render({ canvasContext: ctx, viewport: vp }).promise;
  } else {
    // Raster image — draw through an <img> so GD-acceptable PNG lands in the canvas
    await new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => {
        canvas.width  = img.naturalWidth;
        canvas.height = img.naturalHeight;
        ctx.drawImage(img, 0, 0);
        URL.revokeObjectURL(img.src);
        resolve();
      };
      img.onerror = reject;
      img.src = URL.createObjectURL(file);
    });
  }

  dimsEl.textContent = canvas.width + ' × ' + canvas.height + ' px';

  // Capture canvas as PNG base64 (strip the data-URL prefix)
  const dataUrl = canvas.toDataURL('image/png');
  renderField.value = dataUrl.split(',')[1];
  submitBtn.disabled = false;
});
</script>
</body>
</html>
