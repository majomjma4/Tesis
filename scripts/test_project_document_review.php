<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$config = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$db = Database::connection();
$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function fixture(PDO $db, int $number, bool $withFiles = true): array
{
    $type = (int)$db->query('SELECT id FROM project_types ORDER BY id LIMIT 1')->fetchColumn();
    $career = (int)$db->query('SELECT id FROM careers ORDER BY id LIMIT 1')->fetchColumn();
    $period = (int)$db->query('SELECT id FROM academic_periods ORDER BY id LIMIT 1')->fetchColumn();
    $teacher = (int)$db->query("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.code='teacher' AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL ORDER BY u.id LIMIT 1")->fetchColumn();
    $otherTeacher = (int)$db->query("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.code='teacher' AND u.status='active' AND u.id<>$teacher AND u.deleted_at IS NULL AND u.purged_at IS NULL ORDER BY u.id LIMIT 1")->fetchColumn();
    $student = (int)$db->query("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.code='student' AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL ORDER BY u.id LIMIT 1")->fetchColumn();
    check($type > 0 && $career > 0 && $period > 0 && $teacher > 0 && $otherTeacher > 0 && $student > 0, 'Faltan catálogos o identidades de prueba.');
    $code = 'TST-REV-' . getmypid() . '-' . $number;
    $insert = $db->prepare("INSERT INTO projects(code,project_type_id,career_id,academic_period_id,title,tutor_id,status,current_stage,created_by) VALUES(:code,:type,:career,:period,'Prueba revisión documental',:tutor,'under_review','review',:creator)");
    $insert->execute(['code'=>$code, 'type'=>$type, 'career'=>$career, 'period'=>$period, 'tutor'=>$teacher, 'creator'=>$teacher]);
    $project = (int)$db->lastInsertId();
    $participant = $db->prepare("INSERT INTO project_participants(project_id,user_id,role_code,permission_level,is_leader,status) VALUES(:project,:user,:role,:permission,:leader,'active')");
    $participant->execute(['project'=>$project, 'user'=>$teacher, 'role'=>'tutor', 'permission'=>'review', 'leader'=>0]);
    $participant->execute(['project'=>$project, 'user'=>$student, 'role'=>'student', 'permission'=>'contribute', 'leader'=>1]);
    $files = [];
    if ($withFiles) {
        $fileInsert = $db->prepare("INSERT INTO project_files(project_id,category,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256,uploaded_by,sort_order) VALUES(:project,'document','documento.pdf',:storage,:path,'application/pdf','pdf',10,:checksum,:actor,:sort)");
        foreach ([1, 2] as $position) {
            $checksum = hash('sha256', $code . '-' . $position);
            $fileInsert->execute(['project'=>$project, 'storage'=>$code.'-'.$position.'.pdf', 'path'=>'tests/'.$code.'-'.$position.'.pdf', 'checksum'=>$checksum, 'actor'=>$student, 'sort'=>$position]);
            $files[] = ['file_id'=>(int)$db->lastInsertId(), 'expected_checksum'=>$checksum];
        }
    }
    return compact('project', 'teacher', 'otherTeacher', 'student', 'files', 'code');
}

function runCase(PDO $db, string $name, callable $test): void
{
    global $passed, $failed;
    static $savepoint = 0;
    $point = 'review_case_' . (++$savepoint);
    $db->exec("SAVEPOINT $point");
    try {
        $test();
        echo "OK  $name\n";
        $passed++;
    } catch (Throwable $exception) {
        echo "FAIL $name: {$exception->getMessage()}\n";
        $failed++;
    } finally {
        $db->exec("ROLLBACK TO SAVEPOINT $point");
        $db->exec("RELEASE SAVEPOINT $point");
    }
}

$db->beginTransaction();
try {
    $case = 0;
    runCase($db, 'lote múltiple aprobado y auditoría única', function() use ($db, &$case): void {
        $f = fixture($db, ++$case);
        $decisions = array_map(fn(array $file): array => $file + ['status'=>'approved', 'observations'=>[]], $f['files']);
        $result = (new ProjectDocumentReviewBatchService())->confirmInTransaction($db, $f['project'], 'under_review', $decisions, $f['teacher']);
        check($result['all_active_documents_approved'] === true, 'El resumen no marcó todos aprobados.');
        check((int)$db->query("SELECT COUNT(*) FROM project_audit_log WHERE project_id={$f['project']} AND action='project_document_review_completed'")->fetchColumn() === 1, 'La auditoría no es única.');
        check((int)$db->query("SELECT COUNT(*) FROM notifications WHERE project_id={$f['project']}")->fetchColumn() === 0, 'Se notificaron aprobaciones.');
    });
    runCase($db, 'observación fuerza correcciones y notificación consolidada', function() use ($db, &$case): void {
        $f = fixture($db, ++$case);
        $decisions = [
            $f['files'][0] + ['status'=>'under_review', 'observations'=>[['body'=>'Corregir la metodología descrita.', 'category'=>'Metodología', 'location_reference'=>'Página 4']]],
            $f['files'][1] + ['status'=>'under_review', 'observations'=>[]],
        ];
        $result = (new ProjectDocumentReviewBatchService())->confirmInTransaction($db, $f['project'], 'under_review', $decisions, $f['teacher']);
        check($result['project_status'] === 'development' && $result['observations_created'] === 1, 'No se aplicaron las correcciones.');
        check((int)$result['summary']['corrections_requested'] === 1, 'La observación no forzó el estado.');
        check((int)$db->query("SELECT COUNT(*) FROM notifications WHERE project_id={$f['project']}")->fetchColumn() === 1, 'La notificación no fue consolidada.');
    });
    runCase($db, 'aprobado con observación es rechazado', function() use ($db, &$case): void {
        $f = fixture($db, ++$case);
        try {
            (new ProjectDocumentReviewBatchService())->confirmInTransaction($db, $f['project'], 'under_review', [
                $f['files'][0] + ['status'=>'approved', 'observations'=>[['body'=>'Esta observación impide aprobar.']]],
            ], $f['teacher']);
            throw new RuntimeException('La combinación incompatible fue aceptada.');
        } catch (ProjectStatusTransitionException $e) { check($e->httpStatus() === 422, 'Código inesperado.'); }
    });
    runCase($db, 'checksum obsoleto produce 409 y cero escrituras', function() use ($db, &$case): void {
        $f = fixture($db, ++$case);
        $decision = $f['files'][0] + ['status'=>'approved', 'observations'=>[]];
        $decision['expected_checksum'] = str_repeat('a', 64);
        try { (new ProjectDocumentReviewBatchService())->confirmInTransaction($db, $f['project'], 'under_review', [$decision], $f['teacher']); }
        catch (ProjectStatusTransitionException $e) { check($e->httpStatus() === 409, 'No devolvió 409.'); }
        check((int)$db->query("SELECT COUNT(*) FROM project_file_review_states WHERE project_id={$f['project']}")->fetchColumn() === 0, 'Quedaron estados parciales.');
        check((int)$db->query("SELECT COUNT(*) FROM project_observations WHERE project_id={$f['project']}")->fetchColumn() === 0, 'Quedaron observaciones parciales.');
    });
    runCase($db, 'archivo de otro proyecto y archivo retirado son rechazados', function() use ($db, &$case): void {
        $a = fixture($db, ++$case); $b = fixture($db, ++$case);
        foreach ([$b['files'][0], $a['files'][0]] as $index=>$file) {
            if ($index === 1) $db->prepare('UPDATE project_files SET deleted_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id'=>$file['file_id']]);
            try { (new ProjectDocumentReviewBatchService())->confirmInTransaction($db, $a['project'], 'under_review', [$file + ['status'=>'approved']], $a['teacher']); throw new RuntimeException('Documento inválido aceptado.'); }
            catch (ProjectStatusTransitionException) {}
        }
    });
    runCase($db, 'autorización: tutor sí, otro docente y estudiante no', function() use ($db, &$case): void {
        $f = fixture($db, ++$case);
        $project = ['id'=>$f['project'], 'tutor_id'=>$f['teacher']];
        $capability = new ProjectCapabilityService();
        check($capability->canReviewDocumentsInTransaction($db, $project, $f['teacher'], 'academic'), 'Tutor asignado rechazado.');
        check(!$capability->canReviewDocumentsInTransaction($db, $project, $f['otherTeacher'], 'academic'), 'Docente ajeno autorizado.');
        check(!$capability->canReviewDocumentsInTransaction($db, $project, $f['student'], 'academic'), 'Estudiante autorizado.');
        check(!$capability->canReviewDocumentsInTransaction($db, $project, $f['teacher'], 'repository'), 'Repositorio autorizado.');
    });
    runCase($db, 'proyecto vacío es rechazado', function() use ($db, &$case): void {
        $f = fixture($db, ++$case, false);
        try { (new ProjectDocumentReviewBatchService())->confirmInTransaction($db, $f['project'], 'under_review', [['file_id'=>1,'expected_checksum'=>str_repeat('a',64),'status'=>'approved']], $f['teacher']); throw new RuntimeException('Expediente vacío aceptado.'); }
        catch (ProjectStatusTransitionException $e) { check(str_contains($e->getMessage(), 'no contiene documentos'), 'Mensaje institucional ausente.'); }
    });
    runCase($db, 'uno pendiente mantiene all_active_documents_approved=false', function() use ($db, &$case): void {
        $f = fixture($db, ++$case);
        $decisions = [$f['files'][0] + ['status'=>'approved'], $f['files'][1] + ['status'=>'under_review']];
        $result = (new ProjectDocumentReviewBatchService())->confirmInTransaction($db, $f['project'], 'under_review', $decisions, $f['teacher']);
        check($result['all_active_documents_approved'] === false && $result['project_status'] === 'under_review', 'Resumen o proyecto incorrecto.');
    });
    runCase($db, 'aprobado vigente omitido conserva aprobación; nueva versión no la hereda', function() use ($db, &$case): void {
        $f = fixture($db, ++$case);
        $service = new ProjectDocumentReviewService($db);
        $service->recordCurrentStatus($f['project'], $f['files'][0]['file_id'], $f['files'][0]['expected_checksum'], 'approved', $f['teacher']);
        $result = (new ProjectDocumentReviewBatchService())->confirmInTransaction($db, $f['project'], 'under_review', [$f['files'][1] + ['status'=>'under_review']], $f['teacher']);
        check((int)$result['summary']['approved'] === 1, 'No conservó la aprobación vigente.');
        $newChecksum = hash('sha256', 'nueva-version-'.$f['project']);
        $db->prepare('UPDATE project_files SET checksum_sha256=:checksum WHERE id=:id')->execute(['checksum'=>$newChecksum, 'id'=>$f['files'][0]['file_id']]);
        $summary = $service->approvalSummaryForProject($f['project']);
        check((int)$summary['approved'] === 0 && (int)$summary['development'] === 1, 'La nueva versión heredó aprobación.');
    });
    runCase($db, 'precondición impide aprobar pendientes y permite todos aprobados', function() use ($db, &$case): void {
        $f = fixture($db, ++$case);
        $review = new ProjectDocumentReviewService($db);
        $review->recordCurrentStatus($f['project'], $f['files'][0]['file_id'], $f['files'][0]['expected_checksum'], 'approved', $f['teacher']);
        try {
            (new ProjectStatusTransitionService())->transitionInTransaction($db, $f['project'], 'under_review', 'approved', '', $f['teacher']);
            throw new RuntimeException('Se aprobó con documentos pendientes.');
        } catch (ProjectStatusTransitionException $e) {
            check(str_contains($e->getMessage(), 'todos sus documentos'), 'Mensaje de documentos pendientes incorrecto.');
        }
        $review->recordCurrentStatus($f['project'], $f['files'][1]['file_id'], $f['files'][1]['expected_checksum'], 'approved', $f['teacher']);
        $result = (new ProjectStatusTransitionService())->transitionInTransaction($db, $f['project'], 'under_review', 'approved', '', $f['teacher']);
        check($result['status'] === 'approved', 'No permitió aprobar todos los documentos.');
    });
} finally {
    if ($db->inTransaction()) $db->rollBack();
}

try {
    $rollbackFixture = null;
    Database::transaction(function(PDO $transaction) use (&$rollbackFixture, &$case): void {
        $rollbackFixture = fixture($transaction, ++$case);
        $decisions = array_map(fn(array $file, int $index): array => $file + [
            'status'=>'corrections_requested',
            'observations'=>[['body'=>'Corrección '.($index + 1).' creada antes del fallo simulado.']],
        ], $rollbackFixture['files'], array_keys($rollbackFixture['files']));
        (new ProjectDocumentReviewBatchService())->confirmInTransaction(
            $transaction, $rollbackFixture['project'], 'under_review', $decisions, $rollbackFixture['teacher']
        );
        throw new RuntimeException('Fallo controlado posterior a múltiples escrituras.');
    });
    throw new RuntimeException('La prueba de rollback no produjo el fallo esperado.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'La prueba de rollback no produjo el fallo esperado.') throw $exception;
    check(is_array($rollbackFixture), 'No se creó la prueba de rollback.');
    $find = $db->prepare('SELECT COUNT(*) FROM projects WHERE code=:code');
    $find->execute(['code'=>$rollbackFixture['code']]);
    check((int)$find->fetchColumn() === 0, 'La transacción dejó datos residuales.');
    echo "OK  fallo posterior a múltiples escrituras produce rollback completo\n";
    $passed++;
}

echo "\nResultado: $passed correctas, $failed fallidas. Todas las escrituras fueron revertidas.\n";
exit($failed === 0 ? 0 : 1);
