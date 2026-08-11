ALTER TABLE project_defenses
  ADD COLUMN IF NOT EXISTS result ENUM('approved','rejected') NULL AFTER modality,
  ADD COLUMN IF NOT EXISTS result_notes VARCHAR(2000) NULL AFTER result,
  ADD COLUMN IF NOT EXISTS result_registered_by BIGINT UNSIGNED NULL AFTER result_notes,
  ADD COLUMN IF NOT EXISTS result_registered_at DATETIME NULL AFTER result_registered_by,
  ADD CONSTRAINT fk_project_defenses_result_user FOREIGN KEY (result_registered_by) REFERENCES users(id);
