<?php
/**
 * Serves a zoomed PNG crop of the render centered on a pin.
 * Query: ?issue_id=N   or   ?plan_id=N&x=0.3&y=0.6
 *
 * Optional: &size=400  (crop square side in pixels, default 400)
 */
require __DIR__ . '/db.php';

$cropSize = max(100, min(800, (int)($_GET['size'] ?? 400)));

if (isset($_GET['issue_id'])) {
    $stmt = db()->prepare(
        'SELECT i.pin_x, i.pin_y, p.render_path, p.render_width, p.render_height
         FROM ppm_issues i JOIN ppm_plans p ON p.id = i.plan_id
         WHERE i.id = ?'
    );
    $stmt->execute([(int)$_GET['issue_id']]);
    $row = $stmt->fetch();
} else {
    $stmt = db()->prepare('SELECT render_path, render_width, render_height FROM ppm_plans WHERE id = ?');
    $stmt->execute([(int)($_GET['plan_id'] ?? 0)]);
    $plan = $stmt->fetch();
    if ($plan) {
        $row = array_merge($plan, [
            'pin_x' => (float)($_GET['x'] ?? 0.5),
            'pin_y' => (float)($_GET['y'] ?? 0.5),
        ]);
    }
}

if (empty($row)) { http_response_code(404); exit; }

$renderFile = __DIR__ . '/' . $row['render_path'];
if (!is_file($renderFile)) { http_response_code(404); exit; }

$src = imagecreatefrompng($renderFile);
if (!$src) { http_response_code(500); exit; }

$imgW = imagesx($src);
$imgH = imagesy($src);

// Pin in source pixels
$px = (int)round((float)$row['pin_x'] * $imgW);
$py = (int)round((float)$row['pin_y'] * $imgH);

// Crop box — center on pin, clamp to image bounds
$half  = (int)($cropSize / 2);
$srcX  = max(0, min($imgW - $cropSize, $px - $half));
$srcY  = max(0, min($imgH - $cropSize, $py - $half));

// When the image is smaller than $cropSize in either dimension, use the full dimension
if ($imgW < $cropSize) { $cropW = $imgW; $srcX = 0; } else { $cropW = $cropSize; }
if ($imgH < $cropSize) { $cropH = $imgH; $srcY = 0; } else { $cropH = $cropSize; }

$dst = imagecreatetruecolor($cropW, $cropH);
imagecopy($dst, $src, 0, 0, $srcX, $srcY, $cropW, $cropH);
imagedestroy($src);

// Pin position within the crop
$markerX = $px - $srcX;
$markerY = $py - $srcY;

// Draw crosshair — thick white shadow then red line for contrast
$white = imagecolorallocatealpha($dst, 255, 255, 255, 0);
$red   = imagecolorallocate($dst, 220, 30, 30);

$r  = 14;     // circle radius
$hl = 20;     // half-line length

// White backing
imagesetthickness($dst, 3);
imagearc($dst, $markerX, $markerY, $r * 2 + 4, $r * 2 + 4, 0, 360, $white);
imageline($dst, $markerX - $hl - 2, $markerY, $markerX + $hl + 2, $markerY, $white);
imageline($dst, $markerX, $markerY - $hl - 2, $markerX, $markerY + $hl + 2, $white);

// Red foreground
imagesetthickness($dst, 2);
imagearc($dst, $markerX, $markerY, $r * 2, $r * 2, 0, 360, $red);
imageline($dst, $markerX - $hl, $markerY, $markerX + $hl, $markerY, $red);
imageline($dst, $markerX, $markerY - $hl, $markerX, $markerY + $hl, $red);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=300');
imagepng($dst, null, 6);
imagedestroy($dst);
