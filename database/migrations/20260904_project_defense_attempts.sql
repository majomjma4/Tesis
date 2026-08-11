-- Conserva los registros existentes como intento inicial y habilita el historial.
ALTER TABLE project_defenses
  ADD COLUMN attempt_number INT UNSIGNED NULL AFTER project_id;

UPDATE project_defenses
SET attempt_number = 1
WHERE attempt_number IS NULL;

ALTER TABLE project_defenses
  MODIFY COLUMN attempt_number INT UNSIGNED NOT NULL,
  DROP INDEX uq_project_defenses_project,
  ADD UNIQUE KEY uq_project_defenses_attempt (project_id, attempt_number),
  ADD KEY idx_project_defenses_current (project_id, attempt_number);
