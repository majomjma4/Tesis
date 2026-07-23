-- Normaliza los datos de demostración al primer período real de la plataforma.
-- Se conserva el registro activo para mantener todas sus relaciones existentes.
START TRANSACTION;

DELETE FROM academic_periods
WHERE status IN ('closed', 'planned')
  AND code IN ('2026-I', '2027-I')
  AND NOT EXISTS (
      SELECT 1 FROM projects WHERE projects.academic_period_id = academic_periods.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM student_enrollments WHERE student_enrollments.academic_period_id = academic_periods.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM academic_subjects WHERE academic_subjects.academic_period_id = academic_periods.id
  );

UPDATE academic_periods
SET code = '2026-I',
    name = 'I PAO 2026',
    starts_on = '2026-04-01',
    ends_on = '2026-09-30',
    status = 'active'
WHERE code = '2026-II'
  AND status = 'active';

COMMIT;
