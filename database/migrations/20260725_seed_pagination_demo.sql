-- Datos demostrativos transportables para comprobar paginación (>10 registros).
SET @student_role_id = (SELECT id FROM roles WHERE code='student' LIMIT 1);
SET @career_id = (SELECT id FROM careers WHERE is_active=1 ORDER BY id LIMIT 1);
SET @period_id = (SELECT id FROM academic_periods WHERE status='active' ORDER BY id DESC LIMIT 1);
SET @project_type_id = (SELECT id FROM project_types WHERE code='pis' LIMIT 1);
SET @teacher_id = (SELECT tp.user_id FROM teacher_profiles tp INNER JOIN users u ON u.id=tp.user_id WHERE u.status='active' AND tp.can_tutor=1 ORDER BY u.full_name LIMIT 1);
SET @admin_id = (SELECT id FROM users WHERE email='tesisad@gmail.com' LIMIT 1);

INSERT INTO users (email,password_hash,must_change_password,password_warning_count,full_name,status)
VALUES
('paginacion01@demo.local','$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'Adriana Ponce Vera','active'),
('paginacion02@demo.local','$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'Bruno Cárdenas Mena','active'),
('paginacion03@demo.local','$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'Camila Andrade Ruiz','active'),
('paginacion04@demo.local','$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'David Guerrero Paz','active'),
('paginacion05@demo.local','$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'Elena Morales Cedeño','active'),
('paginacion06@demo.local','$2y$10$wsCvQrl1SH6AIIjsYBQIoOhmr22DD4iiniQisj48g1NInZxwd.bRy',1,0,'Fernando Viteri León','active')
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),status='active',deleted_at=NULL,purged_at=NULL;

INSERT IGNORE INTO user_roles (user_id,role_id)
SELECT u.id,@student_role_id FROM users u WHERE u.email LIKE 'paginacion0%@demo.local';

INSERT INTO student_profiles (user_id,institutional_code,career_id)
SELECT u.id,CONCAT('02020538',LPAD(SUBSTRING(u.email,11,2),2,'0')),@career_id
FROM users u WHERE u.email LIKE 'paginacion0%@demo.local'
ON DUPLICATE KEY UPDATE career_id=VALUES(career_id);

INSERT INTO student_enrollments (student_id,academic_period_id,career_id,semester,status)
SELECT u.id,@period_id,@career_id,4,'active'
FROM users u WHERE u.email LIKE 'paginacion0%@demo.local'
ON DUPLICATE KEY UPDATE career_id=VALUES(career_id),semester=4,status='active';

INSERT INTO projects (code,project_type_id,career_id,academic_period_id,title,subtitle,summary,modality,proposed_tutor_id,tutor_id,status,current_stage,created_by,approved_at,closed_at,published_at)
VALUES
('PIS-2026-004',@project_type_id,@career_id,@period_id,'Sistema de reservas para laboratorios','Prueba de paginación 1','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion01@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('PIS-2026-005',@project_type_id,@career_id,@period_id,'Aplicación de control de asistencia','Prueba de paginación 2','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion02@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('PIS-2026-006',@project_type_id,@career_id,@period_id,'Portal de seguimiento de tutorías','Prueba de paginación 3','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion03@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('PIS-2026-007',@project_type_id,@career_id,@period_id,'Gestor documental para secretaría','Prueba de paginación 4','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion04@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('PIS-2026-008',@project_type_id,@career_id,@period_id,'Panel de indicadores estudiantiles','Prueba de paginación 5','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion05@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('PIS-2026-009',@project_type_id,@career_id,@period_id,'Plataforma de encuestas académicas','Prueba de paginación 6','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion06@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('PIS-2026-010',@project_type_id,@career_id,@period_id,'Sistema de préstamos tecnológicos','Prueba de paginación 7','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion01@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('PIS-2026-011',@project_type_id,@career_id,@period_id,'Agenda institucional inteligente','Prueba de paginación 8','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion02@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('PIS-2026-012',@project_type_id,@career_id,@period_id,'Repositorio de recursos didácticos','Prueba de paginación 9','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion03@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),
('PIS-2026-013',@project_type_id,@career_id,@period_id,'Monitoreo de infraestructura de red','Prueba de paginación 10','Proyecto demostrativo para validar listados extensos.','group',@teacher_id,@teacher_id,'published','published',(SELECT id FROM users WHERE email='paginacion04@demo.local'),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE title=VALUES(title),subtitle=VALUES(subtitle),summary=VALUES(summary),tutor_id=VALUES(tutor_id),status='published',current_stage='published',published_at=COALESCE(published_at,CURRENT_TIMESTAMP),deleted_at=NULL;

DELETE FROM notifications
WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.source'))='pagination_demo';

INSERT INTO notifications (user_id,type,title,message,metadata,is_read,created_at)
VALUES
(@admin_id,'system','Datos demostrativos preparados','El listado contiene registros suficientes para comprobar la paginación.','{"source":"pagination_demo","item":1}',0,CURRENT_TIMESTAMP),
(@admin_id,'reminder','Revisión de proyecto pendiente','Comprueba el segundo bloque de resultados del listado.','{"source":"pagination_demo","item":2}',0,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 HOUR)),
(@admin_id,'status_change','Proyecto actualizado','Un proyecto demostrativo cambió de estado.','{"source":"pagination_demo","item":3}',1,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 2 HOUR)),
(@admin_id,'repository','Proyecto publicado','Hay un nuevo proyecto disponible en el repositorio.','{"source":"pagination_demo","item":4}',0,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 3 HOUR)),
(@admin_id,'review','Revisión completada','La revisión académica fue registrada correctamente.','{"source":"pagination_demo","item":5}',1,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 4 HOUR)),
(@admin_id,'observation','Nueva observación','Se agregó una observación al documento demostrativo.','{"source":"pagination_demo","item":6}',0,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 5 HOUR)),
(@admin_id,'system','Catálogo actualizado','Los datos institucionales fueron sincronizados.','{"source":"pagination_demo","item":7}',1,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY)),
(@admin_id,'reminder','Actividad próxima','Existe una actividad académica programada.','{"source":"pagination_demo","item":8}',0,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 2 DAY)),
(@admin_id,'status_change','Estado aprobado','El proyecto demostrativo fue aprobado.','{"source":"pagination_demo","item":9}',1,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 3 DAY)),
(@admin_id,'repository','Documento disponible','El documento final ya puede consultarse.','{"source":"pagination_demo","item":10}',0,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 4 DAY)),
(@admin_id,'review','Comentarios registrados','La tutora agregó comentarios de revisión.','{"source":"pagination_demo","item":11}',0,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 5 DAY)),
(@admin_id,'system','Prueba de segunda página','Esta notificación permite verificar la navegación entre páginas.','{"source":"pagination_demo","item":12}',0,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 6 DAY));
