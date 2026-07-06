CREATE TABLE IF NOT EXISTS ppm_plans (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL,
    original_path VARCHAR(512) NOT NULL,
    original_mime VARCHAR(100) NOT NULL,
    render_path  VARCHAR(512) NOT NULL,
    render_width  SMALLINT UNSIGNED NOT NULL,
    render_height SMALLINT UNSIGNED NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ppm_issues (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    pin_x       DECIMAL(10,8) NOT NULL,
    pin_y       DECIMAL(10,8) NOT NULL,
    status      ENUM('open','in_progress','resolved') DEFAULT 'open',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES ppm_plans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ppm_calibration (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id    INT UNSIGNED NOT NULL,
    label      VARCHAR(100) NOT NULL DEFAULT '',
    plan_x     DECIMAL(10,8) NOT NULL,
    plan_y     DECIMAL(10,8) NOT NULL,
    lat        DECIMAL(12,8) DEFAULT NULL,
    lng        DECIMAL(12,8) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES ppm_plans(id) ON DELETE CASCADE
);
