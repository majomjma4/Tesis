<?php
declare(strict_types=1);
final class AdminProjectModel
{
    private const STATUSES=['development','under_review','changes_required','approved','completed','published'];
    public function listing(array $f,array $pagination=[]):array
    {
        $w=['p.deleted_at IS NULL'];
        $x=[];
        if($f['search']!==''){
            $w[]='(p.title LIKE :q_title OR p.code LIKE :q_code OR u.full_name LIKE :q_tutor)';
            $term='%'.$f['search'].'%';
            $x['q_title']=$term;
            $x['q_code']=$term;
            $x['q_tutor']=$term;
        }
        if(in_array($f['status'],self::STATUSES,true)){$w[]='p.status=:s';$x['s']=$f['status'];}
        if(($f['group']??'')==='finished')$w[]="p.status IN ('approved','completed','published')";
        if(($f['attention']??'')==='observations')$w[]="EXISTS(SELECT 1 FROM project_observations po WHERE po.project_id=p.id AND po.status='pending')";
        if($f['type_id']>0){$w[]='p.project_type_id=:t';$x['t']=$f['type_id'];}
        if($f['period_id']>0){$w[]='p.academic_period_id=:a';$x['a']=$f['period_id'];}
        $from=" FROM projects p JOIN project_types pt ON pt.id=p.project_type_id JOIN careers c ON c.id=p.career_id JOIN academic_periods ap ON ap.id=p.academic_period_id LEFT JOIN users u ON u.id=p.tutor_id WHERE ".implode(' AND ',$w);
        $sql="SELECT p.id,p.code,p.title,p.subtitle,p.status,p.project_type_id,p.career_id,p.academic_period_id,p.tutor_id,p.updated_at,pt.name type_name,c.name career_name,ap.name period_name,u.full_name tutor_name,(SELECT COUNT(*) FROM project_participants pp WHERE pp.project_id=p.id AND pp.status='active') participant_count".$from.' ORDER BY p.updated_at DESC';
        return PaginationService::run(Database::connection(),'SELECT COUNT(*)'.$from,$sql,$x,$pagination?:PaginationService::request());
    }
    public function summary():array{$r=Database::connection()->query("SELECT COUNT(*) total,SUM(status='development') development,SUM(status IN ('under_review','changes_required')) review,SUM(status IN ('completed','published')) completed FROM projects WHERE deleted_at IS NULL")->fetch()?:[];return array_map('intval',$r);}
    public function catalogs():array{$d=Database::connection();return ['types'=>$d->query('SELECT id,name FROM project_types WHERE is_active=1 ORDER BY name')->fetchAll(),'careers'=>$d->query('SELECT id,name FROM careers WHERE is_active=1 ORDER BY name')->fetchAll(),'periods'=>$d->query("SELECT id,name FROM academic_periods WHERE status IN ('active','planned') ORDER BY starts_on DESC")->fetchAll(),'teachers'=>$d->query("SELECT u.id,u.full_name FROM users u JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.status='active' AND tp.can_tutor=1 ORDER BY u.full_name")->fetchAll()];}
    public function save(array $v,int $id,int $actor):int{$this->validate($v);return Database::transaction(function(PDO $d)use($v,$id,$actor):int{$tutor=$v['tutor_id']?:null;if($id){$q=$d->prepare('SELECT id,title,status FROM projects WHERE id=:id AND deleted_at IS NULL');$q->execute(['id'=>$id]);$before=$q->fetch();if(!$before)throw new InvalidArgumentException('El proyecto ya no existe.');$q=$d->prepare('UPDATE projects SET title=:title,subtitle=:subtitle,project_type_id=:type,career_id=:career,academic_period_id=:period,tutor_id=:tutor,status=:status WHERE id=:id');$q->execute(['title'=>$v['title'],'subtitle'=>$v['subtitle']?:null,'type'=>$v['project_type_id'],'career'=>$v['career_id'],'period'=>$v['academic_period_id'],'tutor'=>$tutor,'status'=>$v['status'],'id'=>$id]);(new ProjectAuditService($d))->record($id,$actor,'project_updated','project',$id,$before,$v);return $id;}$code='PRY-'.date('Y').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);$q=$d->prepare("INSERT INTO projects(code,project_type_id,career_id,academic_period_id,title,subtitle,tutor_id,status,current_stage,created_by) VALUES(:code,:type,:career,:period,:title,:subtitle,:tutor,:status,'registration',:creator)");$q->execute(['code'=>$code,'type'=>$v['project_type_id'],'career'=>$v['career_id'],'period'=>$v['academic_period_id'],'title'=>$v['title'],'subtitle'=>$v['subtitle']?:null,'tutor'=>$tutor,'status'=>$v['status'],'creator'=>$actor]);$id=(int)$d->lastInsertId();(new ProjectAuditService($d))->record($id,$actor,'project_created','project',$id,null,$v);return $id;});}
    public function trash(int $id,string $reason,int $actor):void{if($id<1||mb_strlen(trim($reason))<5)throw new InvalidArgumentException('Indica brevemente el motivo de eliminación.');Database::transaction(function(PDO $d)use($id,$reason,$actor):void{$q=$d->prepare('SELECT id,title,status FROM projects WHERE id=:id AND deleted_at IS NULL');$q->execute(['id'=>$id]);$before=$q->fetch();if(!$before)throw new InvalidArgumentException('El proyecto ya no está disponible.');$d->prepare('UPDATE projects SET deleted_at=CURRENT_TIMESTAMP,deleted_by=:actor,deletion_reason=:reason WHERE id=:id')->execute(['actor'=>$actor,'reason'=>trim($reason),'id'=>$id]);(new ProjectAuditService($d))->record($id,$actor,'project_trashed','project',$id,$before,['deleted'=>true],trim($reason));});}
    private function validate(array $v):void{if(mb_strlen($v['title'])<5)throw new InvalidArgumentException('Ingresa un título de al menos cinco caracteres.');if($v['project_type_id']<1||$v['career_id']<1||$v['academic_period_id']<1)throw new InvalidArgumentException('Completa tipo, carrera y periodo académico.');if(!in_array($v['status'],self::STATUSES,true))throw new InvalidArgumentException('El estado seleccionado no es válido.');}
}
