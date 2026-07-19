-- Adaptación relacional previa a activar MariaDB.
-- Ejecutar después de 20260718_create_academic_projects.sql.

ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(80) NULL AFTER email;
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_username ON users (username);
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER status;

CREATE TABLE IF NOT EXISTS careers (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_periods (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    status ENUM('planned','active','closed') NOT NULL DEFAULT 'planned',
    CONSTRAINT chk_period_dates CHECK (ends_on >= starts_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    institutional_code VARCHAR(50) NOT NULL UNIQUE,
    career_id SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT fk_student_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_profile_career FOREIGN KEY (career_id) REFERENCES careers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    institutional_code VARCHAR(50) NOT NULL UNIQUE,
    academic_title VARCHAR(120) NULL,
    can_tutor TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_teacher_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    academic_period_id SMALLINT UNSIGNED NOT NULL,
    career_id SMALLINT UNSIGNED NOT NULL,
    semester SMALLINT UNSIGNED NOT NULL,
    status ENUM('active','withdrawn','completed') NOT NULL DEFAULT 'active',
    UNIQUE KEY uq_student_enrollment (student_id, academic_period_id),
    INDEX idx_enrollment_lookup (academic_period_id, career_id, semester, status),
    CONSTRAINT chk_enrollment_semester CHECK (semester BETWEEN 1 AND 10),
    CONSTRAINT fk_enrollment_student FOREIGN KEY (student_id) REFERENCES users(id),
    CONSTRAINT fk_enrollment_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id),
    CONSTRAINT fk_enrollment_career FOREIGN KEY (career_id) REFERENCES careers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS research_lines (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    career_id SMALLINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_research_line_career FOREIGN KEY (career_id) REFERENCES careers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    career_id SMALLINT UNSIGNED NOT NULL,
    academic_period_id SMALLINT UNSIGNED NOT NULL,
    semester SMALLINT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(180) NOT NULL,
    responsible_teacher_id BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_subject_period (academic_period_id, career_id, code),
    CONSTRAINT fk_subject_career FOREIGN KEY (career_id) REFERENCES careers(id),
    CONSTRAINT fk_subject_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id),
    CONSTRAINT fk_subject_teacher FOREIGN KEY (responsible_teacher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Los códigos quedan alineados con ProjectDraftService y el formulario.
INSERT IGNORE INTO roles (code, name) VALUES ('student','Estudiante'),('teacher','Docente'),('administrator','Administrador');
DELETE FROM roles WHERE code NOT IN ('student','teacher','administrator');
UPDATE project_types SET code = 'pis', name = 'Proyecto integrador de saberes' WHERE code = 'integrator';
UPDATE project_types SET code = 'practice', name = 'Prácticas preprofesionales' WHERE code = 'internship';

ALTER TABLE projects ADD COLUMN IF NOT EXISTS code VARCHAR(40) NULL AFTER id;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS career_id SMALLINT UNSIGNED NULL AFTER project_type_id;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS academic_period_id SMALLINT UNSIGNED NULL AFTER career_id;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS modality ENUM('individual','group') NULL AFTER summary;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS research_line_id SMALLINT UNSIGNED NULL AFTER modality;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS academic_subject_id BIGINT UNSIGNED NULL AFTER research_line_id;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS proposed_tutor_id BIGINT UNSIGNED NULL AFTER academic_subject_id;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS tutor_id BIGINT UNSIGNED NULL AFTER proposed_tutor_id;
ALTER TABLE projects MODIFY career VARCHAR(180) NULL, MODIFY academic_period VARCHAR(30) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_projects_code ON projects (code);
CREATE INDEX IF NOT EXISTS idx_projects_period_id ON projects (academic_period_id);
ALTER TABLE projects ADD CONSTRAINT fk_projects_career_id FOREIGN KEY (career_id) REFERENCES careers(id);
ALTER TABLE projects ADD CONSTRAINT fk_projects_period_id FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id);
ALTER TABLE projects ADD CONSTRAINT fk_projects_research_line_id FOREIGN KEY (research_line_id) REFERENCES research_lines(id);
ALTER TABLE projects ADD CONSTRAINT fk_projects_subject_id FOREIGN KEY (academic_subject_id) REFERENCES academic_subjects(id);
ALTER TABLE projects ADD CONSTRAINT fk_projects_proposed_tutor_id FOREIGN KEY (proposed_tutor_id) REFERENCES users(id);
ALTER TABLE projects ADD CONSTRAINT fk_projects_tutor_id FOREIGN KEY (tutor_id) REFERENCES users(id);

CREATE TABLE IF NOT EXISTS project_code_sequences (
    project_type_id SMALLINT UNSIGNED NOT NULL,
    code_year SMALLINT UNSIGNED NOT NULL,
    next_number INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (project_type_id, code_year),
    CONSTRAINT fk_code_sequence_type FOREIGN KEY (project_type_id) REFERENCES project_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE project_events DROP FOREIGN KEY fk_events_project;
ALTER TABLE project_events MODIFY project_id BIGINT UNSIGNED NULL;
ALTER TABLE project_events ADD CONSTRAINT fk_events_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS project_favorites (
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, project_id),
    CONSTRAINT fk_favorite_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorite_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_downloads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    INDEX idx_project_downloads (project_id, downloaded_at),
    CONSTRAINT fk_download_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_download_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE notifications ADD CONSTRAINT fk_notifications_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE;

-- Las columnas de texto heredadas se mantienen temporalmente para migrar fixtures.
-- Después de poblar career_id y academic_period_id se volverán NOT NULL y se retirarán
-- career/academic_period en una migración posterior, evitando una conversión destructiva.

INSERT IGNORE INTO careers (code, name) VALUES
('TDS', 'Tecnología Superior en Desarrollo de Software');

INSERT IGNORE INTO academic_periods (code, name, starts_on, ends_on, status) VALUES
('2026-I', 'Periodo académico 2026-I', '2026-01-01', '2026-06-30', 'active'),
('2026-II', 'Periodo académico 2026-II', '2026-07-01', '2026-12-31', 'planned'),
('2027-I', 'Periodo académico 2027-I', '2027-01-01', '2027-06-30', 'planned');
