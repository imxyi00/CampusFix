-- Run this once on an existing CampusFix database to enable request tracking.
-- Existing stage timestamps use updated_at because older status-change times
-- were not stored separately.
CREATE TABLE IF NOT EXISTS request_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    stage ENUM('reviewed','assigned','repair','resolved') NOT NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_request_progress_stage (request_id, stage),
    CONSTRAINT fk_progress_request
        FOREIGN KEY (request_id) REFERENCES requests(id)
        ON DELETE CASCADE
);

INSERT IGNORE INTO request_progress (request_id, stage, completed_at)
SELECT id, 'reviewed', updated_at FROM requests
WHERE status IN ('Assigned', 'In Progress', 'Completed')
UNION ALL
SELECT id, 'assigned', updated_at FROM requests
WHERE status IN ('Assigned', 'In Progress', 'Completed')
UNION ALL
SELECT id, 'repair', updated_at FROM requests
WHERE status IN ('In Progress', 'Completed')
UNION ALL
SELECT id, 'resolved', updated_at FROM requests
WHERE status = 'Completed';
