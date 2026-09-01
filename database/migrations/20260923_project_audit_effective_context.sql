-- Persiste el contexto efectivo del actor al crear cada evento de auditoría.
-- Los eventos históricos conservarán NULL y no se reinterpretan retroactivamente.
ALTER TABLE project_audit_log
    ADD COLUMN IF NOT EXISTS effective_context VARCHAR(32) NULL AFTER user_id;
