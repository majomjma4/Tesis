-- Unifica los códigos visibles por tipo, año de creación y secuencia.
UPDATE projects SET code=CONCAT('TMP-',id);

UPDATE projects p
INNER JOIN (
  SELECT p2.id,
    CONCAT(
      CASE pt.code
        WHEN 'thesis' THEN 'TIT'
        WHEN 'thesis_profile' THEN 'PFT'
        WHEN 'pis' THEN 'PIS'
        WHEN 'practice' THEN 'PRA'
        WHEN 'community' THEN 'VIN'
        ELSE 'PRY'
      END,
      '-',
      YEAR(p2.created_at),
      '-',
      LPAD(ROW_NUMBER() OVER (PARTITION BY p2.project_type_id,YEAR(p2.created_at) ORDER BY p2.id),3,'0')
    ) AS new_code
  FROM projects p2
  INNER JOIN project_types pt ON pt.id=p2.project_type_id
) numbered ON numbered.id=p.id
SET p.code=numbered.new_code;

INSERT INTO project_code_sequences (project_type_id,code_year,next_number)
SELECT project_type_id,YEAR(created_at),COUNT(*)+1
FROM projects
GROUP BY project_type_id,YEAR(created_at)
ON DUPLICATE KEY UPDATE next_number=VALUES(next_number);
