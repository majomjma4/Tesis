<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$db = Database::connection();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FALLO: ' . $message);
    echo 'OK  ' . $message . PHP_EOL;
};

$sessionWasActive = session_status() === PHP_SESSION_ACTIVE;
$originalSession = $_SESSION ?? [];
$sessionPath = null;
$failure = null;

try {
    if (!$sessionWasActive) {
        $sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tesis-p1-effective-context-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) throw new RuntimeException('No se pudo preparar la sesion temporal.');
        session_save_path($sessionPath);
        session_start();
        if (session_status() !== PHP_SESSION_ACTIVE) throw new RuntimeException('No se pudo iniciar la sesion temporal.');
    }

    $fixtures = [
        'teacher' => $db->query("SELECT u.id
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='teacher'
            WHERE COALESCE(u.is_admin,0)=0 AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
            LIMIT 1")->fetchColumn(),
        'teacher_admin' => $db->query("SELECT u.id
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='teacher'
            WHERE u.is_admin=1 AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
            LIMIT 1")->fetchColumn(),
        'admin' => $db->query("SELECT u.id
            FROM users u
            WHERE u.is_admin=1 AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id
                  WHERE ur.user_id=u.id AND r.code='teacher'
              )
            LIMIT 1")->fetchColumn(),
        'student' => $db->query("SELECT u.id
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='student'
            WHERE COALESCE(u.is_admin,0)=0 AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
            LIMIT 1")->fetchColumn(),
        'project' => $db->query("SELECT id FROM projects WHERE deleted_at IS NULL AND publication_origin='workflow' ORDER BY id LIMIT 1")->fetchColumn(),
    ];
    foreach ($fixtures as $name => $value) {
        $assert($value !== false && (int) $value > 0, 'fixture disponible: ' . $name);
    }

    $projectId = (int) $fixtures['project'];
    $marker = 'qa-effective-context-' . bin2hex(random_bytes(6));
    $audit = new ProjectAuditService($db);
    $auditIds = [];

    $db->beginTransaction();
    try {
        $cases = [
            ['label'=>'teacher', 'user'=>(int)$fixtures['teacher'], 'roles'=>['teacher'], 'is_admin'=>false, 'admin_mode'=>false, 'expected'=>'teacher'],
            ['label'=>'teacher_admin_off', 'user'=>(int)$fixtures['teacher_admin'], 'roles'=>['teacher','administrator'], 'is_admin'=>true, 'admin_mode'=>false, 'expected'=>'teacher'],
            ['label'=>'teacher_admin_on', 'user'=>(int)$fixtures['teacher_admin'], 'roles'=>['teacher','administrator'], 'is_admin'=>true, 'admin_mode'=>true, 'expected'=>'admin_mode'],
            ['label'=>'admin_only', 'user'=>(int)$fixtures['admin'], 'roles'=>['administrator'], 'is_admin'=>true, 'admin_mode'=>false, 'expected'=>'admin'],
            ['label'=>'student', 'user'=>(int)$fixtures['student'], 'roles'=>['student'], 'is_admin'=>false, 'admin_mode'=>false, 'expected'=>'student'],
        ];

        foreach ($cases as $case) {
            $_SESSION = [
                'user_id' => $case['user'],
                'roles' => $case['roles'],
                'role' => $case['roles'][0],
                'is_admin' => $case['is_admin'],
                'admin_mode' => $case['admin_mode'],
            ];
            $_POST = ['effective_context' => 'admin'];
            $id = $audit->record(
                $projectId,
                $case['user'],
                'project_status_changed',
                'qa_effective_context',
                $projectId,
                ['status'=>'development'],
                ['status'=>'published', 'qa_marker'=>$marker . '-' . $case['label']],
                $marker . '-' . $case['label']
            );
            $auditIds[$case['label']] = $id;
            $check = $db->prepare('SELECT effective_context FROM project_audit_log WHERE id=:id');
            $check->execute(['id'=>$id]);
            $assert($check->fetchColumn() === $case['expected'], 'persistencia server-side: ' . $case['label']);
        }

        $_SESSION = [];
        unset($_POST);
        $systemId = $audit->record(
            $projectId,
            null,
            'project_status_changed',
            'qa_effective_context',
            $projectId,
            ['status'=>'development'],
            ['status'=>'published', 'qa_marker'=>$marker . '-system'],
            $marker . '-system'
        );
        $auditIds['system'] = $systemId;
        $check = $db->prepare('SELECT effective_context FROM project_audit_log WHERE id=:id');
        $check->execute(['id'=>$systemId]);
        $assert($check->fetchColumn() === 'system', 'persistencia server-side: system');

        $legacyInsert = $db->prepare(
            'INSERT INTO project_audit_log
             (project_id,user_id,effective_context,action,entity_type,entity_id,previous_state,new_state,reason,ip_address,user_agent)
             VALUES (:project,:user,NULL,:action,:entity_type,:entity,:previous_state,:new_state,:reason,NULL,NULL)'
        );
        $legacyInsert->execute([
            'project'=>$projectId,
            'user'=>(int)$fixtures['admin'],
            'action'=>'project_updated',
            'entity_type'=>'qa_effective_context',
            'entity'=>$projectId,
            'previous_state'=>json_encode(['status'=>'development'], JSON_THROW_ON_ERROR),
            'new_state'=>json_encode(['qa_marker'=>$marker . '-legacy', 'edited_by_administrator'=>true], JSON_THROW_ON_ERROR),
            'reason'=>$marker . '-legacy',
        ]);
        $legacyId = (int) $db->lastInsertId();

        $history = (new ProjectAuditHistoryModel())->forProject($projectId, 20, 0, 'academic_management');
        $historyById = [];
        foreach ($history['items'] as $item) $historyById[(int)$item['id']] = $item;
        $assert(($historyById[$auditIds['teacher_admin_on']]['effective_context'] ?? null) === 'admin_mode', 'historial recupera admin_mode persistido');
        $assert(($historyById[$auditIds['admin_only']]['effective_context'] ?? null) === 'admin', 'historial recupera admin persistido');
        $assert(array_key_exists($legacyId, $historyById) && $historyById[$legacyId]['effective_context'] === null, 'historial conserva NULL historico');
        $assert(!array_key_exists($auditIds['teacher_admin_off'], $historyById), 'historial no reclasifica docente con privilegios');
        $assert(!array_key_exists($auditIds['student'], $historyById), 'historial excluye contexto estudiantil');
        $assert(!array_key_exists($auditIds['system'], $historyById), 'historial excluye contexto de sistema');

        $reportEvents = (new AdminReportModel())->auditEvents('2020-01-01', '2099-12-31', 3);
        $reportMatch = array_filter($reportEvents, static fn(array $event): bool =>
            (string)($event['action'] ?? '') === 'project_status_changed'
            && (string)($event['entity_type'] ?? '') === 'qa_effective_context'
            && (int)($event['entity_id'] ?? 0) === $projectId
            && ($event['effective_context'] ?? null) === 'admin_mode'
        );
        $assert($reportMatch !== [], 'reporte administrativo recupera contexto persistido');

        $timelineEvents = (new ProjectAcademicTimelineService($db))->page($projectId, 0, 100)['events'];
        $timelineMatch = array_filter($timelineEvents, static fn(array $event): bool =>
            (string)($event['event_type'] ?? '') === 'project_status_changed'
            && (int)($event['source_id'] ?? 0) === $auditIds['teacher_admin_on']
            && ($event['effective_context'] ?? null) === 'admin_mode'
        );
        $assert($timelineMatch !== [], 'linea de tiempo recupera contexto persistido');

        $db->rollBack();
        $residue = $db->prepare('SELECT IF(EXISTS(SELECT 1 FROM project_audit_log WHERE reason LIKE :marker),\'found\',\'none\')');
        $residue->execute(['marker'=>$marker . '%']);
        $assert($residue->fetchColumn() === 'none', 'rollback sin residuos QA');
    } finally {
        if ($db->inTransaction()) $db->rollBack();
    }
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    unset($_POST);
    if ($sessionWasActive) {
        $_SESSION = $originalSession;
    } else {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        if ($sessionPath !== null && is_dir($sessionPath)) {
            foreach (glob($sessionPath . DIRECTORY_SEPARATOR . '*') ?: [] as $sessionFile) {
                if (is_file($sessionFile)) @unlink($sessionFile);
            }
            @rmdir($sessionPath);
        }
    }
}

if ($failure !== null) {
    fwrite(STDERR, 'FAIL ' . $failure->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Prueba de contexto efectivo finalizada sin residuos.' . PHP_EOL;
