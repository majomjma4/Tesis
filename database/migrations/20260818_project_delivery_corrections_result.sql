-- FASE C: separa el resultado de revision de una entrega del estado academico del proyecto.
-- Reversible mediante 20260818_project_delivery_corrections_result_down.sql.
--
-- La migracion falla cerradamente si encuentra un valor fuera del contrato. En
-- particular, MariaDB puede conservar '' como el valor de error de un ENUM
-- cuando una fila se inserto en modo no estricto; no se debe reinterpretar ni
-- convertir silenciosamente ese estado.
SET @schema_name := DATABASE();
SET @delivery_column_type := LOWER(COALESCE((
  SELECT column_type
  FROM information_schema.columns
  WHERE table_schema=@schema_name AND table_name='project_deliveries' AND column_name='status'
),''));
SET @legacy_delivery_enum := 'enum(''submitted'',''under_review'',''changes_required'',''approved'')';
SET @transition_delivery_enum := 'enum(''submitted'',''under_review'',''changes_required'',''corrections_requested'',''approved'')';
SET @canonical_delivery_enum := 'enum(''submitted'',''under_review'',''corrections_requested'',''approved'')';
SET @invalid_delivery_count := (
  SELECT COUNT(*)
  FROM project_deliveries
  WHERE status IS NULL
     OR status NOT IN ('submitted','under_review','changes_required','corrections_requested','approved')
);
SET @required_table_count := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema=@schema_name
    AND table_name IN ('project_deliveries','projects','project_audit_log','project_observations')
);
SET @preflight_sql := IF(
  @delivery_column_type NOT IN (@legacy_delivery_enum,@transition_delivery_enum,@canonical_delivery_enum)
  OR @invalid_delivery_count > 0
  OR @required_table_count <> 4,
  "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Migracion detenida: project_deliveries.status no cumple el contrato esperado o contiene valores invalidos'",
  'SELECT 1'
);
PREPARE stmt FROM @preflight_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS migration_project_flow_20260818_backup (
  entity_type VARCHAR(20) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  original_status VARCHAR(60) NOT NULL,
  original_updated_at DATETIME NULL,
  captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DDL hace commit implicito en MariaDB. Las mutaciones posteriores quedan en
-- una transaccion para que un fallo de datos no deje cambios parciales.
SET @sql := IF(
  @delivery_column_type=@legacy_delivery_enum,
  "ALTER TABLE project_deliveries MODIFY status ENUM('submitted','under_review','changes_required','corrections_requested','approved') NOT NULL DEFAULT 'submitted'",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

START TRANSACTION;

INSERT IGNORE INTO migration_project_flow_20260818_backup(entity_type,entity_id,original_status,original_updated_at)
SELECT 'delivery',id,status,NULL FROM project_deliveries WHERE status='changes_required';

INSERT IGNORE INTO migration_project_flow_20260818_backup(entity_type,entity_id,original_status,original_updated_at)
SELECT 'project',id,status,updated_at FROM projects WHERE status='changes_required';

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
          'Estado heredado migrado a En desarrollo sin observaciones pendientes; requiere revision manual.')
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
SET @delivery_column_type := LOWER(COALESCE((
  SELECT column_type
  FROM information_schema.columns
  WHERE table_schema=@schema_name AND table_name='project_deliveries' AND column_name='status'
),''));
SET @sql := IF(
  @legacy_delivery_count>0,
  "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Persisten entregas con changes_required; no se puede cerrar la migracion'",
  IF(@delivery_column_type=@canonical_delivery_enum,
    'SELECT 1',
    "ALTER TABLE project_deliveries MODIFY status ENUM('submitted','under_review','corrections_requested','approved') NOT NULL DEFAULT 'submitted'"
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
