-- Solicitudes administrativas independientes de las observaciones académicas.
SET @schema_name := DATABASE();
SET @notification_type := (
  SELECT COLUMN_TYPE FROM information_schema.columns
  WHERE table_schema=@schema_name AND table_name='notifications' AND column_name='type'
);
SET @sql := IF(
  @notification_type IS NOT NULL AND LOCATE("'adjustment'",@notification_type)=0,
  "ALTER TABLE notifications MODIFY type ENUM('delivery','observation','status_change','review','reminder','system','tribunal','repository','comment','adjustment') NOT NULL",
  'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS project_adjustment_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  request_type VARCHAR(60) NOT NULL,
  message TEXT NOT NULL,
  related_section VARCHAR(100) NULL,
  related_field VARCHAR(100) NULL,
  file_id BIGINT UNSIGNED NULL,
  status ENUM('pending','addressed','closed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  addressed_at DATETIME NULL,
  closed_at DATETIME NULL,
  closed_by BIGINT UNSIGNED NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_adjustment_project_status_date (project_id,status,created_at,id),
  KEY idx_adjustment_requester (requested_by),
  KEY idx_adjustment_file (file_id),
  KEY idx_adjustment_closed_by (closed_by),
  CONSTRAINT fk_adjustment_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_adjustment_requester FOREIGN KEY(requested_by) REFERENCES users(id),
  CONSTRAINT fk_adjustment_file FOREIGN KEY(file_id) REFERENCES project_files(id),
  CONSTRAINT fk_adjustment_closed_by FOREIGN KEY(closed_by) REFERENCES users(id),
  CONSTRAINT chk_adjustment_lock_version CHECK(lock_version>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_adjustment_request_responses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_adjustment_response_request_date (request_id,created_at,id),
  KEY idx_adjustment_response_author (author_id),
  CONSTRAINT fk_adjustment_response_request FOREIGN KEY(request_id) REFERENCES project_adjustment_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_adjustment_response_author FOREIGN KEY(author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
