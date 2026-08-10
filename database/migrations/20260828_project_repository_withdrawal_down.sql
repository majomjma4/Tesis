-- Ejecutar únicamente si no existen proyectos retirados.
-- SELECT COUNT(*) FROM projects WHERE withdrawn_at IS NOT NULL;
ALTER TABLE projects
    DROP FOREIGN KEY fk_projects_withdrawn_by,
    DROP KEY fk_projects_withdrawn_by,
    DROP KEY idx_projects_repository_withdrawn,
    DROP COLUMN withdrawn_by,
    DROP COLUMN withdrawn_at;
