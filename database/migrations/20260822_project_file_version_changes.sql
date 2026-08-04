-- Resumen estructurado y persistente de reemplazos documentales.
CREATE TABLE IF NOT EXISTS project_file_version_changes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  file_id BIGINT UNSIGNED NOT NULL,
  previous_version_id BIGINT UNSIGNED NULL,
  previous_checksum CHAR(64) NOT NULL,
  new_checksum CHAR(64) NOT NULL,
  previous_version_number INT UNSIGNED NOT NULL,
  new_version_number INT UNSIGNED NOT NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason VARCHAR(500) NOT NULL,
  declared_summary VARCHAR(2000) NOT NULL,
  sections_json JSON NULL,
  previous_document_status ENUM('development','under_review','approved','corrections_requested') NOT NULL,
  new_document_status ENUM('development','under_review','approved','corrections_requested') NOT NULL DEFAULT 'development',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_project_file_version_change_new(file_id,new_checksum),
  UNIQUE KEY uq_project_file_version_change_number(file_id,new_version_number),
  KEY idx_file_version_change_project_date(project_id,changed_at,id),
  KEY idx_file_version_change_previous(previous_version_id),
  KEY idx_file_version_change_actor(changed_by),
  CONSTRAINT fk_file_version_change_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_file_version_change_file FOREIGN KEY(file_id) REFERENCES project_files(id) ON DELETE CASCADE,
  CONSTRAINT fk_file_version_change_previous FOREIGN KEY(previous_version_id) REFERENCES project_file_versions(id) ON DELETE SET NULL,
  CONSTRAINT fk_file_version_change_actor FOREIGN KEY(changed_by) REFERENCES users(id),
  CONSTRAINT chk_file_version_change_checksums CHECK(previous_checksum<>new_checksum),
  CONSTRAINT chk_file_version_change_numbers CHECK(new_version_number=previous_version_number+1),
  CONSTRAINT chk_file_version_change_sections CHECK(sections_json IS NULL OR JSON_VALID(sections_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_file_version_addressed_observations (
  change_id BIGINT UNSIGNED NOT NULL,
  observation_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(change_id,observation_id),
  KEY idx_version_addressed_observation(observation_id,change_id),
  CONSTRAINT fk_version_addressed_change FOREIGN KEY(change_id) REFERENCES project_file_version_changes(id) ON DELETE CASCADE,
  CONSTRAINT fk_version_addressed_observation FOREIGN KEY(observation_id) REFERENCES project_observations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
