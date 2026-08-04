-- Conservación lógica y manifiestos verificables de versiones históricas.
ALTER TABLE project_file_versions
  ADD COLUMN IF NOT EXISTS physical_status ENUM('active','archived','unavailable') NOT NULL DEFAULT 'active' AFTER replacement_reason,
  ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER physical_status,
  ADD COLUMN IF NOT EXISTS archived_by BIGINT UNSIGNED NULL AFTER archived_at,
  ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL AFTER archived_by,
  ADD COLUMN IF NOT EXISTS checksum_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER verified_at,
  ADD COLUMN IF NOT EXISTS storage_tier VARCHAR(40) NOT NULL DEFAULT 'active' AFTER checksum_verified,
  ADD COLUMN IF NOT EXISTS retention_until DATE NULL AFTER storage_tier,
  ADD COLUMN IF NOT EXISTS legal_hold TINYINT(1) NOT NULL DEFAULT 0 AFTER retention_until,
  ADD COLUMN IF NOT EXISTS unavailable_reason VARCHAR(500) NULL AFTER legal_hold,
  ADD COLUMN IF NOT EXISTS archive_reason VARCHAR(500) NULL AFTER unavailable_reason,
  ADD INDEX IF NOT EXISTS idx_project_file_version_conservation(project_id,physical_status,legal_hold);

SET @archive_fk := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() AND constraint_name='fk_project_file_version_archived_by');
SET @archive_fk_sql := IF(@archive_fk=0,'ALTER TABLE project_file_versions ADD CONSTRAINT fk_project_file_version_archived_by FOREIGN KEY(archived_by) REFERENCES users(id)','DO 1');
PREPARE archive_fk_stmt FROM @archive_fk_sql; EXECUTE archive_fk_stmt; DEALLOCATE PREPARE archive_fk_stmt;

CREATE TABLE IF NOT EXISTS project_file_version_archive_manifests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  file_id BIGINT UNSIGNED NOT NULL,
  version_id BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  replaced_at DATETIME NOT NULL,
  replaced_by BIGINT UNSIGNED NULL,
  historical_document_status VARCHAR(40) NOT NULL,
  declared_summary VARCHAR(2000) NULL,
  sections_json JSON NULL,
  addressed_observations_json JSON NULL,
  storage_tier VARCHAR(40) NOT NULL,
  archived_reason VARCHAR(500) NOT NULL,
  verified_at DATETIME NOT NULL,
  checksum_verified TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_archive_manifest_version(version_id),
  KEY idx_archive_manifest_project(project_id,version_number),
  CONSTRAINT fk_archive_manifest_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_archive_manifest_file FOREIGN KEY(file_id) REFERENCES project_files(id) ON DELETE CASCADE,
  CONSTRAINT fk_archive_manifest_version FOREIGN KEY(version_id) REFERENCES project_file_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_archive_manifest_actor FOREIGN KEY(replaced_by) REFERENCES users(id),
  CONSTRAINT chk_archive_manifest_sections CHECK(sections_json IS NULL OR JSON_VALID(sections_json)),
  CONSTRAINT chk_archive_manifest_observations CHECK(addressed_observations_json IS NULL OR JSON_VALID(addressed_observations_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
