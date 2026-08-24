ALTER TABLE project_participants
    ADD COLUMN IF NOT EXISTS tribunal_position VARCHAR(20) NULL AFTER role_code;

ALTER TABLE project_participants
    ADD INDEX idx_project_participant_tribunal_position (project_id, role_code, tribunal_position);
