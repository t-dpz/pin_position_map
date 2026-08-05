<?php
require_once __DIR__ . '/db.php';

function pin_floor_config(): array {
    static $floors = null;
    if ($floors === null) {
        $floors = require __DIR__ . '/config.php';
    }
    return $floors;
}

function pin_floors(): array {
    return array_filter(pin_floor_config(), fn($f) => !empty($f['enabled']));
}

function pin_floor_info(string $floor): ?array {
    return pin_floor_config()[$floor] ?? null;
}

function pin_save_location(int $issueId, string $floor, float $x, float $y): void {
    pin_db()->prepare(
        'INSERT INTO pin_locations (issue_id, floor, pin_x, pin_y) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE floor = VALUES(floor), pin_x = VALUES(pin_x), pin_y = VALUES(pin_y)'
    )->execute([$issueId, $floor, $x, $y]);
}

function pin_get_location(int $issueId): ?array {
    $stmt = pin_db()->prepare('SELECT * FROM pin_locations WHERE issue_id = ?');
    $stmt->execute([$issueId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pin_emit_assets_once(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $base = PIN_WIDGET_BASE_URL;
    echo '<link rel="stylesheet" href="' . htmlspecialchars($base) . "/assets/pin-widget.css\">\n";
    echo "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js\"></script>\n";
    echo '<script src="' . htmlspecialchars($base) . "/assets/pin-widget.js\"></script>\n";
}

function pin_render_picker(array $opts = []): void {
    $id     = $opts['id'] ?? 'pin-picker';
    $prefix = $opts['field_prefix'] ?? 'location';
    $floor  = (string)($opts['floor'] ?? '');
    $x      = (string)($opts['x'] ?? '');
    $y      = (string)($opts['y'] ?? '');
    $floors = pin_floors();

    pin_emit_assets_once();
    require __DIR__ . '/partials/picker.php';
}

function pin_render_viewer(array $opts): void {
    $id    = $opts['id'] ?? 'pin-viewer';
    $floor = (string)$opts['floor'];
    $x     = (float)$opts['x'];
    $y     = (float)$opts['y'];
    $info  = pin_floor_info($floor);

    pin_emit_assets_once();
    require __DIR__ . '/partials/viewer.php';
}
