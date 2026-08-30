-- Reconciliación histórica defensiva para PIS-2026-014 (proyecto 295).
--
-- NO ejecutar sin revisar primero la salida del precheck.
-- Este archivo no modifica el proyecto ni los documentos: sólo corrige el
-- resultado histórico de cada entrega cuando ambos estados actuales siguen
-- siendo exactamente los esperados.

-- PRECHECK 1: el ENUM ya debe ser canónico.
SELECT
    CASE WHEN COLUMN_TYPE = 'enum(\'submitted\',\'under_review\',\'corrections_requested\',\'approved\')'
         THEN 'PASS'
         ELSE 'STOP: ENUM inesperado'
    END AS delivery_status_enum_check,
    COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'project_deliveries'
  AND COLUMN_NAME = 'status';

-- PRECHECK 2: identidad y estado final del proyecto, y exactamente las dos
-- entregas auditadas.
SELECT
    CASE WHEN EXISTS (
        SELECT 1
        FROM projects
        WHERE id = 295
          AND code = 'PIS-2026-014'
          AND status = 'published'
          AND is_available = 1
          AND published_at = '2026-08-30 06:13:56'
    )
    AND (SELECT COUNT(*) FROM project_deliveries WHERE project_id = 295) = 2
    AND (SELECT COUNT(*) FROM project_deliveries
         WHERE project_id = 295
           AND id = 827
           AND version_number = 1
           AND status = 'under_review') = 1
    AND (SELECT COUNT(*) FROM project_deliveries
         WHERE project_id = 295
           AND id = 828
           AND version_number = 2
           AND status = 'under_review') = 1
    AND EXISTS (SELECT 1 FROM project_audit_log
                WHERE project_id = 295
                  AND action = 'project_submitted_for_review'
                  AND entity_type = 'project_delivery'
                  AND entity_id = 827)
    AND EXISTS (SELECT 1 FROM project_audit_log
                WHERE project_id = 295
                  AND action = 'project_submitted_for_review'
                  AND entity_type = 'project_delivery'
                  AND entity_id = 828)
    THEN 'PASS'
    ELSE 'STOP: proyecto o deliveries no coinciden con la auditoría'
    END AS reconciliation_precheck;

SELECT id, project_id, version_number, status, submitted_at
FROM project_deliveries
WHERE project_id = 295 AND id IN (827, 828)
ORDER BY version_number, id;

-- EJECUCIÓN CONTROLADA: continuar sólo si todos los prechecks devuelven PASS.
START TRANSACTION;

UPDATE project_deliveries
SET status = 'corrections_requested'
WHERE id = 827
  AND project_id = 295
  AND version_number = 1
  AND status = 'under_review';
SELECT ROW_COUNT() AS rows_delivery_827_expected_1;

UPDATE project_deliveries
SET status = 'approved'
WHERE id = 828
  AND project_id = 295
  AND version_number = 2
  AND status = 'under_review';
SELECT ROW_COUNT() AS rows_delivery_828_expected_1;

-- POSTCHECK DENTRO DE LA TRANSACCIÓN.
SELECT id, project_id, version_number, status
FROM project_deliveries
WHERE project_id = 295 AND id IN (827, 828)
ORDER BY version_number, id;

SELECT id, code, status, is_available, published_at
FROM projects
WHERE id = 295;

-- Revisar ambos ROW_COUNT() y el postcheck antes de confirmar.
-- Si alguno no es 1 o el postcheck no coincide: ROLLBACK;
-- Si todo coincide: COMMIT;
-- COMMIT;
-- ROLLBACK;
