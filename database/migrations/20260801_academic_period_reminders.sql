ALTER TABLE notifications
  ADD COLUMN deduplication_key VARCHAR(190) NULL AFTER metadata,
  ADD UNIQUE INDEX uq_notifications_user_deduplication (user_id, deduplication_key);
