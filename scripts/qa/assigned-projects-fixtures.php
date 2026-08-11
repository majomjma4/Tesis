<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

const QA_MARKER = '[QA_ASSIGNED_PROJECTS_4B]';
const QA_ADMIN = 1;
const QA_TEACHER = 134;
const QA_STUDENT = 135;

$command = $argv[1] ?? 'help';
if ($command === 'seed') {
    $db = Database::connection();
    assertQaAccounts($db);
    $existing = $db->prepare("SELECT id,title,status FROM projects WHERE subtitle LIKE :marker AND deleted_at IS NULL ORDER BY id");
    $existing->execute(['marker' => '%' . QA_MARKER . '%']);
    if ($rows = $existing->fetchAll()) {
        foreach ($rows as $row) Database::transaction(static function (PDO $connection) use ($row): void {
            (new ProjectTutoringService())->sync($connection, (int)$row['id'], [QA_TEACHER], QA_TEACHER, null);
        });
        echo json_encode(['created'=>false, 'projects'=>$rows], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }
    $period = (int)$db->query("SELECT id FROM academic_periods WHERE status='active' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $career = (int)$db->query("SELECT id FROM careers WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    if ($period < 1 || $career < 1) throw new RuntimeException('No existe un período y carrera activos para los fixtures QA.');
    $definitions = [
        ['title'=>'QA — Proyecto en desarrollo', 'type'=>'thesis', 'target'=>'development'],
        ['title'=>'QA — Proyecto en revisión', 'type'=>'pis', 'target'=>'under_review'],
        ['title'=>'QA — Proyecto aprobado', 'type'=>'practice', 'target'=>'approved'],
    ];
    $created = [];
    foreach ($definitions as $definition) $created[] = createFixture($db, $definition, $period, $career);
    echo json_encode(['created'=>true, 'projects'=>$created], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

if ($command === 'cleanup') {
    if (($argv[2] ?? '') !== '--confirm') {
        fwrite(STDERR, "Limpieza no ejecutada. Usa: php scripts/qa/assigned-projects-fixtures.php cleanup --confirm\n");
        exit(2);
    }
    $db = Database::connection();
    $query = $db->prepare("SELECT id FROM projects WHERE subtitle LIKE :marker");
    $query->execute(['marker'=>'%' . QA_MARKER . '%']);
    $ids = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
    foreach ($ids as $id) {
        $directory = ROOT_PATH . '/storage/private/projects/' . $id;
        Database::transaction(function (PDO $connection) use ($id): void {
            foreach (['notifications','project_file_review_states','project_file_versions','project_files','project_deliveries','project_participants','project_audit_log'] as $table) {
                $connection->prepare("DELETE FROM {$table} WHERE project_id=:project")->execute(['project'=>$id]);
            }
            $connection->prepare('DELETE FROM projects WHERE id=:project AND subtitle LIKE :marker')->execute(['project'=>$id, 'marker'=>'%' . QA_MARKER . '%']);
        });
        if (is_dir($directory)) foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) unlink($file);
        if (is_dir($directory)) rmdir($directory);
        foreach ([ProjectRepositoryPackageService::academicPackagePath($id), ProjectRepositoryPackageService::packagePath($id)] as $package) if (is_file($package)) unlink($package);
    }
    echo json_encode(['cleaned_project_ids'=>$ids], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

fwrite(STDERR, "Uso: php scripts/qa/assigned-projects-fixtures.php seed | cleanup --confirm\n");
exit(2);

function assertQaAccounts(PDO $db): void
{
    $teacher = $db->prepare("SELECT 1 FROM users u JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.id=:id AND u.status='active' AND tp.can_tutor=1");
    $teacher->execute(['id'=>QA_TEACHER]);
    $student = $db->prepare("SELECT 1 FROM users u JOIN student_profiles sp ON sp.user_id=u.id WHERE u.id=:id AND u.status='active'");
    $student->execute(['id'=>QA_STUDENT]);
    if (!$teacher->fetchColumn() || !$student->fetchColumn()) throw new RuntimeException('Las cuentas QA requeridas no están disponibles.');
}

/** @param array{title:string,type:string,target:string} $definition @return array<string,mixed> */
function createFixture(PDO $db, array $definition, int $period, int $career): array
{
    $type = $db->prepare('SELECT id FROM project_types WHERE code=:code AND is_active=1');
    $type->execute(['code'=>$definition['type']]);
    $typeId = (int)$type->fetchColumn();
    if ($typeId < 1) throw new RuntimeException('Tipo QA no disponible: ' . $definition['type']);
    $projectId = (new AdminProjectModel())->save([
        'title'=>$definition['title'], 'subtitle'=>QA_MARKER, 'summary'=>QA_MARKER,
        'project_type_id'=>$typeId, 'career_id'=>$career, 'academic_period_id'=>$period,
        'tutor_id'=>QA_TEACHER, 'status'=>'development', 'keywords'=>[],
    ], 0, QA_ADMIN, true);
    Database::transaction(function (PDO $connection) use ($projectId): void {
        (new ProjectAuthorService())->sync($connection, $projectId, [QA_STUDENT], QA_STUDENT);
        (new ProjectTutoringService())->sync($connection, $projectId, [QA_TEACHER], QA_TEACHER, null);
        (new ProjectAuditService($connection))->record($projectId, QA_ADMIN, 'qa_fixture_created', 'project', $projectId, null, ['marker'=>QA_MARKER]);
    });
    if ($definition['target'] === 'under_review' || $definition['target'] === 'approved') {
        (new ProjectStatusTransitionService())->transition($projectId, 'development', 'under_review', 'Fixture QA: transición controlada.', QA_ADMIN);
    }
    $fileId = null;
    if ($definition['target'] === 'approved') {
        $fileId = addQaFile($projectId);
        $file = $db->prepare('SELECT checksum_sha256 FROM project_files WHERE id=:id');
        $file->execute(['id'=>$fileId]);
        (new ProjectDocumentReviewBatchService())->confirm($projectId, 'under_review', [[
            'file_id'=>$fileId, 'expected_checksum'=>(string)$file->fetchColumn(), 'status'=>'approved', 'observations'=>[],
        ]], QA_TEACHER, 'academic');
        (new ProjectStatusTransitionService())->transition($projectId, 'under_review', 'approved', 'Fixture QA: aprobación documental controlada.', QA_ADMIN);
    }
    $row = $db->prepare('SELECT id,code,title,status FROM projects WHERE id=:id');
    $row->execute(['id'=>$projectId]);
    return ($row->fetch() ?: []) + ['file_id'=>$fileId];
}

function addQaFile(int $projectId): int
{
    $directory = ROOT_PATH . '/storage/private/projects/' . $projectId;
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('No se pudo crear almacenamiento QA.');
    $storage = bin2hex(random_bytes(32)) . '.txt';
    $path = $directory . DIRECTORY_SEPARATOR . $storage;
    file_put_contents($path, "Fixture QA de Proyectos asignados. " . QA_MARKER . PHP_EOL);
    $stored = ['original_name'=>'qa-evidencia-aprobada.txt','storage_name'=>$storage,'storage_path'=>'storage/private/projects/'.$projectId.'/'.$storage,'mime_type'=>'text/plain','extension'=>'txt','size_bytes'=>(int)filesize($path),'checksum_sha256'=>hash_file('sha256',$path)];
    return Database::transaction(function (PDO $db) use ($projectId, $stored): int {
        $model = new ProjectDocumentModel($db); $model->lockProject($projectId); $file = $model->add($projectId, $stored, QA_ADMIN);
        (new ProjectAuditService($db))->record($projectId, QA_ADMIN, 'project.file_added', 'project_file', (int)$file['id'], null, ['fixture'=>QA_MARKER]);
        return (int)$file['id'];
    });
}
