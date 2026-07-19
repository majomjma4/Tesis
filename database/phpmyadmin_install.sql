-- Instalación limpia para phpMyAdmin / MariaDB
-- Plataforma de Gestión Documental Académica
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS tesis
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE tesis;

CREATE TABLE roles (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  username VARCHAR(80) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  password_warning_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  temporary_password_expires_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  full_name VARCHAR(180) NOT NULL,
  status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE careers (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_periods (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  status ENUM('planned','active','closed') NOT NULL DEFAULT 'planned',
  CONSTRAINT chk_period_dates CHECK (ends_on >= starts_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  institutional_code VARCHAR(50) NOT NULL UNIQUE,
  career_id SMALLINT UNSIGNED NOT NULL,
  CONSTRAINT fk_student_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_student_profile_career FOREIGN KEY (career_id) REFERENCES careers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teacher_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  institutional_code VARCHAR(50) NOT NULL UNIQUE,
  academic_title VARCHAR(120) NULL,
  can_tutor TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_teacher_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_enrollments (
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

CREATE TABLE project_types (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(140) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_lines (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  career_id SMALLINT UNSIGNED NULL,
  name VARCHAR(180) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_research_line_career FOREIGN KEY (career_id) REFERENCES careers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_subjects (
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
  CONSTRAINT fk_subject_teacher FOREIGN KEY (responsible_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  project_type_id SMALLINT UNSIGNED NOT NULL,
  career_id SMALLINT UNSIGNED NOT NULL,
  academic_period_id SMALLINT UNSIGNED NOT NULL,
  title VARCHAR(240) NOT NULL,
  subtitle VARCHAR(300) NULL,
  summary TEXT NULL,
  modality ENUM('individual','group') NULL,
  research_line_id SMALLINT UNSIGNED NULL,
  academic_subject_id BIGINT UNSIGNED NULL,
  proposed_tutor_id BIGINT UNSIGNED NULL,
  tutor_id BIGINT UNSIGNED NULL,
  status VARCHAR(60) NOT NULL DEFAULT 'development',
  current_stage VARCHAR(100) NOT NULL DEFAULT 'registration',
  approved_at DATETIME NULL,
  defense_at DATETIME NULL,
  closed_at DATETIME NULL,
  published_at DATETIME NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_projects_status (status),
  INDEX idx_projects_period (academic_period_id),
  INDEX idx_projects_type (project_type_id),
  CONSTRAINT fk_projects_type FOREIGN KEY (project_type_id) REFERENCES project_types(id),
  CONSTRAINT fk_projects_career FOREIGN KEY (career_id) REFERENCES careers(id),
  CONSTRAINT fk_projects_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id),
  CONSTRAINT fk_projects_research_line FOREIGN KEY (research_line_id) REFERENCES research_lines(id),
  CONSTRAINT fk_projects_subject FOREIGN KEY (academic_subject_id) REFERENCES academic_subjects(id),
  CONSTRAINT fk_projects_proposed_tutor FOREIGN KEY (proposed_tutor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_projects_tutor FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_projects_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_code_sequences (
  project_type_id SMALLINT UNSIGNED NOT NULL,
  code_year SMALLINT UNSIGNED NOT NULL,
  next_number INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (project_type_id, code_year),
  CONSTRAINT fk_code_sequence_type FOREIGN KEY (project_type_id) REFERENCES project_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role_code VARCHAR(50) NOT NULL,
  permission_level ENUM('manage','contribute','review','read') NOT NULL DEFAULT 'read',
  is_leader TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  removed_at DATETIME NULL,
  UNIQUE KEY uq_project_participant (project_id, user_id, role_code),
  INDEX idx_participant_user (user_id, status),
  CONSTRAINT fk_participants_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_participants_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_stages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  stage_code VARCHAR(80) NOT NULL,
  label VARCHAR(160) NOT NULL,
  position SMALLINT UNSIGNED NOT NULL,
  status ENUM('upcoming','current','completed','skipped') NOT NULL DEFAULT 'upcoming',
  completed_at DATETIME NULL,
  UNIQUE KEY uq_project_stage (project_id, stage_code),
  CONSTRAINT fk_stages_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  stage_id BIGINT UNSIGNED NULL,
  version_number INT UNSIGNED NOT NULL,
  title VARCHAR(220) NOT NULL,
  comment TEXT NULL,
  status ENUM('submitted','under_review','changes_required','approved') NOT NULL DEFAULT 'submitted',
  submitted_by BIGINT UNSIGNED NOT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_delivery_version (project_id, version_number),
  CONSTRAINT fk_deliveries_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_deliveries_stage FOREIGN KEY (stage_id) REFERENCES project_stages(id),
  CONSTRAINT fk_deliveries_user FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  delivery_id BIGINT UNSIGNED NULL,
  category VARCHAR(60) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_name VARCHAR(190) NOT NULL UNIQUE,
  storage_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  extension VARCHAR(12) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_files_project (project_id, category),
  CONSTRAINT fk_files_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_files_delivery FOREIGN KEY (delivery_id) REFERENCES project_deliveries(id),
  CONSTRAINT fk_files_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_observations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  delivery_id BIGINT UNSIGNED NULL,
  file_id BIGINT UNSIGNED NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(60) NOT NULL,
  location_reference VARCHAR(180) NULL,
  body TEXT NOT NULL,
  status ENUM('pending','addressed','resolved') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_observations_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_observations_delivery FOREIGN KEY (delivery_id) REFERENCES project_deliveries(id),
  CONSTRAINT fk_observations_file FOREIGN KEY (file_id) REFERENCES project_files(id),
  CONSTRAINT fk_observations_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE observation_responses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  observation_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_responses_observation FOREIGN KEY (observation_id) REFERENCES project_observations(id) ON DELETE CASCADE,
  CONSTRAINT fk_responses_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  delivery_id BIGINT UNSIGNED NULL,
  file_id BIGINT UNSIGNED NULL,
  observation_id BIGINT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_comments_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_comments_author FOREIGN KEY (author_id) REFERENCES users(id),
  CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES project_comments(id),
  CONSTRAINT fk_comments_delivery FOREIGN KEY (delivery_id) REFERENCES project_deliveries(id),
  CONSTRAINT fk_comments_file FOREIGN KEY (file_id) REFERENCES project_files(id),
  CONSTRAINT fk_comments_observation FOREIGN KEY (observation_id) REFERENCES project_observations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  event_date DATE NOT NULL,
  description VARCHAR(500) NULL,
  is_completed TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_events_project_date (project_id, event_date),
  CONSTRAINT fk_events_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_events_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  previous_state JSON NULL,
  new_state JSON NULL,
  reason VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_project_date (project_id, created_at),
  INDEX idx_audit_entity (entity_type, entity_id),
  CONSTRAINT fk_audit_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  type ENUM('delivery','observation','status_change','review','reminder','system','tribunal','repository','comment') NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  action_url VARCHAR(500) NULL,
  action_label VARCHAR(80) NULL,
  metadata JSON NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  archived_at DATETIME NULL,
  deleted_at DATETIME NULL,
  INDEX idx_notifications_user_active (user_id, deleted_at, created_at),
  INDEX idx_notifications_project (project_id),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_favorites (
  user_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, project_id),
  CONSTRAINT fk_favorite_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorite_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_downloads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45) NULL,
  INDEX idx_project_downloads (project_id, downloaded_at),
  CONSTRAINT fk_download_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_download_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (code, name) VALUES
('student','Estudiante'),('teacher','Docente'),('administrator','Administrador');

INSERT INTO project_types (code, name) VALUES
('thesis','Trabajo de titulación'),('pis','Proyecto integrador de saberes'),
('practice','Prácticas preprofesionales'),('community','Proyecto de vinculación');

INSERT INTO careers (code, name) VALUES
('TDS','Tecnología Superior en Desarrollo de Software');

INSERT INTO academic_periods (code, name, starts_on, ends_on, status) VALUES
('2026-I','Periodo académico 2026-I','2026-01-01','2026-06-30','active'),
('2026-II','Periodo académico 2026-II','2026-07-01','2026-12-31','planned'),
('2027-I','Periodo académico 2027-I','2027-01-01','2027-06-30','planned');
