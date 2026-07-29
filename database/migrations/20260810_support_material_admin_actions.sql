-- Estados administrativos generales del material de apoyo.

ALTER TABLE support_materials
  ADD COLUMN published_at DATETIME NULL AFTER publication_date,
  ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1 AFTER status,
  ADD COLUMN deleted_at DATETIME NULL AFTER withdrawn_by,
  ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at,
  ADD COLUMN deletion_reason VARCHAR(500) NULL AFTER deleted_by,
  ADD COLUMN purged_at DATETIME NULL AFTER deletion_reason,
  ADD COLUMN purged_by BIGINT UNSIGNED NULL AFTER purged_at,
  ADD CONSTRAINT fk_support_material_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id),
  ADD CONSTRAINT fk_support_material_purged_by FOREIGN KEY (purged_by) REFERENCES users(id),
  ADD INDEX idx_support_material_visibility (status,is_available,deleted_at,purged_at),
  ADD INDEX idx_support_material_trash (deleted_at,purged_at);

UPDATE support_materials
SET published_at=TIMESTAMP(publication_date,'00:00:00')
WHERE published_at IS NULL AND status IN ('published','withdrawn');
