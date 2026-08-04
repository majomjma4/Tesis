<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

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
     WHERE p.deleted_at IS NULL LIMIT 1"
)->fetch();
if (!$fixture) { fwrite(STDERR, "No hay una fixture administrativa/estudiantil apta.\n"); exit(2); }

$service = new ProjectAdjustmentRequestService();
$project=(int)$fixture['project_id'];$admin=(int)$fixture['admin_id'];$student=(int)$fixture['student_id'];$status=(string)$fixture['status'];
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
    $created=$service->createInTransaction($db,$project,$status,$admin,'academic_management',['request_type'=>'inconsistency','message'=>'Corregir la información administrativa de prueba.']);
    $id=(int)$created['request']['id'];$version=(int)$created['request']['lock_version'];
    $assert($created['request']['status']==='pending','creación pendiente');
    $assert($created['summary']['pending_count']>=1,'resumen agregado');
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
    try{$service->createInTransaction($db,$project,$status,$student,'academic',['request_type'=>'other','message'=>'No autorizado']);$assert(false,'estudiante crea');}
    catch(ProjectAdjustmentRequestException $e){$assert($e->httpStatus()===403,'permiso de creación');}
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

echo "Pruebas transaccionales finalizadas.\n";
