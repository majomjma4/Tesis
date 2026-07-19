CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO system_settings(setting_key,setting_value) VALUES
('institution_name','Instituto Superior Tecnológico El Libertador'),
('file_max_mb','20'),('file_total_max_mb','50'),('file_extensions','["pdf","docx","zip"]')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
