-- Motivo obligatorio asociado a los rechazos de solicitudes post-publicación.
ALTER TABLE project_adjustment_requests
  ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL AFTER closed_by;
