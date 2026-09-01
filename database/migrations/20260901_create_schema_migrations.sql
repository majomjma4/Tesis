-- Control de versiones posterior al baseline estructural.
-- No se ejecuta automaticamente sobre ninguna base existente.
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_id VARCHAR(191) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checksum_sha256 CHAR(64) NOT NULL,
    PRIMARY KEY (migration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
