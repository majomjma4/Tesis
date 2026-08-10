-- Fotografía opcional de perfil; el binario se guarda fuera del directorio público.
ALTER TABLE users
    ADD COLUMN avatar_path VARCHAR(500) NULL AFTER password_changed_at,
    ADD COLUMN avatar_updated_at DATETIME NULL AFTER avatar_path;
