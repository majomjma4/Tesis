<?php
declare(strict_types=1);

final class ThesisTribunalException extends InvalidArgumentException { public function __construct(string $message, private int $status=422){parent::__construct($message);} public function httpStatus():int{return $this->status;} }

final class ThesisTribunalService
{
    private const MIN_MEMBERS=3; private const MAX_MEMBERS=5;
    public static function isValidMemberCount(int $count): bool { return $count >= self::MIN_MEMBERS && $count <= self::MAX_MEMBERS; }
    public static function memberRangeLabel(): string { return self::MIN_MEMBERS.' y '.self::MAX_MEMBERS; }

    /** @return list<array<string,mixed>> */
    public function candidates(int $projectId): array
    {
        $db=Database::connection(); $project=$this->project($db,$projectId,false); if(!in_array((string)$project['status'],['approved','defense'],true))throw new ThesisTribunalException('El Tribunal no puede gestionarse en el estado actual del proyecto.'); $excluded=$this->incompatibleIds($db,$projectId,(int)($project['tutor_id']??0));
        $params=[]; $sql="SELECT DISTINCT u.id,u.full_name,u.email,tp.academic_title,tp.institutional_code
          FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id AND r.code='teacher'
          JOIN teacher_profiles tp ON tp.user_id=u.id
          WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL";
        if($excluded){$sql.=' AND u.id NOT IN ('.implode(',',array_fill(0,count($excluded),'?')).')';$params=$excluded;}
        $sql.=' ORDER BY u.full_name';$q=$db->prepare($sql);$q->execute($params);return $q->fetchAll();
    }

    /** @return array<string,mixed> */
    public function save(int $projectId,string $expectedStatus,array $memberIds,string $reason,int $actor): array
    {
        return Database::transaction(fn(PDO $db)=>$this->saveInTransaction($db,$projectId,$expectedStatus,$memberIds,$reason,$actor));
    }

    /** @return array<string,mixed> */
    public function saveInTransaction(PDO $db,int $projectId,string $expectedStatus,array $memberIds,string $reason,int $actor): array
    {
        $project=$this->project($db,$projectId,true);$status=(string)$project['status'];
        if(!in_array($status,['approved','defense'],true))throw new ThesisTribunalException('El Tribunal solo puede gestionarse mientras el proyecto está aprobado o en defensa.');
        if($expectedStatus!==$status)throw new ThesisTribunalException('El estado del proyecto cambió. Recarga el proceso antes de continuar.',409);
        $ids=array_values(array_unique(array_filter(array_map('intval',$memberIds),fn($id)=>$id>0)));sort($ids);
        if(!self::isValidMemberCount(count($ids)))throw new ThesisTribunalException('Selecciona entre '.self::memberRangeLabel().' docentes para conformar el Tribunal.');
        $participants=$db->prepare("SELECT pp.id,pp.user_id,LOWER(pp.role_code) role_code,pp.status,pp.removed_at,u.full_name FROM project_participants pp JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:project AND (LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor','tribunal','jury') OR pp.user_id=:tutor) FOR UPDATE");
        $participants->execute(['project'=>$projectId,'tutor'=>(int)($project['tutor_id']??0)]);$rows=$participants->fetchAll();
        $incompatible=[];$current=[];$stored=[];foreach($rows as $row){$role=(string)$row['role_code'];$id=(int)$row['user_id'];if($id===(int)($project['tutor_id']??0)||in_array($role,['tutor','cotutor','co_tutor','co-tutor'],true))$incompatible[]=$id;if(in_array($role,['tribunal','jury'],true)){$stored[$id]=$row;if($row['status']==='active'&&empty($row['removed_at']))$current[$id]=['user_id'=>$id,'name'=>(string)$row['full_name']];}}
        if(array_intersect($ids,$incompatible))throw new ThesisTribunalException('El Tutor o Cotutor del proyecto no puede formar parte del Tribunal.');
        $marks=implode(',',array_fill(0,count($ids),'?'));$valid=$db->prepare("SELECT DISTINCT u.id,u.full_name FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id AND r.code='teacher' JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.id IN ($marks) AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL FOR UPDATE");$valid->execute($ids);$validRows=$valid->fetchAll();if(count($validRows)!==count($ids))throw new ThesisTribunalException('Uno o más integrantes seleccionados no son docentes activos válidos.');
        $next=[];foreach($validRows as $row)$next[(int)$row['id']]=['user_id'=>(int)$row['id'],'name'=>(string)$row['full_name']];ksort($next);$currentIds=array_keys($current);sort($currentIds);if($currentIds===$ids)throw new ThesisTribunalException('No se detectaron cambios en la composición del Tribunal.');
        $reason=trim($reason);if($status==='defense'&&(mb_strlen($reason)<5||mb_strlen($reason)>500))throw new ThesisTribunalException('Indica un motivo de entre 5 y 500 caracteres para modificar el Tribunal durante la defensa.');
        $added=array_values(array_diff_key($next,$current));$removed=array_values(array_diff_key($current,$next));$retained=array_values(array_intersect_key($next,$current));
        if($current){$disable=$db->prepare("UPDATE project_participants SET status='inactive',removed_at=CURRENT_TIMESTAMP WHERE project_id=:project AND status='active' AND removed_at IS NULL AND LOWER(role_code) IN ('tribunal','jury')");$disable->execute(['project'=>$projectId]);}
        $find=$db->prepare("SELECT id FROM project_participants WHERE project_id=:project AND user_id=:user AND LOWER(role_code) IN ('tribunal','jury') ORDER BY id DESC LIMIT 1 FOR UPDATE");$reactivate=$db->prepare("UPDATE project_participants SET role_code='tribunal',permission_level='review',status='active',removed_at=NULL,assigned_at=CURRENT_TIMESTAMP WHERE id=:id");$insert=$db->prepare("INSERT INTO project_participants(project_id,user_id,role_code,permission_level,is_leader,status,removed_at) VALUES(:project,:user,'tribunal','review',0,'active',NULL)");
        foreach($ids as $id){$find->execute(['project'=>$projectId,'user'=>$id]);$rowId=(int)$find->fetchColumn();if($rowId)$reactivate->execute(['id'=>$rowId]);else $insert->execute(['project'=>$projectId,'user'=>$id]);}
        $action=$current?'tribunal_updated':'tribunal_assigned';$before=['members'=>array_values($current),'status'=>$status];$after=['members'=>array_values($next),'added'=>$added,'removed'=>$removed,'retained'=>$retained,'status'=>$status,'reason'=>$reason?:null];(new ProjectAuditService($db))->record($projectId,$actor,$action,'project_participants',$projectId,$before,$after,$reason?:null);
        (new ProjectAcademicNotificationService())->tribunalAssigned($db,$projectId,(string)$project['code'],(string)$project['title'],$added,$actor);
        return ['member_count'=>count($ids),'members'=>array_values($next),'action'=>$action,'status'=>$status];
    }

    private function project(PDO $db,int $id,bool $lock):array{$q=$db->prepare("SELECT p.id,p.code,p.title,p.status,p.tutor_id,pt.code type_code FROM projects p JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id AND p.deleted_at IS NULL".($lock?' FOR UPDATE':''));$q->execute(['id'=>$id]);$p=$q->fetch();if(!$p||(string)$p['type_code']!=='thesis')throw new ThesisTribunalException('El proceso de Titulación solicitado no está disponible.',404);return $p;}
    /** @return list<int> */ private function incompatibleIds(PDO $db,int $project,int $tutor):array{$q=$db->prepare("SELECT user_id FROM project_participants WHERE project_id=:project AND status='active' AND removed_at IS NULL AND LOWER(role_code) IN ('tutor','cotutor','co_tutor','co-tutor')");$q->execute(['project'=>$project]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if($tutor>0)$ids[]=$tutor;return array_values(array_unique($ids));}
}
