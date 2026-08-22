-- Enforces the institutional invariant: zero or one academic period may be active.
-- This migration is intentionally not executed automatically.
-- Precondition to verify before applying:
-- SELECT COUNT(*) FROM academic_periods WHERE status='active';
-- If the count is greater than one, resolve the data inconsistency first.

ALTER TABLE academic_periods
  ADD COLUMN IF NOT EXISTS active_guard TINYINT
    AS (CASE WHEN status = 'active' THEN 1 ELSE NULL END) PERSISTENT;

ALTER TABLE academic_periods
  ADD UNIQUE INDEX IF NOT EXISTS uq_academic_periods_single_active (active_guard);
