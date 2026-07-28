-- Separa el archivo de presentación del documento destacado (is_primary).
-- presentation_file_id define exclusivamente la vista inicial del expediente.

ALTER TABLE support_materials
    MODIFY status ENUM('draft','published','withdrawn') NOT NULL DEFAULT 'draft',
    ADD COLUMN presentation_file_id BIGINT UNSIGNED NULL AFTER status;

ALTER TABLE projects
    ADD COLUMN presentation_file_id BIGINT UNSIGNED NULL AFTER published_at;

UPDATE support_materials material
SET presentation_file_id = (
    SELECT file.id
    FROM support_material_files file
    WHERE file.material_id = material.id
      AND file.deleted_at IS NULL
      AND file.is_package = 0
      AND LOWER(file.extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
    ORDER BY file.is_primary DESC, file.sort_order, file.id
    LIMIT 1
)
WHERE material.presentation_file_id IS NULL;

UPDATE projects project
SET presentation_file_id = (
    SELECT file.id
    FROM project_files file
    WHERE file.project_id = project.id
      AND file.deleted_at IS NULL
      AND LOWER(file.extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
    ORDER BY file.id
    LIMIT 1
)
WHERE project.presentation_file_id IS NULL;

-- Los registros demostrativos publicados sin documentos reales vuelven al
-- último estado académico válido; no deben fingir una publicación incompleta.
UPDATE projects project
JOIN project_types type ON type.id = project.project_type_id
SET project.status = IF(type.code = 'thesis', 'tribunal_approved', 'approved'),
    project.published_at = NULL
WHERE project.status = 'published'
  AND project.presentation_file_id IS NULL;

ALTER TABLE support_materials
    ADD KEY idx_support_material_presentation (presentation_file_id),
    ADD CONSTRAINT fk_support_material_presentation
        FOREIGN KEY (presentation_file_id) REFERENCES support_material_files(id),
    ADD CONSTRAINT chk_support_material_published_presentation
        CHECK (status <> 'published' OR presentation_file_id IS NOT NULL);

ALTER TABLE projects
    ADD KEY idx_project_presentation (presentation_file_id),
    ADD CONSTRAINT fk_project_presentation
        FOREIGN KEY (presentation_file_id) REFERENCES project_files(id),
    ADD CONSTRAINT chk_project_published_presentation
        CHECK (status <> 'published' OR presentation_file_id IS NOT NULL);

DELIMITER $$

CREATE TRIGGER trg_support_material_presentation_insert
BEFORE INSERT ON support_materials
FOR EACH ROW
BEGIN
    IF NEW.presentation_file_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
        FROM support_material_files file
        WHERE file.id = NEW.presentation_file_id
          AND file.material_id = NEW.id
          AND file.deleted_at IS NULL
          AND file.is_package = 0
          AND LOWER(file.extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El archivo de presentación del material no es válido';
    END IF;
END$$

CREATE TRIGGER trg_support_material_presentation_update
BEFORE UPDATE ON support_materials
FOR EACH ROW
BEGIN
    IF NEW.presentation_file_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
        FROM support_material_files file
        WHERE file.id = NEW.presentation_file_id
          AND file.material_id = NEW.id
          AND file.deleted_at IS NULL
          AND file.is_package = 0
          AND LOWER(file.extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El archivo de presentación del material no es válido';
    END IF;
END$$

CREATE TRIGGER trg_project_presentation_update
BEFORE UPDATE ON projects
FOR EACH ROW
BEGIN
    IF NEW.presentation_file_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
        FROM project_files file
        WHERE file.id = NEW.presentation_file_id
          AND file.project_id = NEW.id
          AND file.deleted_at IS NULL
          AND LOWER(file.extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El archivo de presentación del proyecto no es válido';
    END IF;
END$$

CREATE TRIGGER trg_support_presentation_file_retire
BEFORE UPDATE ON support_material_files
FOR EACH ROW
BEGIN
    IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL AND EXISTS (
        SELECT 1
        FROM support_materials material
        WHERE material.presentation_file_id = OLD.id
          AND material.status = 'published'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Selecciona otro archivo de presentación antes de retirar el actual';
    END IF;
END$$

CREATE TRIGGER trg_project_presentation_file_retire
BEFORE UPDATE ON project_files
FOR EACH ROW
BEGIN
    IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL AND EXISTS (
        SELECT 1
        FROM projects project
        WHERE project.presentation_file_id = OLD.id
          AND project.status = 'published'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Selecciona otro archivo de presentación antes de retirar el actual';
    END IF;
END$$

DELIMITER ;
