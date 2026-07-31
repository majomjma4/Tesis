-- Registro estructurado de cierres/promociones de períodos académicos.
-- Las fechas se guardan en UTC, igual que la conexión PDO de la aplicación.

CREATE TABLE IF NOT EXISTS academic_period_transitions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  closed_period_id SMALLINT UNSIGNED NOT NULL,
  activated_period_id SMALLINT UNSIGNED NOT NULL,
  performed_by BIGINT UNSIGNED NOT NULL,
  performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reverted_by BIGINT UNSIGNED NULL,
  reverted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_period_transition_event (closed_period_id, activated_period_id, performed_at),
  INDEX idx_period_transition_latest (performed_at, id),
  INDEX idx_period_transition_actor (performed_by, performed_at),
  INDEX idx_period_transition_reverted (reverted_at),
  CONSTRAINT chk_period_transition_distinct CHECK (closed_period_id <> activated_period_id),
  CONSTRAINT chk_period_transition_reversal CHECK (
    (reverted_by IS NULL AND reverted_at IS NULL)
    OR (reverted_by IS NOT NULL AND reverted_at IS NOT NULL)
  ),
  CONSTRAINT fk_period_transition_closed FOREIGN KEY (closed_period_id) REFERENCES academic_periods(id),
  CONSTRAINT fk_period_transition_activated FOREIGN KEY (activated_period_id) REFERENCES academic_periods(id),
  CONSTRAINT fk_period_transition_performer FOREIGN KEY (performed_by) REFERENCES users(id),
  CONSTRAINT fk_period_transition_reverter FOREIGN KEY (reverted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consolida una transición reciente anterior a la migración únicamente cuando
-- los dos eventos estructurados de auditoría y los estados actuales coinciden.
INSERT INTO academic_period_transitions
  (closed_period_id, activated_period_id, performed_by, performed_at)
SELECT
  closed_event.entity_id,
  CAST(JSON_UNQUOTE(JSON_EXTRACT(closed_event.details, '$.activated_period_id')) AS UNSIGNED),
  closed_event.actor_user_id,
  closed_event.created_at
FROM admin_audit_log closed_event
JOIN academic_periods closed_period
  ON closed_period.id=closed_event.entity_id
 AND closed_period.status='closed'
JOIN academic_periods active_period
  ON active_period.id=CAST(JSON_UNQUOTE(JSON_EXTRACT(closed_event.details, '$.activated_period_id')) AS UNSIGNED)
 AND active_period.status='active'
WHERE closed_event.action='academic_period_closed'
  AND closed_event.result='correct'
  AND closed_event.actor_user_id IS NOT NULL
  AND closed_event.created_at>=UTC_TIMESTAMP()-INTERVAL 24 HOUR
  AND EXISTS (
    SELECT 1
    FROM admin_audit_log activated_event
    WHERE activated_event.action='academic_period_activated'
      AND activated_event.result='correct'
      AND activated_event.entity_id=active_period.id
      AND activated_event.actor_user_id=closed_event.actor_user_id
      AND activated_event.created_at=closed_event.created_at
      AND CAST(JSON_UNQUOTE(JSON_EXTRACT(activated_event.details, '$.closed_period_id')) AS UNSIGNED)=closed_period.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM academic_period_transitions existing
    WHERE existing.closed_period_id=closed_period.id
      AND existing.activated_period_id=active_period.id
      AND existing.performed_at=closed_event.created_at
  )
ORDER BY closed_event.created_at DESC,closed_event.id DESC
LIMIT 1;
