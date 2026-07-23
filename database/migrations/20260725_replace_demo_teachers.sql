-- Sustituye docentes demostrativos por el catálogo institucional confirmado.
-- Los correos *.invalid son temporales hasta recibir las cuentas institucionales.
UPDATE users SET email='maribel.fierro.pendiente@local.invalid', full_name='Msc. Maribel Fierro Montero', status='active', deleted_at=NULL, purged_at=NULL
WHERE email IN ('laura.villacis.demo@correo.com','maribel.fierro.pendiente@local.invalid');
UPDATE users SET email='maria.navarrete.pendiente@local.invalid', full_name='Msc. Maria Elena Navarrete', status='active', deleted_at=NULL, purged_at=NULL
WHERE email IN ('andres.salazar.demo@correo.com','maria.navarrete.pendiente@local.invalid');
UPDATE users SET email='diana.alegria.pendiente@local.invalid', full_name='Lic. Diana Alegría Camino', status='active', deleted_at=NULL, purged_at=NULL
WHERE email IN ('paola.rivas.demo@correo.com','diana.alegria.pendiente@local.invalid');
UPDATE users SET email='diana.ramirez.pendiente@local.invalid', full_name='Msc. Diana Anaid Ramirez', status='active', deleted_at=NULL, purged_at=NULL
WHERE email IN ('tesisdoc@gmail.com','diana.ramirez.pendiente@local.invalid');

INSERT INTO users (email,password_hash,must_change_password,full_name,status)
VALUES
('alex.galarza.pendiente@local.invalid','$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,'Abg. Alex Fabián Galarza','active'),
('henrry.marino.pendiente@local.invalid','$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,'Msc. Henrry Mariño Acosta','active')
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),status='active',deleted_at=NULL,purged_at=NULL;

INSERT IGNORE INTO user_roles (user_id,role_id)
SELECT u.id,r.id
FROM users u CROSS JOIN roles r
WHERE r.code='teacher' AND u.email IN (
  'maribel.fierro.pendiente@local.invalid',
  'maria.navarrete.pendiente@local.invalid',
  'diana.alegria.pendiente@local.invalid',
  'diana.ramirez.pendiente@local.invalid',
  'alex.galarza.pendiente@local.invalid',
  'henrry.marino.pendiente@local.invalid'
);

INSERT INTO teacher_profiles (user_id,institutional_code,academic_title,can_tutor)
SELECT u.id,
  CASE u.email
    WHEN 'maribel.fierro.pendiente@local.invalid' THEN '0202053801'
    WHEN 'maria.navarrete.pendiente@local.invalid' THEN '0202053802'
    WHEN 'diana.alegria.pendiente@local.invalid' THEN '0202053803'
    WHEN 'diana.ramirez.pendiente@local.invalid' THEN '0202053804'
    WHEN 'alex.galarza.pendiente@local.invalid' THEN '0202053805'
    ELSE '0202053806'
  END,
  CASE u.email
    WHEN 'diana.alegria.pendiente@local.invalid' THEN 'Lic. (por confirmar)'
    WHEN 'alex.galarza.pendiente@local.invalid' THEN 'Abg. (por confirmar)'
    ELSE 'Msc. (por confirmar)'
  END,
  1
FROM users u
WHERE u.email IN (
  'maribel.fierro.pendiente@local.invalid',
  'maria.navarrete.pendiente@local.invalid',
  'diana.alegria.pendiente@local.invalid',
  'diana.ramirez.pendiente@local.invalid',
  'alex.galarza.pendiente@local.invalid',
  'henrry.marino.pendiente@local.invalid'
)
ON DUPLICATE KEY UPDATE institutional_code=VALUES(institutional_code),academic_title=VALUES(academic_title),can_tutor=1;

UPDATE teacher_profiles tp
INNER JOIN users u ON u.id=tp.user_id
SET tp.can_tutor=0
WHERE u.email NOT IN (
  'maribel.fierro.pendiente@local.invalid',
  'maria.navarrete.pendiente@local.invalid',
  'diana.alegria.pendiente@local.invalid',
  'diana.ramirez.pendiente@local.invalid',
  'alex.galarza.pendiente@local.invalid',
  'henrry.marino.pendiente@local.invalid'
);
