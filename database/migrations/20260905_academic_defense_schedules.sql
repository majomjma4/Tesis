-- Programación global y opcional de defensas por período académico.
CREATE TABLE IF NOT EXISTS academic_defense_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_period_id SMALLINT UNSIGNED NOT NULL,
    defense_date DATE NULL,
    defense_time TIME NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_academic_defense_schedules_period (academic_period_id),
    KEY idx_academic_defense_schedules_updated_by (updated_by),
    CONSTRAINT fk_academic_defense_schedules_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_academic_defense_schedules_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
