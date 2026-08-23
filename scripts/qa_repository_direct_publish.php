<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$action = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($action, ['setup', 'verify', 'cleanup'], true)) {
    fwrite(STDERR, "Uso: php scripts/qa_repository_direct_publish.php [setup|verify|cleanup]\n");
    exit(1);
}

$title = 'QA-DIRECT-PUBLISH-001';
$token = 'qa-direct-publish-token-001';
$db = Database::connection();
$find = static function (PDO $connection) use ($title): ?array {
    $query = $connection->prepare("SELECT * FROM projects WHERE title=:title AND publication_origin='direct_repository' AND deleted_at IS NULL LIMIT 1");
    $query->execute(['title'=>$title]);
    return $query->fetch() ?: null;
};

$users = static function (PDO $connection): array {
    $student = $connection->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id INNER JOIN student_profiles sp ON sp.user_id=u.id WHERE r.code='student' AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL ORDER BY u.id LIMIT 1")->fetchColumn();
    $teacher = $connection->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id INNER JOIN teacher_profiles tp ON tp.user_id=u.id WHERE r.code='teacher' AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND tp.can_tutor=1 ORDER BY u.id LIMIT 1")->fetchColumn();
    return ['student'=>(int)$student,'teacher'=>(int)$teacher];
};

$prepared = static function (int $userId, string $suffix): array {
    $id = str_repeat(dechex((int) (microtime(true) * 1000000)), 4);
    $id = substr($id, 0, 32) . bin2hex(random_bytes(16));
    $directory = ROOT_PATH . '/storage/private/project-publication-preparations/' . $userId . '/' . $id;
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('No se pudo preparar fixture de archivo.');
    $storage = str_repeat('b', 64) . '.pdf';
    $path = $directory . DIRECTORY_SEPARATOR . $storage;
    $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    file_put_contents($path, $content);
    return ['original_name'=>'QA-direct-' . $suffix . '.pdf','storage_name'=>$storage,'absolute_path'=>$path,'extension'=>'pdf','mime_type'=>'application/pdf','size_bytes'=>strlen($content),'checksum_sha256'=>hash_file('sha256',$path)];
};

if ($action === 'setup') {
    if ($find($db)) throw new RuntimeException('El fixture QA ya existe. Ejecuta cleanup antes de repetir setup.');
    $ids = $users($db); $type = (int) $db->query("SELECT id FROM project_types WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn(); $keyword = (int) $db->query("SELECT id FROM keywords WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    if ($ids['student'] < 1 || $ids['teacher'] < 1 || $type < 1) throw new RuntimeException('No existen usuarios/catálogos QA suficientes.');
    $file = $prepared($ids['teacher'], 'setup');
    $replayFile = $prepared($ids['teacher'], 'replay');
    $replayFile['original_name'] = $file['original_name'];
    copy($file['absolute_path'], $replayFile['absolute_path']);
    $replayFile['size_bytes'] = filesize($replayFile['absolute_path']);
    $replayFile['checksum_sha256'] = hash_file('sha256', $replayFile['absolute_path']);
    try {
        $service = new RepositoryDirectProjectService();
        $result = $service->publishPrepared($ids['teacher'], ['title'=>$title,'project_type_id'=>$type,'description'=>'Fixture QA para verificar publicación directa, paquete e aislamiento académico.','author_ids'=>[$ids['student']],'tutor_id'=>$ids['teacher'],'keyword_ids'=>$keyword > 0 ? [$keyword] : [],'period_id'=>'historical-tamper'], [$file], $token);
        $replay = $service->publishPrepared($ids['teacher'], ['title'=>'  '.$title.'  ','project_type_id'=>(string)$type,'description'=>'  Fixture QA para verificar publicación directa, paquete e aislamiento académico.  ','author_ids'=>[$ids['student']],'tutor_id'=>(string)$ids['teacher'],'keyword_ids'=>$keyword > 0 ? [(string)$keyword] : []], [$replayFile], $token);
        if ((int)($replay['project_id'] ?? 0) !== (int)($result['project_id'] ?? 0)) throw new RuntimeException('La repetición idempotente no devolvió el mismo proyecto.');
        echo 'created_project=' . (int)$result['project_id'] . PHP_EOL;
    } finally {
        if (is_file($file['absolute_path'])) @unlink($file['absolute_path']);
        @rmdir(dirname($file['absolute_path'])); @rmdir(dirname(dirname($file['absolute_path'])));
        if (is_file($replayFile['absolute_path'])) @unlink($replayFile['absolute_path']);
        @rmdir(dirname($replayFile['absolute_path'])); @rmdir(dirname(dirname($replayFile['absolute_path'])));
    }
    exit(0);
}

if ($action === 'verify') {
    $project = $find($db); if (!$project) throw new RuntimeException('Fixture no encontrado.');
    $ids = $users($db); $projectId = (int)$project['id'];
    $files = $db->prepare("SELECT * FROM project_files WHERE project_id=:id AND deleted_at IS NULL AND purged_at IS NULL"); $files->execute(['id'=>$projectId]); $fileRows=$files->fetchAll();
    $authorCount = (int) $db->query("SELECT COUNT(*) FROM project_participants WHERE project_id={$projectId} AND role_code='student' AND status='active' AND removed_at IS NULL")->fetchColumn();
    $keywordCount = (int) $db->query("SELECT COUNT(*) FROM project_keywords WHERE project_id={$projectId}")->fetchColumn();
    $activeKeywordCount = (int) $db->query("SELECT COUNT(*) FROM keywords WHERE is_active=1")->fetchColumn();
    $package = (new ProjectRepositoryPackageService())->describe($projectId, '');
    $students = (new ProjectModel())->getStudentProjectsResult($ids['student']);
    $teacherProjects = (new TeacherAssignedProjectService())->forTeacher($ids['teacher']);
    $record = (new ProjectRecordModel())->find($projectId, $ids['student'], false);
    $timeline = (new ProjectAcademicTimelineService())->page($projectId);
    $pureProject = $project + ['participants'=>[['user_id'=>$ids['student'],'role_code'=>'student','status'=>'active','removed_at'=>null]],'active_file_count'=>count($fileRows),'author_count'=>1];
    $academicCaps = (new ProjectCapabilityService())->resolve($pureProject,'academic',$ids['student'],['student'],false);
    $repositoryCaps = (new ProjectCapabilityService())->resolve($pureProject,'repository',$ids['student'],['student'],false);
    $listing = (new RepositoryModel())->getPublishedProjectsResult([],['page'=>1,'size'=>100]);
    $listed = in_array($projectId,array_map(static fn(array $row):int=>(int)$row['id'],(array)($listing['items']??[])),true);
    $replayToken = 'qa-direct-publish-invalid-' . bin2hex(random_bytes(4));
    $invalidFile = $prepared($ids['teacher'], 'invalid');
    $invalidAuthorRejected = false; $invalidTutorRejected = false;
    try { (new RepositoryDirectProjectService())->publishPrepared($ids['teacher'],['title'=>$title.' invalid-author','project_type_id'=>(int)$project['project_type_id'],'description'=>'Descripción suficiente para validar el rechazo de un autor que no es estudiante.','author_ids'=>[$ids['teacher']],'tutor_id'=>$ids['teacher']],[$invalidFile],$replayToken); } catch (RepositoryDirectProjectException $e) { $invalidAuthorRejected = $e->status === 422; }
    if (is_file($invalidFile['absolute_path'])) @unlink($invalidFile['absolute_path']); @rmdir(dirname($invalidFile['absolute_path'])); @rmdir(dirname(dirname($invalidFile['absolute_path'])));
    $stored = $fileRows[0] ?? [];
    $storedFile = ['original_name'=>(string)($stored['original_name']??''),'storage_name'=>(string)($stored['storage_name']??''),'absolute_path'=>ROOT_PATH.'/'.ltrim((string)($stored['storage_path']??''),'/'),'extension'=>(string)($stored['extension']??''),'mime_type'=>(string)($stored['mime_type']??''),'size_bytes'=>(int)($stored['size_bytes']??0),'checksum_sha256'=>(string)($stored['checksum_sha256']??'')];
    $conflictTitle = false;
    try { (new RepositoryDirectProjectService())->publishPrepared($ids['teacher'],['title'=>$title.' changed','project_type_id'=>(int)$project['project_type_id'],'description'=>(string)$project['summary'],'author_ids'=>[$ids['student']],'tutor_id'=>$ids['teacher']],[$storedFile],$token); } catch (RepositoryDirectProjectException $e) { $conflictTitle = $e->status === 409; }
    $conflictFile = $prepared($ids['teacher'], 'conflict-file'); $conflictFile['original_name'] = (string)$stored['original_name']; file_put_contents($conflictFile['absolute_path'], "%PDF-1.4\n2 0 obj\n<< /Type /Catalog /QA 1 >>\nendobj\n%%EOF\n"); $conflictFile['size_bytes'] = filesize($conflictFile['absolute_path']); $conflictFile['checksum_sha256'] = hash_file('sha256', $conflictFile['absolute_path']);
    $conflictBytes = false;
    try { (new RepositoryDirectProjectService())->publishPrepared($ids['teacher'],['title'=>$title,'project_type_id'=>(int)$project['project_type_id'],'description'=>(string)$project['summary'],'author_ids'=>[$ids['student']],'tutor_id'=>$ids['teacher']],[$conflictFile],$token); } catch (RepositoryDirectProjectException $e) { $conflictBytes = $e->status === 409; }
    if (is_file($conflictFile['absolute_path'])) @unlink($conflictFile['absolute_path']); @rmdir(dirname($conflictFile['absolute_path'])); @rmdir(dirname(dirname($conflictFile['absolute_path'])));
    $invalidFile = $prepared($ids['teacher'], 'invalid-tutor');
    try { (new RepositoryDirectProjectService())->publishPrepared($ids['teacher'],['title'=>$title.' invalid-tutor','project_type_id'=>(int)$project['project_type_id'],'description'=>'Descripción suficiente para validar el rechazo de un tutor no docente.','author_ids'=>[$ids['student']],'tutor_id'=>$ids['student']],[$invalidFile],'qa-direct-publish-invalid-tutor-' . bin2hex(random_bytes(4))); } catch (RepositoryDirectProjectException $e) { $invalidTutorRejected = $e->status === 422; }
    if (is_file($invalidFile['absolute_path'])) @unlink($invalidFile['absolute_path']); @rmdir(dirname($invalidFile['absolute_path'])); @rmdir(dirname(dirname($invalidFile['absolute_path'])));
    $checks = [
        'direct_origin'=>($project['publication_origin']??'')===ProjectPublicationOrigin::DIRECT_REPOSITORY,
        'published_available'=>$project['status']==='published'&&(int)$project['is_available']===1,
        'traceability'=>(int)$project['repository_added_by']===$ids['teacher']&&!empty($project['repository_added_at'])&&!empty($project['published_at']),
        'active_period'=>(int)$project['academic_period_id']===(int)$db->query("SELECT id FROM academic_periods WHERE status='active' ORDER BY starts_on DESC,id DESC LIMIT 1")->fetchColumn(),
        'authors_and_tutor'=>$authorCount>0&&(int)$project['tutor_id']===$ids['teacher'],
        'keywords'=>$activeKeywordCount > 0 ? $keywordCount > 0 : $keywordCount === 0,
        'files_active'=>count($fileRows)>0,
        'package_valid'=>!empty($package['available']),
        'repository_visible'=>$listed,
        'repository_capability'=>!empty($repositoryCaps['view_project']),
        'student_excluded'=>!in_array($projectId,array_map(static fn(array $row):int=>(int)$row['id'],(array)($students['items']??[])),true),
        'teacher_excluded'=>!in_array($projectId,array_map(static fn(array $row):int=>(int)$row['id'],(array)($teacherProjects['projects']??[])),true),
        'workspace_denied'=>$record===null&&empty($academicCaps['view_project']),
        'timeline_empty'=>(int)($timeline['total']??0)===0,
        'invalid_author_rejected'=>$invalidAuthorRejected,
        'invalid_tutor_rejected'=>$invalidTutorRejected,
        'same_token_different_title_conflict'=>$conflictTitle,
        'same_token_same_name_different_file_conflict'=>$conflictBytes,
    ];
    foreach($checks as $name=>$pass) echo ($pass?'PASS ':'FAIL ').$name.PHP_EOL;
    if(in_array(false,$checks,true)) exit(2);
    exit(0);
}

$project = $find($db); if (!$project) { echo "cleanup no-op\n"; exit(0); }
$projectId=(int)$project['id'];
$files=$db->prepare('SELECT storage_name FROM project_files WHERE project_id=:id');$files->execute(['id'=>$projectId]);
foreach($files->fetchAll(PDO::FETCH_COLUMN) as $storage) { $path=ROOT_PATH.'/storage/private/projects/'.$projectId.'/'.basename((string)$storage); if(is_file($path)) @unlink($path); }
$db->beginTransaction();
try { $db->prepare("DELETE FROM repository_direct_publish_requests WHERE project_id=:id")->execute(['id'=>$projectId]); $db->prepare("DELETE FROM projects WHERE id=:id AND title=:title AND publication_origin='direct_repository'")->execute(['id'=>$projectId,'title'=>$title]); $db->commit(); } catch(Throwable $e) { $db->rollBack(); throw $e; }
$package=ProjectRepositoryPackageService::packagePath($projectId); if(is_file($package)) @unlink($package); @rmdir(ROOT_PATH.'/storage/private/projects/'.$projectId);
echo 'cleaned_project=' . $projectId . PHP_EOL;
