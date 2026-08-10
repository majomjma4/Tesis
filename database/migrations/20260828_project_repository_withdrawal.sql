-- Separa retiro institucional de estado académico y disponibilidad.
ALTER TABLE projects
    ADD COLUMN withdrawn_at DATETIME NULL AFTER is_available,
    ADD COLUMN withdrawn_by BIGINT UNSIGNED NULL AFTER withdrawn_at,
    ADD KEY idx_projects_repository_withdrawn (status, withdrawn_at, deleted_at),
    ADD KEY fk_projects_withdrawn_by (withdrawn_by),
    ADD CONSTRAINT fk_projects_withdrawn_by FOREIGN KEY (withdrawn_by) REFERENCES users(id) ON DELETE SET NULL;

-- Los proyectos existentes quedan visibles: withdrawn_at inicia en NULL.
