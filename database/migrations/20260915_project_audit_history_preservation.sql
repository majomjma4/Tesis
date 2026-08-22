-- Preserve project audit history after definitive project deletion.
-- Precondition: project_audit_log contains only the expected legacy orphan rows
-- and all audit JSON/actor integrity checks have passed.

ALTER TABLE project_audit_log
    DROP FOREIGN KEY fk_audit_project;

ALTER TABLE project_audit_log
    MODIFY COLUMN project_id BIGINT UNSIGNED NULL;

UPDATE project_audit_log audit
LEFT JOIN projects project ON project.id = audit.project_id
SET audit.project_id = NULL
WHERE audit.project_id IS NOT NULL
  AND project.id IS NULL;

ALTER TABLE project_audit_log
    ADD CONSTRAINT fk_audit_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE SET NULL;
