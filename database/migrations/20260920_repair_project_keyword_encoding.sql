-- Corrige filas creadas por la migración 20260919 cuando el cliente MariaDB
-- interpretó el archivo UTF-8 con una conexión latin1. No elimina registros.

UPDATE keywords SET
  name = CONVERT(0x496E766573746967616369C3B36E USING utf8mb4),
  normalized_name = CONVERT(0x696E766573746967616369C3B36E USING utf8mb4)
WHERE id > 4 AND name LIKE 'Investigaci%' AND normalized_name LIKE 'investigaci%';

UPDATE keywords SET
  name = CONVERT(0x496E6E6F76616369C3B36E USING utf8mb4),
  normalized_name = CONVERT(0x696E6E6F76616369C3B36E USING utf8mb4)
WHERE id > 4 AND name LIKE 'Innovaci%' AND normalized_name LIKE 'innovaci%';

UPDATE keywords SET
  name = CONVERT(0x4D65746F646F6C6F67C3AD61 USING utf8mb4),
  normalized_name = CONVERT(0x6D65746F646F6C6F67C3AD61 USING utf8mb4)
WHERE id > 4 AND name LIKE 'Metodolog%' AND normalized_name LIKE 'metodolog%';

UPDATE keywords SET
  name = CONVERT(0x50726F707565737461207465636E6F6CC3B367696361 USING utf8mb4),
  normalized_name = CONVERT(0x70726F707565737461207465636E6F6CC3B367696361 USING utf8mb4)
WHERE id > 4 AND name LIKE 'Propuesta tecnol%' AND normalized_name LIKE 'propuesta tecnol%';

UPDATE keywords SET
  name = CONVERT(0x41706C6963616369C3B36E207072C3A16374696361 USING utf8mb4),
  normalized_name = CONVERT(0x61706C6963616369C3B36E207072C3A16374696361 USING utf8mb4)
WHERE id > 4 AND name LIKE 'Aplicaci%' AND normalized_name LIKE 'aplicaci%';

UPDATE keywords SET
  name = CONVERT(0x4765737469C3B36E20696E737469747563696F6E616C USING utf8mb4),
  normalized_name = CONVERT(0x6765737469C3B36E20696E737469747563696F6E616C USING utf8mb4)
WHERE id > 4 AND name LIKE 'Gesti%' AND normalized_name LIKE 'gesti%';

UPDATE keywords SET
  name = CONVERT(0x56696E63756C616369C3B36E20636F6D756E697461726961 USING utf8mb4),
  normalized_name = CONVERT(0x76696E63756C616369C3B36E20636F6D756E697461726961 USING utf8mb4)
WHERE id > 4 AND name LIKE 'Vinculaci%' AND normalized_name LIKE 'vinculaci%';

UPDATE keywords k
SET k.is_active = 0
WHERE BINARY k.normalized_name IN (
    BINARY CONVERT(0x7072C3A16374696361732070726570726F666573696F6E616C6573 USING utf8mb4),
    BINARY CONVERT(0x76696E63756C616369C3B36E USING utf8mb4),
    BINARY 'proyecto integrador de saberes'
)
AND NOT EXISTS (
    SELECT 1
    FROM project_keywords pk
    WHERE pk.keyword_id = k.id
);
