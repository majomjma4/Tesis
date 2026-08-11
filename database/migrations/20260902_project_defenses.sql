-- Información organizativa opcional; no representa una transición académica.
CREATE TABLE IF NOT EXISTS project_defenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    defense_date DATE NULL,
    defense_time TIME NULL,
    location VARCHAR(255) NULL,
    modality ENUM('presential','virtual','hybrid') NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_defenses_project (project_id),
    CONSTRAINT fk_project_defenses_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
