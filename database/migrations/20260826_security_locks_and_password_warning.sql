-- Migración de seguridad: bloqueo temporal por múltiples intentos fallidos de inicio de sesión y persistencia por usuario de omitir aviso diario.

CREATE TABLE IF NOT EXISTS login_security_locks (
    lock_key VARCHAR(64) NOT NULL PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 1,
    locked_until DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS temporary_password_last_warning_at DATE NULL AFTER temporary_password_expires_at;
