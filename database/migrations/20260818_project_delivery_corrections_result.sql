-- FASE C: separa el resultado de revisión de una entrega del estado académico del proyecto.
-- Reversible mediante 20260818_project_delivery_corrections_result_down.sql.
SET @schema_name := DATABASE();

CREATE TABLE IF NOT EXISTS migration_project_flow_20260818_backup (
  entity_type VARCHAR(20) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  original_status VARCHAR(60) NOT NULL,
  original_updated_at DATETIME NULL,
  captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migration_project_flow_20260818_backup(entity_type,entity_id,original_status,original_updated_at)
SELECT 'delivery',id,status,NULL FROM project_deliveries WHERE status='changes_required';

INSERT IGNORE INTO migration_project_flow_20260818_backup(entity_type,entity_id,original_status,original_updated_at)
SELECT 'project',id,status,updated_at FROM projects WHERE status='changes_required';

SET @delivery_column_type := (
  SELECT column_type FROM information_schema.columns
  WHERE table_schema=@schema_name AND table_name='project_deliveries' AND column_name='status'
);
SET @sql := IF(
  LOCATE('corrections_requested',@delivery_column_type)=0 OR EXISTS(SELECT 1 FROM project_deliveries WHERE status='changes_required'),
  "ALTER TABLE project_deliveries MODIFY status ENUM('submitted','under_review','changes_required','corrections_requested','approved') NOT NULL DEFAULT 'submitted'",
  'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE project_deliveries SET status='corrections_requested' WHERE status='changes_required';

INSERT INTO project_audit_log(project_id,user_id,action,entity_type,entity_id,previous_state,new_state,reason)
SELECT p.id,NULL,'project_legacy_status_migrated','project',p.id,
       JSON_OBJECT('status','changes_required','updated_at',DATE_FORMAT(b.original_updated_at,'%Y-%m-%d %H:%i:%s')),
       JSON_OBJECT(
         'status','development',
         'migration_id','20260818_project_delivery_corrections_result',
         'pending_observation_count',(SELECT COUNT(*) FROM project_observations o WHERE o.project_id=p.id AND o.status='pending'),
         'requires_manual_review',IF(EXISTS(SELECT 1 FROM project_observations o WHERE o.project_id=p.id AND o.status='pending'),FALSE,TRUE)
       ),
       IF(EXISTS(SELECT 1 FROM project_observations o WHERE o.project_id=p.id AND o.status='pending'),
          'Estado heredado migrado a En desarrollo; conserva observaciones pendientes.',
          'Estado heredado migrado a En desarrollo sin observaciones pendientes; requiere revisión manual.')
FROM projects p
JOIN migration_project_flow_20260818_backup b ON b.entity_type='project' AND b.entity_id=p.id
WHERE p.status='changes_required'
  AND NOT EXISTS(
    SELECT 1 FROM project_audit_log a
    WHERE a.project_id=p.id AND a.action='project_legacy_status_migrated'
      AND JSON_UNQUOTE(JSON_EXTRACT(a.new_state,'$.migration_id'))='20260818_project_delivery_corrections_result'
  );

UPDATE projects p
JOIN migration_project_flow_20260818_backup b ON b.entity_type='project' AND b.entity_id=p.id
SET p.status='development',p.updated_at=b.original_updated_at
WHERE p.status='changes_required';

SET @legacy_delivery_count := (SELECT COUNT(*) FROM project_deliveries WHERE status='changes_required');
SET @sql := IF(
  @legacy_delivery_count=0,
  "ALTER TABLE project_deliveries MODIFY status ENUM('submitted','under_review','corrections_requested','approved') NOT NULL DEFAULT 'submitted'",
  "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Persisten entregas con changes_required; no se puede cerrar la migración'"
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

