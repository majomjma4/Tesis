CREATE TABLE IF NOT EXISTS support_material_audit_reads (
  user_id BIGINT UNSIGNED NOT NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  last_seen_audit_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id,material_id),
  CONSTRAINT fk_support_material_audit_read_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_support_material_audit_read_material
    FOREIGN KEY (material_id) REFERENCES support_materials(id) ON DELETE CASCADE,
  INDEX idx_support_material_audit_read_event (last_seen_audit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
