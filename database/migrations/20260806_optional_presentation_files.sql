-- Hace opcional el archivo de presentación sin mezclarlo con el destacado.
-- Conserva las claves foráneas y los triggers que validan pertenencia,
-- disponibilidad y compatibilidad cuando presentation_file_id no es NULL.

ALTER TABLE support_materials
    DROP CONSTRAINT chk_support_material_published_presentation;

ALTER TABLE projects
    DROP CONSTRAINT chk_project_published_presentation;

DROP TRIGGER IF EXISTS trg_support_presentation_file_retire;
DROP TRIGGER IF EXISTS trg_project_presentation_file_retire;

-- Estos proyectos de paginación fueron sembrados explícitamente como
-- publicados y 20260805 los degradó únicamente por no poseer archivos.
-- PIS-2026-013 no se incluye: su auditoría demuestra que ya había sido
-- cambiado manualmente a aprobado antes de la migración obligatoria.
UPDATE projects
SET status = 'published',
    published_at = COALESCE(published_at, closed_at, approved_at, created_at)
WHERE code IN (
    'PIS-2026-004','PIS-2026-005','PIS-2026-006',
    'PIS-2026-007','PIS-2026-008','PIS-2026-009',
    'PIS-2026-010','PIS-2026-011','PIS-2026-012'
)
  AND status = 'approved'
  AND current_stage = 'published'
  AND presentation_file_id IS NULL;
