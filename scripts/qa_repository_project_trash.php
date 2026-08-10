<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__)); define('APP_PATH', ROOT_PATH.'/app');
$GLOBALS['config']=require APP_PATH.'/config/app.php'; require APP_PATH.'/helpers.php'; require APP_PATH.'/Core/Autoloader.php'; Autoloader::register();

$db=Database::connection();$ids=[];$tag='QA_REPO_TRASH_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3));
try{
 $actor=(int)$db->query("SELECT id FROM users WHERE is_admin=1 AND status='active' AND deleted_at IS NULL LIMIT 1")->fetchColumn();$type=(int)$db->query("SELECT id FROM project_types WHERE is_active=1 AND code<>'thesis' LIMIT 1")->fetchColumn();$career=(int)$db->query('SELECT id FROM careers WHERE is_active=1 LIMIT 1')->fetchColumn();$period=(int)$db->query("SELECT id FROM academic_periods WHERE status='active' LIMIT 1")->fetchColumn();if(!$actor||!$type||!$career||!$period)throw new RuntimeException('Catálogos QA no disponibles.');
 $create=function(bool $available,bool $withdrawn)use($db,$actor,$type,$career,$period,$tag,&$ids):int{$db->prepare("INSERT INTO projects(code,project_type_id,career_id,academic_period_id,title,status,published_at,is_available,withdrawn_at,withdrawn_by,created_by) VALUES(?,?,?,?,?,'published',UTC_TIMESTAMP(),?,?,?,?)")->execute([$tag.'_'.count($ids),$type,$career,$period,$tag,$available?1:0,$withdrawn?gmdate('Y-m-d H:i:s'):null,$withdrawn?$actor:null,$actor]);$id=(int)$db->lastInsertId();$ids[]=$id;return $id;};
 $trash=new AdminTrashModel();
 foreach([[true,false],[false,false],[false,true]] as [$available,$withdrawn]){
  $id=$create($available,$withdrawn);$before=$db->query('SELECT status,published_at,is_available,withdrawn_at,withdrawn_by FROM projects WHERE id='.$id)->fetch();
  $trash->trashRepositoryProject($id,'Fixture QA para validar Papelera',$actor);$after=$db->query('SELECT status,published_at,is_available,withdrawn_at,withdrawn_by,deleted_at,deletion_reason FROM projects WHERE id='.$id)->fetch();
  if($after['deleted_at']===null||$after['status']!==$before['status']||$after['published_at']!==$before['published_at']||(int)$after['is_available']!==(int)$before['is_available']||$after['withdrawn_at']!==$before['withdrawn_at']||$after['withdrawn_by']!==$before['withdrawn_by'])throw new RuntimeException('Papelera alteró el estado de publicación.');
  try{$trash->trashRepositoryProject($id,'Segundo intento QA',$actor);throw new RuntimeException('El doble envío no fue rechazado.');}catch(InvalidArgumentException $expected){}
  $trash->restore('project',$id,$actor);$restored=$db->query('SELECT status,published_at,is_available,withdrawn_at,withdrawn_by,deleted_at FROM projects WHERE id='.$id)->fetch();
  if($restored['deleted_at']!==null||$restored['status']!==$before['status']||$restored['published_at']!==$before['published_at']||(int)$restored['is_available']!==(int)$before['is_available']||$restored['withdrawn_at']!==$before['withdrawn_at']||$restored['withdrawn_by']!==$before['withdrawn_by'])throw new RuntimeException('La restauración no conservó el estado previo.');
 }
 try{$id=$create(true,false);$trash->trashRepositoryProject($id,'   ',$actor);throw new RuntimeException('El motivo vacío no fue rechazado.');}catch(InvalidArgumentException $expected){}
 echo json_encode(['ok'=>true,'published_available'=>true,'published_unavailable'=>true,'withdrawn'=>true,'restore'=>true,'double_send'=>true,'reason_validation'=>true],JSON_UNESCAPED_UNICODE).PHP_EOL;
}finally{foreach($ids as $id){$db->prepare('DELETE FROM project_audit_log WHERE project_id=?')->execute([$id]);$db->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);}}
