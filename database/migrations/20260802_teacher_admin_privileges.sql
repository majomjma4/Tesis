-- Separa el tipo académico del nivel de acceso administrativo.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER full_name,
  ADD COLUMN IF NOT EXISTS is_initial_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin,
  ADD INDEX IF NOT EXISTS idx_users_admin_access (is_admin, status, deleted_at, purged_at),
  ADD CONSTRAINT IF NOT EXISTS chk_initial_admin_requires_access CHECK (is_initial_admin = 0 OR is_admin = 1);

-- Mantiene compatible cualquier administrador heredado.
UPDATE users u
SET u.is_admin = 1,
    u.is_initial_admin = CASE
      WHEN EXISTS (
        SELECT 1
        FROM user_roles ur
        JOIN roles r ON r.id = ur.role_id
        WHERE ur.user_id = u.id AND r.code = 'administrator'
      )
      AND NOT EXISTS (
        SELECT 1 FROM teacher_profiles tp WHERE tp.user_id = u.id
      )
      THEN 1 ELSE 0
    END
WHERE EXISTS (
  SELECT 1
  FROM user_roles ur
  JOIN roles r ON r.id = ur.role_id
  WHERE ur.user_id = u.id AND r.code = 'administrator'
);
