START TRANSACTION;

UPDATE careers
SET name = 'Desarrollo de Software',
    is_active = CASE WHEN code = 'TDS' THEN 1 ELSE 0 END;

COMMIT;
