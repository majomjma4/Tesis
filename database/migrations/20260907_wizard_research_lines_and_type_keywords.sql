-- Catálogos institucionales del wizard. Conserva IDs de líneas existentes para no romper proyectos históricos.
UPDATE research_lines
SET name = CONVERT(0x5465636E6F6C6F67C3AD6173206465206C6120496E666F726D616369C3B36E207920436F6D756E6963616369C3B36E USING utf8mb4)
WHERE id = 5 AND career_id = 1;

UPDATE research_lines
SET name = CONVERT(0x496E67656E696572C3AD6120646520536F667477617265 USING utf8mb4)
WHERE id = 6 AND career_id = 1;

CREATE TABLE IF NOT EXISTS project_type_keywords (
  project_type_id SMALLINT UNSIGNED NOT NULL,
  keyword_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (project_type_id, keyword_id),
  CONSTRAINT fk_project_type_keywords_type FOREIGN KEY (project_type_id) REFERENCES project_types(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_type_keywords_keyword FOREIGN KEY (keyword_id) REFERENCES keywords(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO keywords (name, normalized_name, is_active) VALUES
  (CONVERT(0x5072C3A16374696361732070726570726F666573696F6E616C6573 USING utf8mb4), CONVERT(0x7072C3A16374696361732070726570726F666573696F6E616C6573 USING utf8mb4), 1),
  (CONVERT(0x56696E63756C616369C3B36E USING utf8mb4), CONVERT(0x76696E63756C616369C3B36E USING utf8mb4), 1),
  ('Proyecto integrador de saberes', 'proyecto integrador de saberes', 1),
  ('Desarrollo de software', 'desarrollo de software', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1;

INSERT IGNORE INTO project_type_keywords (project_type_id, keyword_id)
SELECT pt.id, k.id FROM project_types pt JOIN keywords k
WHERE (pt.code IN ('thesis', 'thesis_profile') AND k.normalized_name IN ('perfil de tesis', 'titulación', 'investigación'))
   OR (pt.code = 'practice' AND k.normalized_name IN ('prácticas preprofesionales', 'desarrollo de software'))
   OR (pt.code = 'community' AND k.normalized_name IN ('vinculación'))
   OR (pt.code = 'pis' AND k.normalized_name IN ('proyecto integrador de saberes', 'desarrollo de software'));
