-- Separa Titulación y Perfil de tesis como tipos de proyecto independientes.
UPDATE project_types
SET name = 'Titulación', is_active = 1
WHERE code = 'thesis';

INSERT INTO project_types (code, name, is_active)
VALUES ('thesis_profile', 'Perfil de tesis', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1;
