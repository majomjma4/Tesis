-- Unifica el flujo institucional y retira el estado legado "completed".
-- Las tesis pasan por tribunal; los demás tipos avanzan de aprobado a publicado.
UPDATE projects p
INNER JOIN project_types pt ON pt.id = p.project_type_id
SET p.status = CASE WHEN pt.code = 'thesis' THEN 'defense' ELSE 'published' END,
    p.current_stage = CASE WHEN pt.code = 'thesis' THEN 'defense' ELSE 'published' END,
    p.published_at = CASE WHEN pt.code <> 'thesis' THEN COALESCE(p.published_at, CURRENT_TIMESTAMP) ELSE p.published_at END
WHERE p.status = 'completed';
