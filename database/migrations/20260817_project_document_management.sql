-- Contrato documental administrativo para expedientes de proyectos.
-- Idempotente: cada ampliacion se aplica solo cuando no existe.
SET @schema_name := DATABASE();

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@schema_name AND table_name='project_files' AND column_name='sort_order'),
  'SELECT 1',
  'ALTER TABLE project_files ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER checksum_sha256'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@schema_name AND table_name='project_files' AND column_name='deleted_by'),
  'SELECT 1',
  'ALTER TABLE project_files ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at, ADD CONSTRAINT fk_project_file_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id)'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@schema_name AND table_name='project_files' AND column_name='purged_at'),
  'SELECT 1',
  'ALTER TABLE project_files ADD COLUMN purged_at DATETIME NULL AFTER deleted_by'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@schema_name AND table_name='project_files' AND column_name='purged_by'),
  'SELECT 1',
  'ALTER TABLE project_files ADD COLUMN purged_by BIGINT UNSIGNED NULL AFTER purged_at, ADD CONSTRAINT fk_project_file_purged_by FOREIGN KEY (purged_by) REFERENCES users(id)'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@schema_name AND table_name='project_files' AND index_name='idx_project_file_restore_window'),
  'SELECT 1',
  'ALTER TABLE project_files ADD INDEX idx_project_file_restore_window(project_id,deleted_at,purged_at)'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS project_file_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  file_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_name VARCHAR(190) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  extension VARCHAR(12) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  checksum_sha256 CHAR(64) NOT NULL,
  replaced_by BIGINT UNSIGNED NULL,
  replaced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  replacement_reason VARCHAR(500) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_project_file_version_number(file_id,version_number),
  KEY idx_project_file_version_project(project_id,replaced_at),
  KEY fk_project_file_version_actor(replaced_by),
  CONSTRAINT fk_project_file_version_file FOREIGN KEY(file_id) REFERENCES project_files(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_file_version_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_file_version_actor FOREIGN KEY(replaced_by) REFERENCES users(id),
  CONSTRAINT chk_project_file_version_positive CHECK(version_number>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE project_files pf
JOIN (
  SELECT id, ROW_NUMBER() OVER(PARTITION BY project_id ORDER BY created_at,id) AS position
  FROM project_files
) ordered ON ordered.id=pf.id
SET pf.sort_order=ordered.position
WHERE pf.sort_order=0;
