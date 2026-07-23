ALTER TABLE admin_audit_log
  ADD COLUMN action_label VARCHAR(180) NULL AFTER action,
  ADD COLUMN module VARCHAR(80) NULL AFTER action_label,
  ADD COLUMN element_label VARCHAR(255) NULL AFTER entity_id,
  ADD COLUMN result ENUM('correct','failed') NOT NULL DEFAULT 'correct' AFTER element_label,
  ADD INDEX idx_admin_activity_filters (module, action, result, created_at),
  ADD INDEX idx_admin_activity_actor_date (actor_user_id, created_at);
