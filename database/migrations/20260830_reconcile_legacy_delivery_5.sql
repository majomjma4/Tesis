-- Reconciliacion controlada de project_deliveries.id=5.
--
-- Evidencia: el fixture TIT-2026-001 de scripts/seed_admin_demo.php crea esta
-- entrega con status='corrections_requested'. La BD recuperada conserva el
-- ENUM legacy sin ese valor y, con sql_mode no estricto, lo almaceno como ''.
-- El ENUM actual solo permite representar el equivalente legacy
-- 'changes_required'. La migracion 20260818 lo convertira posteriormente a
-- 'corrections_requested'.
--
-- Este archivo deja la transaccion abierta deliberadamente. Tras revisar los
-- SELECT posteriores, ejecutar COMMIT si todo es correcto o ROLLBACK si no lo es.

SELECT id,project_id,stage_id,version_number,title,comment,status,submitted_by,submitted_at
FROM project_deliveries
WHERE id=5;

SELECT id,code,status,current_stage,is_available,created_at,updated_at,published_at,approved_at,closed_at
FROM projects
WHERE id=1;

START TRANSACTION;

SELECT id,project_id,version_number,status
FROM project_deliveries
WHERE id=5 AND project_id=1 AND status=''
FOR UPDATE;

UPDATE project_deliveries
SET status='changes_required'
WHERE id=5
  AND project_id=1
  AND status='';

SELECT ROW_COUNT() AS affected_rows;

SELECT id,project_id,stage_id,version_number,title,comment,status,submitted_by,submitted_at
FROM project_deliveries
WHERE id=5;

SELECT id,project_id,version_number,status
FROM project_deliveries
WHERE id=5 AND project_id=1 AND status='changes_required';

-- No ejecutar automaticamente: confirmar que affected_rows=1 y que el
-- segundo SELECT devuelve exactamente una fila antes de elegir una opcion.
-- COMMIT;
-- ROLLBACK;
