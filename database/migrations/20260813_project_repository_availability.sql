-- Permite suspender temporalmente la consulta de un proyecto sin retirar su publicación.

ALTER TABLE projects
  ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1 AFTER published_at,
  ADD INDEX idx_project_repository_visibility (status,is_available,deleted_at);
