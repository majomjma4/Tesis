-- Fase 1 del rol Administrador: seguridad de sesión y contraseña temporal.
ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_warning_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER must_change_password;
ALTER TABLE users ADD COLUMN IF NOT EXISTS temporary_password_expires_at DATETIME NULL AFTER password_warning_count;
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER temporary_password_expires_at;
ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER last_login_at;
