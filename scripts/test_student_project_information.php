<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$db = Database::connection();
$passed = 0;
$failed = 0;

function expect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function expectFailure(callable $operation, int $status): void { try { $operation(); } catch (StudentProjectInformationException $e) { expect($e->httpStatus() === $status, 'Estado HTTP inesperado.'); return; } catch (ProjectTutoringException|ProjectAuthorException $e) { expect($status === 422, 'Estado HTTP inesperado.'); return; } throw new RuntimeException('La operación debía ser rechazada.'); }
function caseRun(PDO $db, string $name, callable $test): void { global $passed, $failed; static $n=0; $point='student_info_'.++$n; $db->exec("SAVEPOINT $point"); try { $test(); echo "OK   $name\n"; $passed++; } catch (Throwable $e) { echo "FAIL $name: {$e->getMessage()}\n"; $failed++; } finally { $db->exec("ROLLBACK TO SAVEPOINT $point"); $db->exec("RELEASE SAVEPOINT $point"); } }

function identities(PDO $db): array {
    $teachers=$db->query("SELECT u.id FROM users u JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND tp.can_tutor=1 ORDER BY u.id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
    $students=$db->query("SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id AND r.code='student' JOIN student_profiles sp ON sp.user_id=u.id WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL ORDER BY u.id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    expect(count($teachers)>=1 && count($students)>=2, 'Faltan identidades de prueba activas.');
    return ['teacher'=>(int)$teachers[0], 'other_teacher'=>(int)($teachers[1]??$teachers[0]), 'student'=>(int)$students[0], 'other_student'=>(int)$students[1], 'third_student'=>(int)($students[2]??$students[1])];
}
function fixture(PDO $db, array $ids): array {
    $type=(int)$db->query('SELECT id FROM project_types WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn(); $career=(int)$db->query('SELECT id FROM careers ORDER BY id LIMIT 1')->fetchColumn(); $period=(int)$db->query('SELECT id FROM academic_periods ORDER BY id LIMIT 1')->fetchColumn();
    expect($type>0&&$career>0&&$period>0,'Faltan catálogos de prueba.');
    $code='TST-STUDENT-INFO-'.getmypid().'-'.bin2hex(random_bytes(4));
    $q=$db->prepare("INSERT INTO projects(code,project_type_id,career_id,academic_period_id,title,summary,tutor_id,status,current_stage,created_by) VALUES(:code,:type,:career,:period,'Título original de prueba','Resumen original de prueba con la longitud mínima requerida.',:tutor,'development','registration',:author)");
    $q->execute(['code'=>$code,'type'=>$type,'career'=>$career,'period'=>$period,'tutor'=>$ids['teacher'],'author'=>$ids['student']]); $project=(int)$db->lastInsertId();
    (new ProjectTutoringService())->sync($db,$project,[$ids['teacher']],$ids['teacher'],null);
    (new ProjectAuthorService())->sync($db,$project,[$ids['student'],$ids['other_student']],$ids['student']);
    return ['id'=>$project]+$ids;
}
function payload(array $f): array { return ['title'=>'Título actualizado de prueba','summary'=>'Resumen actualizado de prueba con la longitud mínima requerida.','tutoring_user_ids'=>[$f['teacher']],'tutoring_primary_id'=>$f['teacher'],'author_user_ids'=>[$f['student'],$f['other_student']],'author_leader_id'=>$f['student']]; }

$db->beginTransaction();
try {
    $ids=identities($db); $service=new StudentProjectInformationService();
    caseRun($db,'1 participante activo en development edita información',function()use($db,$ids,$service){$f=fixture($db,$ids);$r=$service->saveInputInTransaction($db,$f['id'],payload($f),$f['student']);expect($r['changed'],'No registró el cambio.');expect((string)$db->query("SELECT title FROM projects WHERE id={$f['id']}")->fetchColumn()==='Título actualizado de prueba','No actualizó título.');});
    caseRun($db,'2 estudiante ajeno es rechazado',function()use($db,$ids,$service){$f=fixture($db,$ids);expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],payload($f),$f['third_student']),403);});
    caseRun($db,'3 participante inactivo es rechazado',function()use($db,$ids,$service){$f=fixture($db,$ids);$db->prepare("UPDATE project_participants SET status='inactive',removed_at=UTC_TIMESTAMP() WHERE project_id=:p AND user_id=:u AND role_code='student'")->execute(['p'=>$f['id'],'u'=>$f['student']]);expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],payload($f),$f['student']),403);});
    foreach(['under_review','corrections_requested','approved'] as $status) caseRun($db,"estado $status es rechazado",function()use($db,$ids,$service,$status){$f=fixture($db,$ids);$db->prepare('UPDATE projects SET status=:s WHERE id=:id')->execute(['s'=>$status,'id'=>$f['id']]);expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],payload($f),$f['student']),422);});
    caseRun($db,'7 campos fuera de whitelist no se modifican',function()use($db,$ids,$service){$f=fixture($db,$ids);$input=payload($f)+['status'=>'approved','code'=>'HACK','project_type_id'=>999];$service->saveInputInTransaction($db,$f['id'],$input,$f['student']);$row=$db->query("SELECT code,status FROM projects WHERE id={$f['id']}")->fetch();expect($row['code']!=='HACK'&&$row['status']==='development','Se modificó un campo no permitido.');});
    caseRun($db,'8 tutor inválido no deja cambios',function()use($db,$ids,$service){$f=fixture($db,$ids);$input=payload($f);$input['tutoring_user_ids']=[999999];$input['tutoring_primary_id']=999999;expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],$input,$f['student']),422);expect((string)$db->query("SELECT title FROM projects WHERE id={$f['id']}")->fetchColumn()==='Título original de prueba','Quedó un cambio parcial.');});
    caseRun($db,'9 autores inválidos son rechazados',function()use($db,$ids,$service){$f=fixture($db,$ids);$input=payload($f);$input['author_user_ids']=[999999];$input['author_leader_id']=999999;expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],$input,$f['student']),422);});
    caseRun($db,'10 título inválido no modifica',function()use($db,$ids,$service){$f=fixture($db,$ids);$input=payload($f);$input['title']='abc';expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],$input,$f['student']),422);expect((string)$db->query("SELECT title FROM projects WHERE id={$f['id']}")->fetchColumn()==='Título original de prueba','Título inválido produjo escritura.');});
    caseRun($db,'10b descripción vacía es rechazada',function()use($db,$ids,$service){$f=fixture($db,$ids);$input=payload($f);$input['summary']='';expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],$input,$f['student']),422);});
    caseRun($db,'10c descripción menor de 30 caracteres es rechazada',function()use($db,$ids,$service){$f=fixture($db,$ids);$input=payload($f);$input['summary']='Descripción demasiado corta';expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],$input,$f['student']),422);});
    caseRun($db,'10d título mayor de 240 caracteres es rechazado',function()use($db,$ids,$service){$f=fixture($db,$ids);$input=payload($f);$input['title']=str_repeat('a',241);expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],$input,$f['student']),422);});
    caseRun($db,'11 rollback exterior revierte tutor ante fallo posterior',function()use($db,$ids,$service){$f=fixture($db,$ids);$db->exec('SAVEPOINT student_info_rollback_check');$input=payload($f);$input['tutoring_user_ids']=[$f['other_teacher']];$input['tutoring_primary_id']=$f['other_teacher'];$input['author_user_ids']=[999999];$input['author_leader_id']=999999;try{$service->saveInputInTransaction($db,$f['id'],$input,$f['student']);}catch(StudentProjectInformationException|ProjectAuthorException){} $db->exec('ROLLBACK TO SAVEPOINT student_info_rollback_check');$db->exec('RELEASE SAVEPOINT student_info_rollback_check');expect((int)$db->query("SELECT tutor_id FROM projects WHERE id={$f['id']}")->fetchColumn()===$f['teacher'],'El rollback no restauró el tutor.');});
    caseRun($db,'12 auditoría conserva actor de sesión',function()use($db,$ids,$service){$f=fixture($db,$ids);$service->saveInputInTransaction($db,$f['id'],payload($f),$f['student']);expect((int)$db->query("SELECT user_id FROM project_audit_log WHERE project_id={$f['id']} AND action='project_updated' ORDER BY id DESC LIMIT 1")->fetchColumn()===$f['student'],'Actor de auditoría incorrecto.');});
    caseRun($db,'13 historial académico recupera project_updated',function()use($db,$ids,$service){$f=fixture($db,$ids);$service->saveInputInTransaction($db,$f['id'],payload($f),$f['student']);$events=(new ProjectAcademicTimelineService($db))->page($f['id'],0,50)['events'];expect((bool)array_filter($events,fn(array $e)=>(string)$e['event_type']==='project_updated'),'El historial no recuperó el evento.');});
    caseRun($db,'14 mismos valores no crean auditoría',function()use($db,$ids,$service){$f=fixture($db,$ids);$same=['title'=>'Título original de prueba','summary'=>'Resumen original de prueba con la longitud mínima requerida.','tutoring_user_ids'=>[$f['teacher']],'tutoring_primary_id'=>$f['teacher'],'author_user_ids'=>[$f['student'],$f['other_student']],'author_leader_id'=>$f['student']];$r=$service->saveInputInTransaction($db,$f['id'],$same,$f['student']);expect(empty($r['changed']),'Detectó cambios inexistentes.');expect((int)$db->query("SELECT COUNT(*) FROM project_audit_log WHERE project_id={$f['id']} AND action='project_updated'")->fetchColumn()===0,'Generó historial engañoso.');});
    caseRun($db,'16 estudiante intenta eliminarse de author_user_ids (auto-eliminación rechazada)',function()use($db,$ids,$service){$f=fixture($db,$ids);$input=payload($f);$input['author_user_ids']=[$f['other_student']];$input['author_leader_id']=$f['other_student'];expectFailure(fn()=>$service->saveInputInTransaction($db,$f['id'],$input,$f['student']),422);$q=$db->prepare("SELECT status FROM project_participants WHERE project_id=:p AND user_id=:u AND role_code='student'");$q->execute(['p'=>$f['id'],'u'=>$f['student']]);expect((string)$q->fetchColumn()==='active','El estudiante fue desactivado en auto-eliminación.');expect((int)$db->query("SELECT COUNT(*) FROM project_audit_log WHERE project_id={$f['id']} AND action='project_updated'")->fetchColumn()===0,'Auto-eliminación generó auditoría.');});
} finally { $db->rollBack(); }

echo "Resultado: $passed OK, $failed FAIL\n";
exit($failed ? 1 : 0);
