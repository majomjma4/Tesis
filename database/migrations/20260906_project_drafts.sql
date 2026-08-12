CREATE TABLE IF NOT EXISTS project_drafts (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  payload JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uq_project_drafts_user (user_id),
  INDEX idx_project_drafts_expiration (expires_at),
  CONSTRAINT fk_project_drafts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_draft_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  draft_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_name VARCHAR(190) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  extension VARCHAR(12) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  zip_meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_project_draft_files_storage_name (storage_name),
  INDEX idx_project_draft_files_draft (draft_id),
  INDEX idx_project_draft_files_owner (user_id, draft_id),
  CONSTRAINT fk_project_draft_files_draft FOREIGN KEY (draft_id) REFERENCES project_drafts(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_draft_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
