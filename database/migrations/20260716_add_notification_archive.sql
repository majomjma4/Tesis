ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER updated_at,
    ADD INDEX IF NOT EXISTS idx_notifications_archived (archived_at);
