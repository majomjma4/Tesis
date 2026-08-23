-- Evita publicaciones directas duplicadas ante reintentos concurrentes.
CREATE TABLE IF NOT EXISTS repository_direct_publish_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    request_token CHAR(64) NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    response_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_repository_direct_publish_request (user_id, request_token),
    KEY idx_repository_direct_publish_project (project_id),
    CONSTRAINT fk_repository_direct_publish_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_repository_direct_publish_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
