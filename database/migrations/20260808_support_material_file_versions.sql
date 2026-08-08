-- Conserva versiones retiradas cuando un archivo de material es reemplazado.

CREATE TABLE IF NOT EXISTS support_material_file_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_id BIGINT UNSIGNED NOT NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_name VARCHAR(255) NOT NULL,
  relative_path VARCHAR(500) NOT NULL,
  extension VARCHAR(15) NOT NULL,
  mime_type VARCHAR(150) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  replaced_by BIGINT UNSIGNED NULL,
  replaced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_support_file_version_file
    FOREIGN KEY (file_id) REFERENCES support_material_files(id),
  CONSTRAINT fk_support_file_version_material
    FOREIGN KEY (material_id) REFERENCES support_materials(id),
  CONSTRAINT fk_support_file_version_actor
    FOREIGN KEY (replaced_by) REFERENCES users(id),
  INDEX idx_support_file_version_history (file_id, replaced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
