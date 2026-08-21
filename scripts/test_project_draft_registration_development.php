<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__)); define('APP_PATH', ROOT_PATH.'/app');
require APP_PATH.'/helpers.php'; require APP_PATH.'/Core/Autoloader.php'; Autoloader::register();
function expectRegistration(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}

$db=Database::connection();$projects=[];$directories=[];$actor=0;$sequenceType=0;$sequenceBefore=null;$storage=new ProjectDraftStorageService($db);
try{
    $actor=(int)$db->query("SELECT u.id FROM users u INNER JOIN student_profiles sp ON sp.user_id=u.id INNER JOIN student_enrollments se ON se.student_id=u.id AND se.career_id=sp.career_id AND se.status='active' INNER JOIN academic_periods ap ON ap.id=se.academic_period_id AND ap.status='active' INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='student' LEFT JOIN project_drafts d ON d.user_id=u.id AND d.expires_at>UTC_TIMESTAMP() WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND d.id IS NULL ORDER BY u.id LIMIT 1")->fetchColumn();
    expectRegistration($actor>0,'No existe un estudiante apto sin borrador.');
    $policy=['role'=>'student','actor_type'=>'student','can_create'=>true,'auto_leader'=>true,'can_add_students'=>true,'must_select_leader'=>false,'can_configure_full_team'=>false,'student_tutor_mode'=>'proposed','can_self_assign_tutor'=>false];
    $draftService=new ProjectDraftService();$catalogs=$draftService->catalogs($actor,$policy);$type=$catalogs['types']['pis']??null;$tutor=$catalogs['teachers'][0]??null;
    expectRegistration($type!==null&&!empty($type['enabled'])&&$tutor!==null,'Faltan catálogos para la prueba.');
    $sequenceType=(int)$type['id'];$q=$db->prepare('SELECT next_number FROM project_code_sequences WHERE project_type_id=:type AND code_year=:year');$q->execute(['type'=>$sequenceType,'year'=>(int)date('Y')]);$before=$q->fetchColumn();$sequenceBefore=$before===false?null:(int)$before;
    foreach([0,1,2] as $fileCount){
        $draft=$storage->save($actor,['type'=>'pis','title'=>'Registro en desarrollo de prueba','description'=>'Descripción válida para verificar el estado inicial en desarrollo.','period'=>(string)$catalogs['active_period']['code'],'modality'=>'','research_line'=>'','tutor_id'=>(string)$tutor['id'],'members'=>[(string)$actor],'tags'=>[]]);
        $draftId=(string)$draft['id'];$directory=ROOT_PATH.'/storage/private/project-drafts/'.$actor.'/'.$draftId;if($fileCount>0&&!is_dir($directory))mkdir($directory,0775,true);
        for($i=1;$i<=$fileCount;$i++){$name=bin2hex(random_bytes(32)).'.pdf';$path=$directory.DIRECTORY_SEPARATOR.$name;file_put_contents($path,"%PDF-1.4\nRegistro $i\n%%EOF\n");$insert=$db->prepare("INSERT INTO project_draft_files(draft_id,user_id,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256) VALUES(:draft,:user,:original,:storage,:path,'application/pdf','pdf',:size,:checksum)");$insert->execute(['draft'=>$draftId,'user'=>$actor,'original'=>'documento-'.$i.'.pdf','storage'=>$name,'path'=>'storage/private/project-drafts/'.$actor.'/'.$draftId.'/'.$name,'size'=>(int)filesize($path),'checksum'=>hash_file('sha256',$path)]);}
        $registered=(new ProjectDraftRegistrationService())->register($actor,$policy,$draftId);$project=(int)$registered['project_id'];$projects[]=$project;$directories[]=ROOT_PATH.'/storage/private/projects/'.$project;
        $status=$db->query("SELECT status FROM projects WHERE id=$project")->fetchColumn();expectRegistration($status==='development',"El registro con $fileCount archivo(s) no quedó en development.");echo "OK   registro con $fileCount archivo(s) queda en development\n";
    }
}finally{
    foreach($projects as $project)$db->prepare('DELETE FROM projects WHERE id=:id')->execute(['id'=>$project]);
    if($sequenceType>0){if($sequenceBefore===null)$db->prepare('DELETE FROM project_code_sequences WHERE project_type_id=:type AND code_year=:year')->execute(['type'=>$sequenceType,'year'=>(int)date('Y')]);else$db->prepare('UPDATE project_code_sequences SET next_number=:next WHERE project_type_id=:type AND code_year=:year')->execute(['next'=>$sequenceBefore,'type'=>$sequenceType,'year'=>(int)date('Y')]);}
    if($actor>0)try{$storage->delete($actor);}catch(Throwable){}
    foreach($directories as $directory)if(is_dir($directory)){foreach(glob($directory.DIRECTORY_SEPARATOR.'*')?:[] as $file)if(is_file($file))unlink($file);@rmdir($directory);}
}
echo "Resultado: 3 OK, 0 FAIL\n";
