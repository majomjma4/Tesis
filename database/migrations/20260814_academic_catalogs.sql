-- Catálogos administrables usados por Materiales de apoyo.
-- Reutiliza system_settings y no crea nuevas entidades de dominio.

INSERT IGNORE INTO system_settings(setting_key,setting_value)
VALUES
(
  'support_material_types',
  '[{"id":1,"name":"Normativa","is_active":true,"aliases":[]},{"id":2,"name":"Formato","is_active":true,"aliases":[]},{"id":3,"name":"Guía documental","is_active":true,"aliases":[]},{"id":4,"name":"Plantilla","is_active":true,"aliases":[]},{"id":5,"name":"Instructivo","is_active":true,"aliases":[]},{"id":6,"name":"Reglamento","is_active":true,"aliases":[]}]'
),
(
  'support_material_keywords',
  '[{"id":1,"name":"Tesis","is_active":true,"aliases":[]},{"id":2,"name":"Perfil de tesis","is_active":true,"aliases":[]},{"id":3,"name":"Titulación","is_active":true,"aliases":[]},{"id":4,"name":"Investigación","is_active":true,"aliases":[]},{"id":5,"name":"Metodología","is_active":true,"aliases":[]},{"id":6,"name":"Normativa","is_active":true,"aliases":[]},{"id":7,"name":"Reglamento","is_active":true,"aliases":[]},{"id":8,"name":"Formato","is_active":true,"aliases":[]},{"id":9,"name":"Plantilla","is_active":true,"aliases":[]},{"id":10,"name":"Guía documental","is_active":true,"aliases":[]},{"id":11,"name":"Vinculación","is_active":true,"aliases":[]},{"id":12,"name":"Proyecto PIS","is_active":true,"aliases":[]},{"id":13,"name":"Prácticas preprofesionales","is_active":true,"aliases":[]}]'
);
