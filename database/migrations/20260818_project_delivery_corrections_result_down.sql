-- Reversión controlada de la FASE C. Solo restaura filas capturadas por la migración directa.
SET @schema_name := DATABASE();
SET @backup_exists := EXISTS(
  SELECT 1 FROM information_schema.tables
  WHERE table_schema=@schema_name AND table_name='migration_project_flow_20260818_backup'
);

ALTER TABLE project_deliveries
  MODIFY status ENUM('submitted','under_review','changes_required','corrections_requested','approved')
  NOT NULL DEFAULT 'submitted';

SET @sql := IF(
  @backup_exists,
  "UPDATE project_deliveries d JOIN migration_project_flow_20260818_backup b ON b.entity_type='delivery' AND b.entity_id=d.id SET d.status=b.original_status WHERE d.status='corrections_requested'",
  'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @backup_exists,
  "UPDATE projects p JOIN migration_project_flow_20260818_backup b ON b.entity_type='project' AND b.entity_id=p.id SET p.status=b.original_status,p.updated_at=b.original_updated_at WHERE p.status='development'",
  'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @backup_exists,
  "DELETE a FROM project_audit_log a JOIN migration_project_flow_20260818_backup b ON b.entity_type='project' AND b.entity_id=a.project_id WHERE a.action='project_legacy_status_migrated' AND JSON_UNQUOTE(JSON_EXTRACT(a.new_state,'$.migration_id'))='20260818_project_delivery_corrections_result'",
  'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS migration_project_flow_20260818_backup;

ALTER TABLE project_deliveries
  MODIFY status ENUM('submitted','under_review','changes_required','approved')
  NOT NULL DEFAULT 'submitted';

