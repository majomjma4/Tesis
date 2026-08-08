-- Conserva la evidencia histórica cuando un archivo retirado se elimina físicamente.

ALTER TABLE support_material_files
  ADD COLUMN purged_at DATETIME NULL AFTER deleted_by,
  ADD COLUMN purged_by BIGINT UNSIGNED NULL AFTER purged_at,
  ADD CONSTRAINT fk_support_file_purged_by
    FOREIGN KEY (purged_by) REFERENCES users(id),
  ADD INDEX idx_support_file_restore_window (material_id, deleted_at, purged_at);
