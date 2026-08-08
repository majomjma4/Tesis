-- Estado de revisión por versión documental. No modifica archivos existentes.
CREATE TABLE IF NOT EXISTS project_file_review_states (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  file_id BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  status ENUM('development','under_review','approved','corrections_requested') NOT NULL DEFAULT 'development',
  reviewed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_project_file_review_version (file_id,checksum_sha256),
  KEY idx_project_file_review_summary (project_id,status),
  KEY fk_project_file_review_actor (reviewed_by),
  CONSTRAINT fk_project_file_review_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_file_review_file FOREIGN KEY (file_id) REFERENCES project_files(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_file_review_actor FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
