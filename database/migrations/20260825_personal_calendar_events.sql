-- Agenda personal: conserva project_events como fuente única y permite hora/modificación.
ALTER TABLE project_events
  ADD COLUMN IF NOT EXISTS event_time TIME NULL AFTER event_date;

ALTER TABLE project_events
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE INDEX IF NOT EXISTS idx_project_events_owner_date
  ON project_events(created_by, event_date);
