CREATE TABLE IF NOT EXISTS password_reset_requests (
    reset_request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reset_request_status ENUM('pending','done') NOT NULL DEFAULT 'pending',
    reset_request_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reset_request_resolved_at TIMESTAMP NULL,
    reset_request_resolved_by INT NULL,

    reset_pending_marker TINYINT
        GENERATED ALWAYS AS (
            CASE WHEN reset_request_status = 'pending' THEN 1 ELSE NULL END
        ) STORED,

    CONSTRAINT fk_reset_request_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_reset_request_admin
        FOREIGN KEY (reset_request_resolved_by) REFERENCES users(user_id)
        ON DELETE SET NULL,

    UNIQUE KEY uniq_pending_per_user (user_id, reset_pending_marker)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;