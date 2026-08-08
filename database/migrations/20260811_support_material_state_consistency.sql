-- Normaliza combinaciones administrativas inequívocas sin alterar archivos,
-- historial ni materiales que se encuentren en Papelera.
-- Es seguro ejecutar este archivo más de una vez.

UPDATE support_materials
SET is_available = 0
WHERE status = 'withdrawn'
  AND is_available <> 0
  AND deleted_at IS NULL
  AND purged_at IS NULL;

UPDATE support_materials
SET is_available = 0
WHERE deleted_at IS NOT NULL
  AND purged_at IS NULL
  AND is_available <> 0;
