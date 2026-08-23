-- Separa el origen de publicación de la state machine académica.
ALTER TABLE projects
    ADD COLUMN publication_origin VARCHAR(40) NOT NULL DEFAULT 'workflow' AFTER status,
    ADD COLUMN repository_added_by BIGINT UNSIGNED NULL AFTER published_at,
    ADD COLUMN repository_added_at DATETIME NULL AFTER repository_added_by,
    ADD KEY idx_projects_repository_origin (publication_origin, status, deleted_at, withdrawn_at),
    ADD KEY idx_projects_repository_added_by (repository_added_by),
    ADD CONSTRAINT fk_projects_repository_added_by FOREIGN KEY (repository_added_by)
        REFERENCES users(id) ON DELETE SET NULL;

-- Los proyectos existentes conservan el origen normal del workflow.
