<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$options = getopt('', ['database:', 'db-host::', 'db-port::', 'db-user::', 'db-password::', 'migrate']);
$databaseName = trim((string) ($options['database'] ?? ''));
if ($databaseName === '' || strcasecmp($databaseName, 'tesis') === 0 || !preg_match('/^tesis_qa_[a-z0-9_]+$/i', $databaseName)) {
    fwrite(STDERR, "Uso seguro: php scripts/test_project_adjustment_requests.php --database=tesis_qa_<nombre>\n");
    exit(2);
}
connectToIsolatedDatabase($options);

$db = Database::connection();
if (in_array('--migrate', $argv, true)) {
    $sql = file_get_contents(ROOT_PATH . '/database/migrations/20260820_project_adjustment_requests.sql');
    foreach (array_filter(array_map('trim', explode(';', (string)$sql))) as $statement) $db->exec($statement);
}
if (!(bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='project_adjustment_requests'")->fetchColumn()) {
    fwrite(STDERR, "Falta ejecutar la migración 20260820_project_adjustment_requests.sql.\n"); exit(2);
}

$fixture = $db->query(
    "SELECT p.id project_id,p.status,u.id admin_id,pp.user_id student_id
     FROM projects p
     INNER JOIN users u ON u.is_admin=1 AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
     INNER JOIN project_participants pp ON pp.project_id=p.id AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL
     INNER JOIN user_roles ur ON ur.user_id=pp.user_id INNER JOIN roles r ON r.id=ur.role_id AND r.code='student'
      WHERE p.deleted_at IS NULL AND p.publication_origin='workflow' AND p.status IN ('development','under_review','approved','defense','tribunal_approved')
      ORDER BY CASE WHEN p.status IN ('approved','defense','tribunal_approved') THEN 0 ELSE 1 END,p.id LIMIT 1"
)->fetch();
if (!$fixture) { fwrite(STDERR, "No hay una fixture administrativa/estudiantil apta.\n"); exit(2); }

$service = new ProjectAdjustmentRequestService();
$project=(int)$fixture['project_id'];$admin=(int)$fixture['admin_id'];$student=(int)$fixture['student_id'];$status=(string)$fixture['status'];
$sessionDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tesis-adjustment-qa-' . bin2hex(random_bytes(8));
if (!mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) throw new RuntimeException('No fue posible preparar la sesión QA.');
ini_set('session.save_path', $sessionDirectory);
$session = new AuthSessionService();
$session->start();
$_SESSION['user_id'] = $admin;
$_SESSION['roles'] = ['administrator'];
$_SESSION['role'] = 'administrator';
$_SESSION['is_admin'] = true;
$_SESSION['admin_mode'] = true;
$assert = static function(bool $condition,string $label): void { if(!$condition)throw new RuntimeException('FALLÓ: '.$label);echo "OK  $label\n"; };
$before = [
    'requests'=>(int)$db->query('SELECT COUNT(*) FROM project_adjustment_requests')->fetchColumn(),
    'responses'=>(int)$db->query('SELECT COUNT(*) FROM project_adjustment_request_responses')->fetchColumn(),
    'audit'=>(int)$db->query("SELECT COUNT(*) FROM project_audit_log WHERE action LIKE 'project_adjustment_request_%'")->fetchColumn(),
    'notifications'=>(int)$db->query("SELECT COUNT(*) FROM notifications WHERE type='adjustment'")->fetchColumn(),
];

$db->beginTransaction();
try {
    $suffix=bin2hex(random_bytes(5));
    $newUser=$db->prepare("INSERT INTO users(email,password_hash,full_name,status) VALUES(:email,'test-only','Usuario ajeno de prueba','active')");
    $newUser->execute(['email'=>'ajeno-'.$suffix.'@example.invalid']);$outsider=(int)$db->lastInsertId();
    $role=$db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT :user,id FROM roles WHERE code='student'");$role->execute(['user'=>$outsider]);
    try{$service->listForProject($project,$status,$outsider,'academic');$assert(false,'usuario ajeno consulta');}
    catch(ProjectAdjustmentRequestException $e){$assert($e->httpStatus()===403,'usuario ajeno sin acceso');}
    $newUser->execute(['email'=>'tribunal-'.$suffix.'@example.invalid']);$tribunal=(int)$db->lastInsertId();
    $role=$db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT :user,id FROM roles WHERE code='teacher'");$role->execute(['user'=>$tribunal]);
    $member=$db->prepare("INSERT INTO project_participants(project_id,user_id,role_code,permission_level,status) VALUES(:project,:user,'tribunal','review','active')");$member->execute(['project'=>$project,'user'=>$tribunal]);
    try{$service->listForProject($project,$status,$tribunal,'academic');$assert(false,'tribunal consulta');}
    catch(ProjectAdjustmentRequestException $e){$assert($e->httpStatus()===403,'tribunal sin acceso');}
    $created=$service->createInTransaction($db,$project,$status,$admin,'academic_management',['request_type'=>'inconsistency','related_section'=>'Información académica','related_field'=>'Valor ignorado','message'=>'Corregir la información administrativa de prueba.']);
    $id=(int)$created['request']['id'];$version=(int)$created['request']['lock_version'];
    $assert($created['request']['status']==='pending','creación pendiente');
    $assert($created['summary']['pending_count']>=1,'resumen agregado');
    $assert(($created['request']['related_section']??null)==='Información académica'&&($created['request']['related_field']??null)===null,'sección guardada sin campo específico');
    $dedup='adjustment:'.$id.':'.$student;
    $q=$db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:user AND deduplication_key=:dedup');$q->execute(['user'=>$student,'dedup'=>$dedup]);
    $assert((int)$q->fetchColumn()===1,'notificación consolidada y deduplicada');
    $responded=$service->respondInTransaction($db,$project,$id,$version,$status,$student,'academic','Respuesta administrativa de prueba.');$version=(int)$responded['request']['lock_version'];
    $assert($version===2,'respuesta y versión optimista');
    $addressed=$service->transitionInTransaction($db,$project,$id,$version,$status,$student,'academic','addressed');$version=(int)$addressed['request']['lock_version'];
    $assert($addressed['request']['status']==='addressed','marcado atendido');
    $closed=$service->transitionInTransaction($db,$project,$id,$version,$status,$admin,'academic_management','closed');
    $assert($closed['request']['status']==='closed','cierre administrativo');
    try{$service->transitionInTransaction($db,$project,$id,(int)$closed['request']['lock_version'],$status,$admin,'academic_management','closed');$assert(false,'doble cierre');}
    catch(ProjectAdjustmentRequestException $e){$assert($e->httpStatus()===409,'doble cierre rechazado');}
    try{$service->respondInTransaction($db,$project,$id,1,$status,$student,'academic','Conflicto');$assert(false,'versión obsoleta');}
    catch(ProjectAdjustmentRequestException $e){$assert($e->httpStatus()===409,'conflicto lock_version');}
    $studentCapabilityCheck = (new ProjectCapabilityService())->adjustmentCapabilitiesInTransaction($db, ['id'=>$project,'status'=>$status,'publication_origin'=>'workflow','deleted_at'=>null,'withdrawn_at'=>null], $student, 'academic');
    $assert(!empty($studentCapabilityCheck['create_adjustment_request']), 'estudiante recibe solicitud controlada cuando no puede editar directamente');
    try{$service->createInTransaction($db,$project,$status,$student,'academic',['request_type'=>'other','message'=>'No autorizado']);$assert(false,'estudiante crea');}
    catch(ProjectAdjustmentRequestException $e){$assert($e->httpStatus()===422,'tipo de solicitud estudiantil (HTTP '.$e->httpStatus().')');}
    try{$service->createInTransaction($db,PHP_INT_MAX,$status,$admin,'academic_management',['request_type'=>'other','message'=>'Proyecto inexistente']);$assert(false,'proyecto inexistente');}
    catch(ProjectAdjustmentRequestException $e){$assert($e->httpStatus()===404,'proyecto inexistente rechazado');}
    try{$service->createInTransaction($db,$project,'estado-obsoleto',$admin,'academic_management',['request_type'=>'other','message'=>'Estado obsoleto']);$assert(false,'estado esperado');}
    catch(ProjectAdjustmentRequestException $e){$assert($e->httpStatus()===409,'conflicto de estado del proyecto');}
    $assert((int)$db->query("SELECT COUNT(*) FROM project_audit_log WHERE entity_type='project_adjustment_request' AND entity_id=$id")->fetchColumn()===4,'auditoría técnica');
} finally { if($db->inTransaction())$db->rollBack(); }

$after = [
    'requests'=>(int)$db->query('SELECT COUNT(*) FROM project_adjustment_requests')->fetchColumn(),
    'responses'=>(int)$db->query('SELECT COUNT(*) FROM project_adjustment_request_responses')->fetchColumn(),
    'audit'=>(int)$db->query("SELECT COUNT(*) FROM project_audit_log WHERE action LIKE 'project_adjustment_request_%'")->fetchColumn(),
    'notifications'=>(int)$db->query("SELECT COUNT(*) FROM notifications WHERE type='adjustment'")->fetchColumn(),
];
$assert($before===$after,'rollback sin residuos');
$schema=$db->query("SELECT table_name,engine,table_collation FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('project_adjustment_requests','project_adjustment_request_responses') ORDER BY table_name")->fetchAll();
$assert(count($schema)===2 && count(array_filter($schema,static fn(array $row): bool => $row['engine']==='InnoDB' && str_starts_with((string)$row['table_collation'],'utf8mb4_')))===2,'esquema InnoDB y UTF-8');
$notificationType=(string)$db->query("SELECT column_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notifications' AND column_name='type'")->fetchColumn();
$assert(str_contains($notificationType,"'adjustment'"),'tipo de notificación adjustment instalado');

$db->beginTransaction();
try {
    $service->createInTransaction($db,$project,$status,$admin,'academic_management',['request_type'=>'other','message'=>'Prueba de rollback forzado.']);
    throw new RuntimeException('fallo simulado');
} catch(RuntimeException $e) { $db->rollBack(); $assert($e->getMessage()==='fallo simulado','rollback ante error posterior'); }

$decisionFixture = $db->query(
    "SELECT p.id project_id,pp.user_id student_id
     FROM projects p
     INNER JOIN project_participants pp ON pp.project_id=p.id AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL
     INNER JOIN student_profiles sp ON sp.user_id=pp.user_id
     INNER JOIN users u ON u.id=pp.user_id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
     WHERE p.status='published' AND p.is_available=1 AND p.deleted_at IS NULL AND p.withdrawn_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM project_adjustment_requests pending WHERE pending.project_id=p.id AND pending.requested_by=pp.user_id AND pending.status='pending')
     ORDER BY p.id LIMIT 1"
)->fetch();
if ($decisionFixture) {
    $decisionProject=(int)$decisionFixture['project_id']; $decisionStudent=(int)$decisionFixture['student_id'];
    foreach (['approved','rejected'] as $decision) {
        $db->beginTransaction();
        try {
            $created=$service->createInTransaction($db,$decisionProject,'published',$decisionStudent,'repository',['request_type'=>'published_modification','message'=>'Solicitud transaccional de modificacion publicada.']);
            $requestId=(int)$created['request']['id']; $requestVersion=(int)$created['request']['lock_version'];
            $resolved=$decision==='approved'
                ? $service->approveInTransaction($db,$decisionProject,$requestId,$requestVersion,'published',$admin,'academic_management')
                : $service->rejectInTransaction($db,$decisionProject,$requestId,$requestVersion,'published',$admin,'academic_management','Motivo de rechazo de prueba.');
            $assert(($resolved['decision']??'')===$decision,"decision administrativa $decision");
            $state=$db->query("SELECT status,is_available FROM projects WHERE id=$decisionProject")->fetch();
            $assert($decision==='approved'
                ? (($state['status']??'')==='development' && (int)$state['is_available']===0)
                : (($state['status']??'')==='published' && (int)$state['is_available']===1),"estado del proyecto tras $decision");
            $requestState=$db->query("SELECT status FROM project_adjustment_requests WHERE id=$requestId")->fetchColumn();
            $assert($requestState===($decision==='approved'?'addressed':'closed'),"estado persistido de solicitud $decision");
            if ($decision==='rejected') {
                $persistedReason=$db->prepare('SELECT rejection_reason FROM project_adjustment_requests WHERE id=:id');
                $persistedReason->execute(['id'=>$requestId]);
                $assert($persistedReason->fetchColumn()==='Motivo de rechazo de prueba.','motivo de rechazo persistido');
            }
            $auditAction='project_adjustment_request_'.$decision;
            $assert((int)$db->query("SELECT COUNT(*) FROM project_audit_log WHERE entity_type='project_adjustment_request' AND entity_id=$requestId AND action='$auditAction'")->fetchColumn()===1,"auditoria de decision $decision");
            $timelineTypes=array_column((new ProjectAcademicTimelineService($db))->page($decisionProject,0,100)['events'],'event_type');
            $assert(in_array($decision==='approved'?'adjustment_approved':'adjustment_rejected',$timelineTypes,true),"historial de decision $decision");
            if ($decision==='approved') {
                $assert((int)$db->query("SELECT COUNT(*) FROM project_audit_log WHERE project_id=$decisionProject AND action='project_reopened_for_adjustment'")->fetchColumn()===1,'reapertura auditada');
                $reopenAudit = (int) $db->query("SELECT id FROM project_audit_log WHERE project_id=$decisionProject AND action='project_reopened_for_adjustment' ORDER BY id DESC LIMIT 1")->fetchColumn();
                (new ProjectAcademicNotificationService())->postPublicationModification($db,$decisionProject,$decisionStudent,$reopenAudit,'qa_post_publication');
                $adminNotification = $db->prepare("SELECT COUNT(*) FROM notifications WHERE project_id=:project AND type='adjustment' AND JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.event'))='post_publication_modification'");
                $adminNotification->execute(['project'=>$decisionProject]);
                $assert((int)$adminNotification->fetchColumn()>=1,'notificación administrativa de modificación posterior a publicación');
            } else {
                $second=$service->createInTransaction($db,$decisionProject,'published',$decisionStudent,'repository',['request_type'=>'published_modification','message'=>'Nueva solicitud despues de una resolucion.']);
                $assert((int)$second['request']['id']>$requestId,'nueva solicitud despues de rechazo');
            }
        } finally { if($db->inTransaction())$db->rollBack(); }
    }
} else {
    echo "SKIP decisiones administrativas: no hay una fixture publicada sin solicitud pendiente.\n";
}

$setSession = static function (int $userId, array $roles, bool $isAdmin = false, bool $adminMode = false): void {
    $_SESSION['user_id'] = $userId;
    $_SESSION['roles'] = $roles;
    $_SESSION['role'] = (string) ($roles[0] ?? 'student');
    $_SESSION['is_admin'] = $isAdmin;
    $_SESSION['admin_mode'] = $adminMode;
};
$policy = new ProjectCapabilityService();
$developmentFixture = $db->query(
    "SELECT p.id,p.code,p.created_by,p.tutor_id,p.status,p.publication_origin,p.academic_period_id,p.is_available,p.published_at,p.deleted_at,p.withdrawn_at,
            (SELECT COUNT(*) FROM project_files f WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL) active_file_count
     FROM projects p JOIN academic_periods ap ON ap.id=p.academic_period_id
     WHERE p.publication_origin='workflow' AND p.status='development' AND ap.status='active'
       AND ap.starts_on <= UTC_DATE() AND ap.ends_on >= UTC_DATE()
       AND EXISTS (SELECT 1 FROM project_participants pp WHERE pp.project_id=p.id AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL)
     ORDER BY p.id LIMIT 1"
)->fetch();
if ($developmentFixture) {
    $teacherFixture = $db->query(
        "SELECT u.id FROM users u
         INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher'
         WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND u.is_admin=0
           AND NOT EXISTS (SELECT 1 FROM project_participants pp WHERE pp.project_id=" . (int) $developmentFixture['id'] . " AND pp.user_id=u.id AND pp.status='active' AND pp.removed_at IS NULL)
         ORDER BY u.id LIMIT 1"
    )->fetchColumn();
    if ($teacherFixture) {
        $setSession((int) $teacherFixture, ['teacher']);
        $teacherCaps = $policy->adjustmentCapabilitiesInTransaction($db, $developmentFixture, (int) $teacherFixture, 'academic');
        $assert(!empty($teacherCaps['create_adjustment_request']), 'docente no asignado con acceso institucional puede solicitar cambios');
        $db->beginTransaction();
        try {
            $created = $service->createInTransaction($db, (int) $developmentFixture['id'], 'development', (int) $teacherFixture, 'academic', ['request_type'=>'other','message'=>'Solicitud de cambios de prueba para docente con acceso.']);
            try {
                $service->createInTransaction($db, (int) $developmentFixture['id'], 'development', (int) $teacherFixture, 'academic', ['request_type'=>'other','message'=>'Segundo envío equivalente de prueba.']);
                $assert(false, 'duplicado equivalente rechazado');
            } catch (ProjectAdjustmentRequestException $e) {
                $assert($e->httpStatus() === 409, 'duplicado equivalente rechazado');
            }
        } finally { if ($db->inTransaction()) $db->rollBack(); }
    } else {
        echo "SKIP docente no asignado: no hay docente QA disponible.\n";
    }

    $studentId = (int) $db->query("SELECT pp.user_id FROM project_participants pp INNER JOIN student_profiles sp ON sp.user_id=pp.user_id WHERE pp.project_id=" . (int) $developmentFixture['id'] . " AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL ORDER BY pp.user_id LIMIT 1")->fetchColumn();
    $setSession($studentId, ['student']);
    $tampered = $policy->adjustmentCapabilitiesInTransaction($db, $developmentFixture, $studentId, 'academic_management');
    $assert(empty($tampered['approve_adjustment_request']), 'contexto administrativo manipulado no concede aprobación');

    $publishedFixture = $db->query(
        "SELECT p.id,p.code,p.created_by,p.tutor_id,p.status,p.publication_origin,p.academic_period_id,p.is_available,p.published_at,p.deleted_at,p.withdrawn_at,
                (SELECT COUNT(*) FROM project_files f WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL) active_file_count
         FROM projects p
         WHERE p.publication_origin='workflow' AND p.status='published' AND p.is_available=1 AND p.deleted_at IS NULL AND p.withdrawn_at IS NULL
           AND EXISTS (SELECT 1 FROM project_participants pp WHERE pp.project_id=p.id AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL)
           AND NOT EXISTS (SELECT 1 FROM project_adjustment_requests ar WHERE ar.project_id=p.id AND ar.status='pending')
         ORDER BY p.id LIMIT 1"
    )->fetch();
    if ($publishedFixture) {
        $publishedStudent = (int) $db->query("SELECT pp.user_id FROM project_participants pp INNER JOIN student_profiles sp ON sp.user_id=pp.user_id WHERE pp.project_id=" . (int) $publishedFixture['id'] . " AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL ORDER BY pp.user_id LIMIT 1")->fetchColumn();
        $setSession($publishedStudent, ['student']);
        $academicPublishedCaps = $policy->adjustmentCapabilitiesInTransaction($db, $publishedFixture, $publishedStudent, 'academic');
        $assert(empty($academicPublishedCaps['create_adjustment_request']) && empty($academicPublishedCaps['view_adjustment_requests']), 'contexto académico no permite acceder al ajuste de proyecto publicado');
        $repositoryTeacher = (int) $db->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher' INNER JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.is_admin=0 AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL ORDER BY u.id LIMIT 1")->fetchColumn();
        if ($repositoryTeacher > 0) {
            $setSession($repositoryTeacher, ['teacher']);
            $repositoryTeacherCaps = $policy->adjustmentCapabilitiesInTransaction($db, $publishedFixture, $repositoryTeacher, 'repository');
            $assert(!empty($repositoryTeacherCaps['create_adjustment_request']) && empty($repositoryTeacherCaps['view_adjustment_requests']), 'docente repository puede crear sin leer solicitudes internas');
            $db->beginTransaction();
            try {
                $created = $service->createInTransaction($db, (int) $publishedFixture['id'], 'published', $repositoryTeacher, 'repository', ['request_type'=>'other','message'=>'Cambios solicitados por docente en proyecto publicado.']);
                $repositoryRequestId = (int) $created['request']['id'];
                $assert(($created['request']['request_type'] ?? '') === 'other', 'docente repository no usa published_modification estudiantil');
                $studentNotification = $db->prepare("SELECT COUNT(*) FROM notifications WHERE project_id=:project AND type='adjustment' AND deduplication_key LIKE :dedup");
                $studentNotification->execute(['project' => (int) $publishedFixture['id'], 'dedup' => 'adjustment:' . $repositoryRequestId . ':%']);
                $assert((int) $studentNotification->fetchColumn() > 0, 'docente repository notifica a estudiantes');
                $adminNotification = $db->prepare("SELECT COUNT(*) FROM notifications n INNER JOIN users u ON u.id=n.user_id WHERE n.project_id=:project AND n.type='adjustment' AND u.is_admin=1 AND JSON_UNQUOTE(JSON_EXTRACT(n.metadata,'$.request_id'))=:request");
                $adminNotification->execute(['project' => (int) $publishedFixture['id'], 'request' => (string) $repositoryRequestId]);
                $assert((int) $adminNotification->fetchColumn() > 0, 'docente repository notifica a administración');
                try {
                    $service->createInTransaction($db, (int) $publishedFixture['id'], 'published', $repositoryTeacher, 'repository', ['request_type'=>'published_modification','message'=>'Tipo estudiantil no permitido al docente.']);
                    $assert(false, 'docente repository usa tipo estudiantil');
                } catch (ProjectAdjustmentRequestException $e) {
                    $assert($e->httpStatus() === 422, 'tipo estudiantil rechazado para docente repository');
                }
            } finally { if ($db->inTransaction()) $db->rollBack(); }
        } else {
            echo "SKIP docente repository: no hay docente QA disponible.\n";
        }
    } else {
        echo "SKIP publicación workflow: no hay fixture QA publicada disponible.\n";
    }

    $setSession((int) $admin, ['administrator'], true, true);
    $adminTeacher = (int) $db->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher' WHERE u.is_admin=1 AND u.status='active' ORDER BY u.id LIMIT 1")->fetchColumn();
    if ($adminTeacher > 0) {
        $setSession($adminTeacher, ['teacher'], true, false);
        $adminOffCaps = $policy->adjustmentCapabilitiesInTransaction($db, $developmentFixture, $adminTeacher, 'academic_management');
        $assert(empty($adminOffCaps['manage_adjustment_requests']), 'Admin Mode inactivo bloquea gestión administrativa');
        $setSession($adminTeacher, ['teacher'], true, true);
        $adminOnCaps = $policy->adjustmentCapabilitiesInTransaction($db, $developmentFixture, $adminTeacher, 'academic_management');
        $assert(!empty($adminOnCaps['manage_adjustment_requests']), 'Admin Mode activo habilita gestión administrativa');
    }

    $setSession($studentId, ['student']);
    $db->beginTransaction();
    try {
        $db->prepare("UPDATE academic_periods SET status='closed' WHERE id=:period")->execute(['period'=>(int)$developmentFixture['academic_period_id']]);
        $situation = $policy->studentEditSituation($db, $developmentFixture, $studentId);
        $assert(empty($situation['can_edit_ordinary']) && !empty($situation['can_request_controlled_modification']), 'período terminado bloquea edición ordinaria y habilita solicitud controlada');
        $created = $service->createInTransaction($db, (int)$developmentFixture['id'], 'development', $studentId, 'academic', ['request_type'=>'published_modification','message'=>'Necesito actualizar el proyecto después del cierre del período.']);
        $requestId = (int) $created['request']['id'];
        $setSession((int)$admin, ['administrator'], true, true);
        $approved = $service->approveInTransaction($db, (int)$developmentFixture['id'], $requestId, 1, 'development', (int)$admin, 'academic_management');
        $afterApproval = $policy->studentEditSituation($db, $developmentFixture, $studentId);
        $assert(!empty($afterApproval['can_edit_ordinary']) && ($approved['project_status'] ?? '') === 'development', 'aprobación administrativa reabre la edición mediante servicio');
    } finally { if ($db->inTransaction()) $db->rollBack(); }
}

echo "Pruebas transaccionales finalizadas.\n";

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (isset($sessionDirectory) && is_dir($sessionDirectory)) @rmdir($sessionDirectory);

function connectToIsolatedDatabase(array $options): void
{
    $name = trim((string) ($options['database'] ?? ''));
    $host = trim((string) ($options['db-host'] ?? '127.0.0.1'));
    $port = trim((string) ($options['db-port'] ?? '3306'));
    $user = (string) ($options['db-user'] ?? 'root');
    $password = (string) ($options['db-password'] ?? '');
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]
    );
    $property = (new ReflectionClass('Database'))->getProperty('connection');
    $property->setValue(null, $pdo);
}
