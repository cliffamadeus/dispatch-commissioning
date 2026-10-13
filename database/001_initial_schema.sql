CREATE TABLE IF NOT EXISTS activity_logs(
    activity_log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    user_email VARCHAR(50),
    activity_log_action VARCHAR(50) NOT NULL,
    activity_log_status ENUM('success','failed') DEFAULT 'success',

    -- Client Parameters
    activity_log_ip_address VARCHAR(45),
    activity_log_user_agent VARCHAR(255),

    -- Timestamp
    activity_log_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- September 7, 2026

CREATE TABLE IF NOT EXISTS users(
    user_id INT AUTO_INCREMENT PRIMARY KEY,

    user_email VARCHAR(50) UNIQUE NOT NULL,
    user_username VARCHAR(20) UNIQUE NOT NULL,
    user_password VARCHAR(255) NOT NULL,
    user_role ENUM('admin','manager', 'user') NOT NULL DEFAULT 'user',
    
    user_created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,
    
    user_updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;

INSERT INTO users
(
    user_email,
    user_username,
    user_password,
    user_role
)
VALUES
(
    'admin@example.com',
    'admin',
    '$2y$10$HNfhClczEWBxcFuJwP53iu2Y75Tba7IEtmX8vX.1tp0dZ5EVt9CbO',
    'admin'
),
(
    'manager@example.com',
    'manager',
    '$2y$10$HNfhClczEWBxcFuJwP53iu2Y75Tba7IEtmX8vX.1tp0dZ5EVt9CbO',
    'manager'
),
(
    'user@example.com',
    'user',
    '$2y$10$HNfhClczEWBxcFuJwP53iu2Y75Tba7IEtmX8vX.1tp0dZ5EVt9CbO',
    'user'
);

-- User verification
ALTER TABLE users
ADD COLUMN user_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER user_role;

-- Mark existing users as verified so they aren't locked out
UPDATE users SET user_verified = 1;