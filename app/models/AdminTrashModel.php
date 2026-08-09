<?php
declare(strict_types=1);
final class AdminTrashModel
{
 public function trashSupportMaterial(int $id,string $reason,int $actor,string $reasonCode='',string $reasonDetail=''):void{Database::transaction(fn(PDO $d)=>$this->trashSupportMaterialAtomic($d,$id,$reason,$actor,$reasonCode,$reasonDetail));}
 public function trashSupportMaterialAtomic(PDO $d,int $id,string $reason,int $actor,string $reasonCode='',string $reasonDetail=''):array
 {
  if($id<1||mb_strlen(trim($reason))<5)throw new InvalidArgumentException('Indica un motivo de al menos cinco caracteres.');
  $read=$d->prepare('SELECT sm.id,sm.title,sm.material_type,sm.category_id,category.name category_name,sm.status,sm.is_available,sm.published_at FROM support_materials sm LEFT JOIN support_material_categories category ON category.id=sm.category_id WHERE sm.id=:id AND sm.deleted_at IS NULL AND sm.purged_at IS NULL FOR UPDATE');$read->execute(['id'=>$id]);$material=$read->fetch();
  if(!$material){$exists=$d->prepare('SELECT deleted_at,purged_at FROM support_materials WHERE id=:id');$exists->execute(['id'=>$id]);$state=$exists->fetch();if($state&&$state['deleted_at']!==null&&$state['purged_at']===null)throw new InvalidArgumentException('El material ya se encuentra en Papelera.');throw new InvalidArgumentException('El material cambió de estado antes de completar la operación.');}
  $storedReason=trim($reason).($reasonDetail!==''?': '.trim($reasonDetail):'');
  $update=$d->prepare('UPDATE support_materials SET is_available=0,deleted_at=UTC_TIMESTAMP(),deleted_by=:deleted_by,deletion_reason=:reason,updated_by=:updated_by WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL');$update->execute(['deleted_by'=>$actor,'updated_by'=>$actor,'reason'=>$storedReason,'id'=>$id]);
  if($update->rowCount()!==1)throw new InvalidArgumentException('El material cambió de estado antes de completar la operación.');
  (new AdminActivityService($d))->record($actor,'support_material.trashed','Envió material de apoyo a la Papelera','Papelera','support_material',$id,(string)$material['title'],'correct',[
   'material_id'=>$id,'title'=>(string)$material['title'],'material_type'=>(string)$material['material_type'],
   'category_id'=>(int)$material['category_id'],'category'=>(string)($material['category_name']??''),
   'reason_code'=>$reasonCode,'reason'=>trim($reason),'reason_detail'=>trim($reasonDetail),
   'previous_available'=>(bool)$material['is_available'],'is_available'=>false,
   'previous_status'=>(string)$material['status'],'new_status'=>'Papelera','origin'=>'Repositorio','destination'=>'trash',
  ]);
  return $material;
 }
 public function restoreSupportMaterial(int $id,int $actor):void
 {
  if($id<1)throw new InvalidArgumentException('El material no es válido.');
  Database::transaction(function(PDO $d)use($id,$actor):void{
   $read=$d->prepare('SELECT title,status,is_available,deletion_reason FROM support_materials WHERE id=:id AND deleted_at IS NOT NULL AND purged_at IS NULL FOR UPDATE');
   $read->execute(['id'=>$id]);$material=$read->fetch();
   if(!$material)throw new InvalidArgumentException('El material ya no puede restaurarse.');
   $update=$d->prepare("UPDATE support_materials SET status='published',is_available=1,withdrawn_at=NULL,withdrawn_by=NULL,publication_date=COALESCE(publication_date,UTC_DATE()),published_at=COALESCE(published_at,UTC_TIMESTAMP()),deleted_at=NULL,deleted_by=NULL,deletion_reason=NULL,updated_by=:actor WHERE id=:id AND deleted_at IS NOT NULL AND purged_at IS NULL");
   $update->execute(['actor'=>$actor,'id'=>$id]);
   if($update->rowCount()!==1)throw new RuntimeException('El material cambió de estado antes de completar la restauración.');
   (new AdminActivityService($d))->record($actor,'support_material_restored','Restauró material de apoyo desde la Papelera','Papelera','support_material',$id,(string)$material['title'],'correct',[
    'previous_status'=>'Papelera','restored_from_status'=>(string)$material['status'],'new_status'=>'published',
    'previous_available'=>(bool)$material['is_available'],'is_available'=>true,
    'previous_trash_reason'=>(string)$material['deletion_reason'],
   ]);
  });
 }
  public function supportMaterialDashboard(array $pagination=[]):array{
   $d=Database::connection();
   $settings=(new SystemSettingModel())->all();
   $mDays=max(1,(int)($settings['retention_materials_days']??60));
   $from=' FROM support_materials sm LEFT JOIN users u ON u.id=sm.deleted_by WHERE sm.deleted_at IS NOT NULL AND sm.purged_at IS NULL';
   $data="SELECT sm.id,sm.title,sm.material_type code,sm.deleted_at,sm.deletion_reason,u.full_name deleted_by_name,GREATEST(0,".$mDays."-DATEDIFF(CURRENT_DATE,DATE(sm.deleted_at))) days_left".$from.' ORDER BY sm.deleted_at DESC';
   $result=PaginationService::run($d,'SELECT COUNT(*)'.$from,$data,[],$pagination?:PaginationService::request());
   $expiredStmt=$d->prepare("SELECT COUNT(*) FROM support_materials WHERE deleted_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL :days DAY) AND purged_at IS NULL");
   $expiredStmt->execute(['days'=>$mDays]);
   return ['candidates'=>[],'users'=>[],'projects'=>[],'materials'=>$result['items'],'pagination'=>$result['pagination'],'active_type'=>'materials','summary'=>['users'=>(int)$d->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL AND purged_at IS NULL')->fetchColumn(),'projects'=>(int)$d->query('SELECT COUNT(*) FROM projects WHERE deleted_at IS NOT NULL')->fetchColumn(),'materials'=>(int)$d->query('SELECT COUNT(*) FROM support_materials WHERE deleted_at IS NOT NULL AND purged_at IS NULL')->fetchColumn(),'expired'=>(int)$expiredStmt->fetchColumn()]];
  }
  public function purgeExpiredSupportMaterials(int $actor):int{
   $settings=(new SystemSettingModel())->all();
   $mDays=max(1,(int)($settings['retention_materials_days']??60));
   return Database::transaction(function(PDO $d)use($actor,$mDays):int{
    $q=$d->prepare("SELECT id FROM support_materials WHERE deleted_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL :days DAY) AND purged_at IS NULL FOR UPDATE");
    $q->execute(['days'=>$mDays]);
    $ids=$q->fetchAll(PDO::FETCH_COLUMN);
    if($ids){
     $u=$d->prepare("UPDATE support_materials SET purged_at=UTC_TIMESTAMP(),purged_by=:actor WHERE deleted_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL :days DAY) AND purged_at IS NULL");
     $u->execute(['actor'=>$actor,'days'=>$mDays]);
     (new AdminActivityService($d))->record($actor,'support_material_trash_purged','Purgó materiales de apoyo vencidos','Papelera','support_material',null,'Materiales vencidos','correct',['count'=>count($ids)]);
    }
    return count($ids);
   });
  }
  public function dashboard(string $type='users',array $pagination=[]):array{
   $d=Database::connection();
   $settings=(new SystemSettingModel())->all();
   $uDays=max(1,(int)($settings['retention_users_days']??60));
   $pDays=max(1,(int)($settings['retention_projects_days']??60));
   $type=in_array($type,['users','projects'],true)?$type:'users';
   $candidates=$d->query("SELECT id,full_name,email FROM users WHERE deleted_at IS NULL AND purged_at IS NULL AND status<>'inactive' ORDER BY full_name LIMIT 200")->fetchAll();
   $userCount=(int)$d->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL AND purged_at IS NULL")->fetchColumn();
   $projectCount=(int)$d->query("SELECT COUNT(*) FROM projects WHERE deleted_at IS NOT NULL")->fetchColumn();
   $expUsersStmt=$d->prepare("SELECT COUNT(*) FROM users WHERE deleted_at<DATE_SUB(CURRENT_TIMESTAMP,INTERVAL :u_days DAY) AND purged_at IS NULL");
   $expUsersStmt->execute(['u_days'=>$uDays]);
   $expProjectsStmt=$d->prepare("SELECT COUNT(*) FROM projects WHERE deleted_at<DATE_SUB(CURRENT_TIMESTAMP,INTERVAL :p_days DAY)");
   $expProjectsStmt->execute(['p_days'=>$pDays]);
   $expired=(int)$expUsersStmt->fetchColumn()+(int)$expProjectsStmt->fetchColumn();

   if($type==='projects'){
    $from=" FROM projects p LEFT JOIN users u ON u.id=p.deleted_by WHERE p.deleted_at IS NOT NULL";
    $data="SELECT p.id,p.code,p.title,p.deleted_at,p.deletion_reason,u.full_name deleted_by_name,GREATEST(0,".$pDays."-DATEDIFF(CURRENT_DATE,DATE(p.deleted_at))) days_left".$from.' ORDER BY p.deleted_at DESC';
   }else{
    $from=" FROM users u LEFT JOIN users a ON a.id=u.deleted_by WHERE u.deleted_at IS NOT NULL AND u.purged_at IS NULL";
    $data="SELECT u.id,u.full_name,u.email,u.deleted_at,u.deletion_reason,a.full_name deleted_by_name,GREATEST(0,".$uDays."-DATEDIFF(CURRENT_DATE,DATE(u.deleted_at))) days_left".$from.' ORDER BY u.deleted_at DESC';
   }
   $result=PaginationService::run($d,'SELECT COUNT(*)'.$from,$data,[],$pagination?:PaginationService::request());
   return ['candidates'=>$candidates,'users'=>$type==='users'?$result['items']:[],'projects'=>$type==='projects'?$result['items']:[],'pagination'=>$result['pagination'],'active_type'=>$type,'summary'=>['users'=>$userCount,'projects'=>$projectCount,'expired'=>$expired]];
  }
  public function trashUser(int $id,string $reason,int $actor):void{if($id<1||mb_strlen(trim($reason))<5)throw new InvalidArgumentException('Indica un motivo de al menos cinco caracteres.');Database::transaction(function(PDO $d)use($id,$reason,$actor):void{$read=$d->prepare('SELECT id,status,is_admin,is_initial_admin FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');$read->execute(['id'=>$id]);$user=$read->fetch();if(!$user)throw new InvalidArgumentException('El usuario ya no está disponible.');if((bool)$user['is_admin']&&$user['status']==='active'){$admins=$d->prepare("SELECT COUNT(*) FROM users WHERE is_admin=1 AND status='active' AND deleted_at IS NULL AND purged_at IS NULL AND id<>:id");$admins->execute(['id'=>$id]);if((int)$admins->fetchColumn()<1)throw new InvalidArgumentException(AdminUserModel::LAST_ADMIN_MESSAGE);}$q=$d->prepare('UPDATE users SET status=\'inactive\',deleted_at=CURRENT_TIMESTAMP,deleted_by=:actor,deletion_reason=:reason,session_version=session_version+1 WHERE id=:id');$q->execute(['actor'=>$actor,'reason'=>trim($reason),'id'=>$id]);$this->audit($d,$actor,'user_trashed','user',$id,['reason'=>trim($reason)]);if((bool)$user['is_initial_admin'])$this->audit($d,$actor,'initial_admin_deactivated','user',$id,['reason'=>trim($reason),'via'=>'trash']);});}
  public function restore(string $entity,int $id,int $actor):void{if(!in_array($entity,['user','project'],true)||$id<1)throw new InvalidArgumentException('El elemento no es válido.');Database::transaction(function(PDO $d)use($entity,$id,$actor):void{$restoredAdmin=false;if($entity==='user'){$read=$d->prepare('SELECT is_admin FROM users WHERE id=:id AND deleted_at IS NOT NULL AND purged_at IS NULL FOR UPDATE');$read->execute(['id'=>$id]);$restoredAdmin=(bool)$read->fetchColumn();$q=$d->prepare("UPDATE users SET status='active',deleted_at=NULL,deleted_by=NULL,deletion_reason=NULL,session_version=session_version+1 WHERE id=:id AND deleted_at IS NOT NULL AND purged_at IS NULL");}else{$q=$d->prepare('UPDATE projects SET deleted_at=NULL,deleted_by=NULL,deletion_reason=NULL WHERE id=:id AND deleted_at IS NOT NULL');}$q->execute(['id'=>$id]);if($q->rowCount()!==1)throw new InvalidArgumentException('El elemento ya no puede restaurarse.');$this->audit($d,$actor,$entity.'_restored',$entity,$id,[]);if($restoredAdmin)$this->audit($d,$actor,'admin_access_reactivated','user',$id,['via'=>'trash_restore']);});}
  public function purgeExpired(int $actor):array{
   $settings=(new SystemSettingModel())->all();
   $uDays=max(1,(int)($settings['retention_users_days']??60));
   $pDays=max(1,(int)($settings['retention_projects_days']??60));
   return Database::transaction(function(PDO $d)use($actor,$uDays,$pDays):array{
    $uQ=$d->prepare("SELECT id FROM users WHERE deleted_at<DATE_SUB(CURRENT_TIMESTAMP,INTERVAL :u_days DAY) AND purged_at IS NULL FOR UPDATE");
    $uQ->execute(['u_days'=>$uDays]);
    $users=$uQ->fetchAll(PDO::FETCH_COLUMN);
    foreach($users as $id){
     $d->prepare('DELETE FROM student_enrollments WHERE student_id=:id')->execute(['id'=>$id]);
     $d->prepare('DELETE FROM student_profiles WHERE user_id=:id')->execute(['id'=>$id]);
     $d->prepare('DELETE FROM teacher_profiles WHERE user_id=:id')->execute(['id'=>$id]);
     $d->prepare('DELETE FROM user_roles WHERE user_id=:id')->execute(['id'=>$id]);
     $d->prepare("UPDATE users SET email=CONCAT('deleted-',id,'@invalid.local'),username=NULL,full_name='Usuario eliminado',password_hash=:hash,status='inactive',purged_at=CURRENT_TIMESTAMP,session_version=session_version+1 WHERE id=:id")->execute(['hash'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'id'=>$id]);
    }
    $pQ=$d->prepare("SELECT id FROM projects WHERE deleted_at<DATE_SUB(CURRENT_TIMESTAMP,INTERVAL :p_days DAY) FOR UPDATE");
    $pQ->execute(['p_days'=>$pDays]);
    $projects=$pQ->fetchAll(PDO::FETCH_COLUMN);
    foreach($projects as $id)$d->prepare('DELETE FROM projects WHERE id=:id')->execute(['id'=>$id]);
    $this->audit($d,$actor,'trash_purged','trash',null,['users'=>count($users),'projects'=>count($projects)]);
    return ['users'=>count($users),'projects'=>count($projects)];
   });
  }
 private function audit(PDO $d,int $actor,string $action,string $type,?int $id,array $details):void
 {
  $element=$type==='trash'?'Elementos vencidos de la papelera':ucfirst($type).' #'.(int)$id;
  if($id&&in_array($type,['user','project'],true)){
   $column=$type==='user'?'full_name':'title';$table=$type==='user'?'users':'projects';
   $q=$d->prepare("SELECT $column FROM $table WHERE id=:id");$q->execute(['id'=>$id]);$element=(string)($q->fetchColumn()?:$element);
  }
  $labels=['user_trashed'=>'Envió un usuario a la papelera','user_restored'=>'Restauró un usuario desde la papelera','project_restored'=>'Restauró un proyecto desde la papelera','trash_purged'=>'Ejecutó la eliminación definitiva de elementos vencidos','initial_admin_deactivated'=>'Desactivó la cuenta administrativa inicial','admin_access_reactivated'=>'Reactivó una cuenta con acceso administrativo'];
  (new AdminActivityService($d))->record($actor,$action,$labels[$action]??$action,'Papelera',$type,$id,$element,'correct',$details);
 }
}
