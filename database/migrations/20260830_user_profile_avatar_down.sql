-- Ejecutar únicamente después de confirmar que no hay avatares almacenados que deban conservarse.
ALTER TABLE users
    DROP COLUMN avatar_updated_at,
    DROP COLUMN avatar_path;
