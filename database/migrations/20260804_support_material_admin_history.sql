-- Historial administrativo de materiales de apoyo.
-- MariaDB: ejecutar una sola vez sobre la base de datos de la aplicación.
-- No modifica ni elimina registros existentes.

ALTER TABLE admin_audit_log
    ADD INDEX idx_admin_audit_entity_date (entity_type, entity_id, created_at);
