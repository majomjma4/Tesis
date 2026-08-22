CREATE TABLE IF NOT EXISTS password_recovery_rate_limits (
    ip_address VARCHAR(45) NOT NULL PRIMARY KEY,
    window_started_at DATETIME NOT NULL,
    last_requested_at DATETIME NOT NULL,
    request_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
    INDEX idx_password_recovery_rate_last_requested (last_requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
