ALTER TABLE project_observations
    ADD COLUMN IF NOT EXISTS file_checksum_sha256 CHAR(64) NULL AFTER file_id,
    ADD COLUMN IF NOT EXISTS project_file_version_id BIGINT UNSIGNED NULL AFTER file_checksum_sha256,
    ADD INDEX IF NOT EXISTS idx_project_observations_file_revision (file_id, file_checksum_sha256),
    ADD INDEX IF NOT EXISTS idx_project_observations_file_version (project_file_version_id);

ALTER TABLE project_observations
    ADD CONSTRAINT fk_project_observations_file_version
    FOREIGN KEY (project_file_version_id) REFERENCES project_file_versions(id)
    ON DELETE SET NULL;
