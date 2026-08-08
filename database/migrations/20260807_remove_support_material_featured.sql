-- Elimina el estado redundante de documento destacado.
-- La vista inicial del expediente depende exclusivamente de presentation_file_id.

ALTER TABLE support_material_files
  DROP COLUMN IF EXISTS is_primary;
