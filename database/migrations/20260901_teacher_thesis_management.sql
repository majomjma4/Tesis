-- Cargo académico individual; no concede acceso administrativo general.
ALTER TABLE teacher_profiles
  ADD COLUMN IF NOT EXISTS can_manage_thesis TINYINT(1) NOT NULL DEFAULT 0 AFTER can_tutor,
  ADD INDEX IF NOT EXISTS idx_teacher_profiles_manage_thesis (can_manage_thesis);
