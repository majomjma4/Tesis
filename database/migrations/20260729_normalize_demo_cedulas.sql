START TRANSACTION;

UPDATE teacher_profiles tp
INNER JOIN users u ON u.id=tp.user_id
SET tp.institutional_code=CASE u.email
    WHEN 'maribel.fierro.pendiente@local.invalid' THEN '0202053801'
    WHEN 'maria.navarrete.pendiente@local.invalid' THEN '0202053802'
    WHEN 'diana.alegria.pendiente@local.invalid' THEN '0202053803'
    WHEN 'diana.ramirez.pendiente@local.invalid' THEN '0202053804'
    WHEN 'alex.galarza.pendiente@local.invalid' THEN '0202053805'
    WHEN 'henrry.marino.pendiente@local.invalid' THEN '0202053806'
    ELSE tp.institutional_code
END
WHERE u.email IN (
    'maribel.fierro.pendiente@local.invalid',
    'maria.navarrete.pendiente@local.invalid',
    'diana.alegria.pendiente@local.invalid',
    'diana.ramirez.pendiente@local.invalid',
    'alex.galarza.pendiente@local.invalid',
    'henrry.marino.pendiente@local.invalid'
);

UPDATE student_profiles sp
INNER JOIN users u ON u.id=sp.user_id
SET sp.institutional_code=CASE u.email
    WHEN 'pruebas.estudiante@demo.local' THEN '0202053810'
    WHEN 'paginacion01@demo.local' THEN '0202053821'
    WHEN 'paginacion02@demo.local' THEN '0202053822'
    WHEN 'paginacion03@demo.local' THEN '0202053823'
    WHEN 'paginacion04@demo.local' THEN '0202053824'
    WHEN 'paginacion05@demo.local' THEN '0202053825'
    WHEN 'paginacion06@demo.local' THEN '0202053826'
    ELSE sp.institutional_code
END
WHERE u.email IN (
    'pruebas.estudiante@demo.local',
    'paginacion01@demo.local',
    'paginacion02@demo.local',
    'paginacion03@demo.local',
    'paginacion04@demo.local',
    'paginacion05@demo.local',
    'paginacion06@demo.local'
);

UPDATE student_profiles
SET institutional_code='0202053810'
WHERE institutional_code='EST-TEST-001';

COMMIT;
