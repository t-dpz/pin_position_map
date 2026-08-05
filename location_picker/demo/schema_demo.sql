-- Stand-in for the host app's own issue table. Not part of the reusable
-- component — location_picker only ever needs an integer issue id.
CREATE TABLE IF NOT EXISTS pin_demo_issues (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    status      ENUM('open','in_progress','resolved') DEFAULT 'open',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
