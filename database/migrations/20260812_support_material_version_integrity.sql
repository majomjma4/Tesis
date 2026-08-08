-- Numera de forma persistente las versiones y conserva la huella SHA-256
-- de archivos vigentes e históricos.

ALTER TABLE support_material_files
  ADD COLUMN sha256 CHAR(64) NULL AFTER size_bytes;

ALTER TABLE support_material_file_versions
  ADD COLUMN version_number INT UNSIGNED NULL AFTER material_id,
  ADD COLUMN sha256 CHAR(64) NULL AFTER size_bytes;

CREATE TEMPORARY TABLE tmp_support_material_version_numbers (
  id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  version_number INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_support_material_version_numbers (id,version_number)
SELECT id,
       ROW_NUMBER() OVER (
         PARTITION BY file_id
         ORDER BY replaced_at,id
       ) AS version_number
FROM support_material_file_versions;

UPDATE support_material_file_versions version
JOIN tmp_support_material_version_numbers numbered ON numbered.id=version.id
SET version.version_number=numbered.version_number
WHERE version.version_number IS NULL;

DROP TEMPORARY TABLE tmp_support_material_version_numbers;

ALTER TABLE support_material_file_versions
  MODIFY version_number INT UNSIGNED NOT NULL,
  ADD CONSTRAINT chk_support_file_version_positive CHECK (version_number > 0),
  ADD UNIQUE INDEX uq_support_file_version_number (file_id,version_number);

DELIMITER $$
CREATE TRIGGER trg_support_file_version_number_immutable
BEFORE UPDATE ON support_material_file_versions
FOR EACH ROW
BEGIN
  IF NOT (NEW.version_number <=> OLD.version_number) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT='El número de una versión documental es inmutable';
  END IF;
END$$
DELIMITER ;
