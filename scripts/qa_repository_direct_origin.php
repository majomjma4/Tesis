<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$action = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($action, ['setup', 'verify', 'cleanup'], true)) {
    fwrite(STDERR, "Uso: php scripts/qa_repository_direct_origin.php [setup|verify|cleanup]\n");
    exit(1);
}

$code = 'QA-DIRECT-REP-001';
$title = 'QA direct repository isolation';
$db = Database::connection();

$find = static function (PDO $connection) use ($code, $title): ?array {
    $query = $connection->prepare('SELECT id,created_by,repository_added_by,publication_origin,status,is_available FROM projects WHERE code=:code AND title=:title LIMIT 1');
    $query->execute(['code' => $code, 'title' => $title]);
    return $query->fetch() ?: null;
};

if ($action === 'setup') {
    if ($find($db) !== null) {
        echo "Fixture ya existente: {$code}\n";
        exit(0);
    }
    $student = $db->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id INNER JOIN student_profiles sp ON sp.user_id=u.id WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND r.code='student' ORDER BY u.id LIMIT 1")->fetchColumn();
    $teacher = $db->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id INNER JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND r.code='teacher' ORDER BY u.id LIMIT 1")->fetchColumn();
    $type = $db->query("SELECT id FROM project_types WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    $career = $db->query("SELECT id FROM careers ORDER BY id LIMIT 1")->fetchColumn();
    $period = $db->query("SELECT id FROM academic_periods ORDER BY id DESC LIMIT 1")->fetchColumn();
    if (!$student || !$teacher || !$type || !$career || !$period) throw new RuntimeException('No hay catálogos/usuarios activos suficientes para el fixture.');

    Database::transaction(function (PDO $connection) use ($code, $title, $student, $teacher, $type, $career, $period): void {
        $insert = $connection->prepare("INSERT INTO projects (code,project_type_id,career_id,academic_period_id,title,subtitle,summary,status,current_stage,is_available,published_at,publication_origin,repository_added_by,repository_added_at,created_by,created_at,updated_at) VALUES (:code,:type,:career,:period,:title,NULL,'Fixture read-only de aislamiento','published','repository_direct',1,UTC_TIMESTAMP(),'direct_repository',:added_by,UTC_TIMESTAMP(),:creator,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $insert->execute(['code'=>$code,'type'=>(int)$type,'career'=>(int)$career,'period'=>(int)$period,'title'=>$title,'added_by'=>(int)$teacher,'creator'=>(int)$teacher]);
        $projectId = (int) $connection->lastInsertId();
        $participant = $connection->prepare("INSERT INTO project_participants (project_id,user_id,role_code,permission_level,is_leader,status) VALUES (:project,:user,'student','read',1,'active')");
        $participant->execute(['project'=>$projectId,'user'=>(int)$student]);
        $file = $connection->prepare("INSERT INTO project_files (project_id,delivery_id,category,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256,uploaded_by) VALUES (:project,NULL,'repository','qa-direct-repository.pdf',:storage,:path,'application/pdf','pdf',1,:checksum,:user)");
        $file->execute(['project'=>$projectId,'storage'=>'qa-direct-repository.pdf','path'=>'storage/private/qa-direct-repository.pdf','checksum'=>str_repeat('0',64),'user'=>(int)$teacher]);
        echo "Fixture creado: proyecto {$projectId}; student {$student}; teacher {$teacher}\n";
    });
    exit(0);
}

if ($action === 'cleanup') {
    $project = $find($db);
    if ($project === null) { echo "Fixture ausente; cleanup no-op.\n"; exit(0); }
    Database::transaction(function (PDO $connection) use ($code, $title): void {
        $delete = $connection->prepare('DELETE FROM projects WHERE code=:code AND title=:title AND publication_origin=:origin');
        $delete->execute(['code'=>$code,'title'=>$title,'origin'=>ProjectPublicationOrigin::DIRECT_REPOSITORY]);
        if ($delete->rowCount() > 1) throw new RuntimeException('El cleanup identificó más de un fixture.');
    });
    echo "Fixture eliminado: {$code}\n";
    exit(0);
}

$project = $find($db);
if ($project === null) throw new RuntimeException('Fixture no encontrado. Ejecuta setup primero.');
$student = $db->prepare("SELECT user_id FROM project_participants WHERE project_id=:project AND role_code='student' AND status='active' AND removed_at IS NULL LIMIT 1");
$student->execute(['project'=>(int)$project['id']]);
$studentId = (int) $student->fetchColumn();
$studentProjects = (new ProjectModel())->getStudentProjectsResult($studentId);
$teacherProjects = (new TeacherAssignedProjectService())->forTeacher((int)$project['repository_added_by']);
$timeline = (new ProjectAcademicTimelineService())->page((int)$project['id']);
$record = (new ProjectRecordModel())->find((int)$project['id'], $studentId, false);
$resolved = (new ProjectCapabilityService())->resolve($project + ['participants'=>[['user_id'=>$studentId,'role_code'=>'student','status'=>'active','removed_at'=>null]],'active_file_count'=>1,'author_count'=>1], 'academic', $studentId, ['student'], false);
$repositoryCapabilities = (new ProjectCapabilityService())->resolve($project + ['participants'=>[['user_id'=>$studentId,'role_code'=>'student','status'=>'active','removed_at'=>null]],'active_file_count'=>1,'author_count'=>1], 'repository', $studentId, ['student'], false);
$repository = (new RepositoryModel())->getPublishedProjectsResult([], ['page'=>1,'size'=>100]);
$visibleInRepository = in_array((int)$project['id'], array_map(static fn(array $row): int => (int)$row['id'], (array)($repository['items'] ?? [])), true);

$checks = [
    'student_collection_excludes' => !in_array((int)$project['id'], array_map(static fn(array $row): int => (int)$row['id'], (array)($studentProjects['items'] ?? [])), true),
    'teacher_assigned_excludes' => !in_array((int)$project['id'], array_map(static fn(array $row): int => (int)$row['id'], (array)($teacherProjects['projects'] ?? [])), true),
    'timeline_empty' => (int)($timeline['total'] ?? 0) === 0,
    'academic_record_denied' => $record === null,
    'student_capabilities_denied' => empty($resolved['view_project']) && empty($resolved['manage_workspace_files']) && empty($resolved['review_documents']),
    'repository_capability_preserved' => !empty($repositoryCapabilities['view_project']),
    'repository_visible' => $visibleInRepository,
];
foreach ($checks as $name => $pass) echo ($pass ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
if (in_array(false, $checks, true)) exit(2);
