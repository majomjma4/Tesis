ALTER TABLE project_types
    ADD COLUMN IF NOT EXISTS registration_description TEXT NULL AFTER name;

UPDATE project_types
SET registration_description = CASE code
    WHEN 'practice' THEN 'Desarrollo de prácticas preprofesionales orientadas a fortalecer competencias profesionales mediante actividades planificadas, supervisadas y vinculadas con el perfil de formación.'
    WHEN 'community' THEN 'Desarrollo de un proyecto de vinculación orientado a responder necesidades de la comunidad mediante actividades planificadas, participativas y relacionadas con la formación académica.'
    ELSE registration_description
END
WHERE code IN ('practice', 'community')
  AND registration_description IS NULL;
