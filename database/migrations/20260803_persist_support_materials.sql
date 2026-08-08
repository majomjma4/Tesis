-- Material de apoyo persistente para el repositorio institucional.
CREATE TABLE IF NOT EXISTS support_material_categories (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_materials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id SMALLINT UNSIGNED NOT NULL,
  academic_period_id SMALLINT UNSIGNED NULL,
  title VARCHAR(220) NOT NULL,
  material_type VARCHAR(100) NOT NULL,
  description VARCHAR(500) NOT NULL,
  full_description TEXT NOT NULL,
  publisher VARCHAR(180) NOT NULL,
  publication_date DATE NOT NULL,
  status ENUM('published','withdrawn') NOT NULL DEFAULT 'published',
  download_count INT UNSIGNED NOT NULL DEFAULT 0,
  keywords_json LONGTEXT NULL,
  withdrawn_at DATETIME NULL,
  withdrawn_by BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_support_material_category FOREIGN KEY (category_id) REFERENCES support_material_categories(id),
  CONSTRAINT fk_support_material_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id),
  CONSTRAINT fk_support_material_withdrawn_by FOREIGN KEY (withdrawn_by) REFERENCES users(id),
  CONSTRAINT fk_support_material_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_support_material_updated_by FOREIGN KEY (updated_by) REFERENCES users(id),
  INDEX idx_support_material_status_date (status, publication_date),
  INDEX idx_support_material_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_material_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  material_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_name VARCHAR(255) NOT NULL,
  relative_path VARCHAR(500) NOT NULL,
  extension VARCHAR(15) NOT NULL,
  mime_type VARCHAR(150) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  is_package TINYINT(1) NOT NULL DEFAULT 0,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  CONSTRAINT fk_support_file_material FOREIGN KEY (material_id) REFERENCES support_materials(id),
  CONSTRAINT fk_support_file_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_support_file_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id),
  UNIQUE KEY uq_support_material_path (material_id, relative_path),
  INDEX idx_support_file_material_active (material_id, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO support_material_categories (id,slug,name) VALUES
  (1,'tesis','Tesis'),
  (2,'practicas','Prácticas'),
  (3,'proyecto-pis','Proyectos PIS'),
  (4,'vinculacion','Vinculación')
ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1;

INSERT INTO support_materials
  (id,category_id,academic_period_id,title,material_type,description,full_description,publisher,publication_date,status,download_count,keywords_json)
SELECT 1,1,(SELECT id FROM academic_periods WHERE name='I PAO 2026' LIMIT 1),
  'Guía para la elaboración del perfil de tesis','Guía',
  'Orientaciones para estructurar correctamente el perfil y preparar el proceso de titulación.',
  'Esta guía reúne los criterios institucionales para elaborar el perfil de tesis.\n\nIncluye recomendaciones para delimitar el tema, formular objetivos, organizar antecedentes y presentar la propuesta académica.',
  'Instituto Superior Tecnológico "El Libertador"','2026-07-08','published',86,
  '["Perfil de tesis","Titulación","Metodología"]'
WHERE NOT EXISTS (SELECT 1 FROM support_materials WHERE id=1);

INSERT INTO support_materials
  (id,category_id,academic_period_id,title,material_type,description,full_description,publisher,publication_date,status,download_count,keywords_json)
SELECT 2,2,(SELECT id FROM academic_periods WHERE name='I PAO 2026' LIMIT 1),
  'Formato de seguimiento de prácticas preprofesionales','Formato',
  'Formato institucional para registrar actividades, horas cumplidas y evidencias de prácticas.',
  'Documento editable destinado al seguimiento periódico de las prácticas preprofesionales.\n\nPermite registrar actividades, resultados, evidencias y validaciones del responsable institucional.',
  'Instituto Superior Tecnológico "El Libertador"','2026-06-20','published',63,
  '["Prácticas","Seguimiento","Evidencias"]'
WHERE NOT EXISTS (SELECT 1 FROM support_materials WHERE id=2);

INSERT INTO support_materials
  (id,category_id,academic_period_id,title,material_type,description,full_description,publisher,publication_date,status,download_count,keywords_json)
SELECT 3,3,(SELECT id FROM academic_periods WHERE status='active' ORDER BY id DESC LIMIT 1),'Instructivo para proyectos PIS','Instructivo',
  'Pasos y criterios para organizar entregables, evidencias y presentación de proyectos integradores.',
  'Este instructivo explica el flujo recomendado para desarrollar proyectos PIS.\n\nDetalla la organización de equipos, entregables mínimos, evidencias y criterios generales de presentación.',
  'Instituto Superior Tecnológico "El Libertador"','2025-12-12','published',49,
  '["PIS","Entregables","Proyectos"]'
WHERE NOT EXISTS (SELECT 1 FROM support_materials WHERE id=3);

INSERT INTO support_materials
  (id,category_id,academic_period_id,title,material_type,description,full_description,publisher,publication_date,status,download_count,keywords_json)
SELECT 4,4,(SELECT id FROM academic_periods WHERE status='active' ORDER BY id DESC LIMIT 1),'Formato de informe de vinculación','Plantilla',
  'Plantilla editable para documentar actividades, beneficiarios, resultados e impacto comunitario.',
  'Plantilla institucional para presentar el informe de las actividades de vinculación.\n\nOrganiza objetivos, participantes, resultados, evidencias e indicadores de impacto comunitario.',
  'Instituto Superior Tecnológico "El Libertador"','2025-11-30','published',38,
  '["Vinculación","Informe","Impacto"]'
WHERE NOT EXISTS (SELECT 1 FROM support_materials WHERE id=4);

INSERT INTO support_materials
  (id,category_id,academic_period_id,title,material_type,description,full_description,publisher,publication_date,status,download_count,keywords_json)
SELECT 5,1,(SELECT id FROM academic_periods WHERE status='active' ORDER BY id DESC LIMIT 1),'Reglamento de uso del material académico','Reglamento',
  'Disposiciones generales para consultar y utilizar responsablemente los recursos institucionales.',
  'Documento informativo sobre el uso responsable del material académico institucional.\n\nResume las condiciones de consulta, atribución y distribución de los recursos disponibles.',
  'Instituto Superior Tecnológico "El Libertador"','2025-05-14','published',21,
  '["Reglamento","Recursos","Uso académico"]'
WHERE NOT EXISTS (SELECT 1 FROM support_materials WHERE id=5);

-- Conserva los datos iniciales legibles aun si la migración se vuelve a ejecutar.
UPDATE support_materials SET
  title='Guía para la elaboración del perfil de tesis',
  material_type='Guía',
  description='Orientaciones para estructurar correctamente el perfil y preparar el proceso de titulación.',
  full_description='Esta guía reúne los criterios institucionales para elaborar el perfil de tesis.\n\nIncluye recomendaciones para delimitar el tema, formular objetivos, organizar antecedentes y presentar la propuesta académica.',
  keywords_json='["Perfil de tesis","Titulación","Metodología"]'
WHERE id=1;
UPDATE support_materials SET
  title='Formato de seguimiento de prácticas preprofesionales',
  description='Formato institucional para registrar actividades, horas cumplidas y evidencias de prácticas.',
  full_description='Documento editable destinado al seguimiento periódico de las prácticas preprofesionales.\n\nPermite registrar actividades, resultados, evidencias y validaciones del responsable institucional.',
  keywords_json='["Prácticas","Seguimiento","Evidencias"]'
WHERE id=2;
UPDATE support_materials SET
  title='Instructivo para proyectos PIS',
  description='Pasos y criterios para organizar entregables, evidencias y presentación de proyectos integradores.',
  full_description='Este instructivo explica el flujo recomendado para desarrollar proyectos PIS.\n\nDetalla la organización de equipos, entregables mínimos, evidencias y criterios generales de presentación.'
WHERE id=3;
UPDATE support_materials SET
  title='Formato de informe de vinculación',
  description='Plantilla editable para documentar actividades, beneficiarios, resultados e impacto comunitario.',
  full_description='Plantilla institucional para presentar el informe de las actividades de vinculación.\n\nOrganiza objetivos, participantes, resultados, evidencias e indicadores de impacto comunitario.',
  keywords_json='["Vinculación","Informe","Impacto"]'
WHERE id=4;
UPDATE support_materials SET
  title='Reglamento de uso del material académico',
  description='Disposiciones generales para consultar y utilizar responsablemente los recursos institucionales.',
  full_description='Documento informativo sobre el uso responsable del material académico institucional.\n\nResume las condiciones de consulta, atribución y distribución de los recursos disponibles.',
  keywords_json='["Reglamento","Recursos","Uso académico"]'
WHERE id=5;
UPDATE support_materials
SET publisher='Instituto Superior Tecnológico "El Libertador"'
WHERE id BETWEEN 1 AND 5;

INSERT INTO support_material_files
  (id,material_id,original_name,storage_name,relative_path,extension,mime_type,size_bytes,is_primary,is_package,sort_order)
VALUES
  (1,1,'guia_perfil_tesis.pdf','guia_perfil_tesis.pdf','guia_perfil_tesis.pdf','pdf','application/pdf',689,1,0,1),
  (2,1,'lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt','lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt','lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt','txt','text/plain',87,0,0,2),
  (3,1,'material_tesis_completo.zip','material_tesis_completo.zip','material_tesis_completo.zip','zip','application/zip',777,0,1,3),
  (4,2,'seguimiento_practicas.docx','seguimiento_practicas.docx','seguimiento_practicas.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',1029,1,0,1),
  (5,3,'instructivo_proyectos_pis.pdf','instructivo_proyectos_pis.pdf','instructivo_proyectos_pis.pdf','pdf','application/pdf',688,1,0,1),
  (6,4,'informe_vinculacion.docx','informe_vinculacion.docx','informe_vinculacion.docx','docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',1023,1,0,1),
  (7,5,'reglamento_material_apoyo.txt','reglamento_material_apoyo.txt','reglamento_material_apoyo.txt','txt','text/plain',110,1,0,1)
ON DUPLICATE KEY UPDATE original_name=VALUES(original_name);
