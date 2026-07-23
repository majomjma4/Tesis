<?php
declare(strict_types=1);
final class AdminProjectModel
{
    private const STATUSES=['development','under_review','changes_required','approved','defense','tribunal_approved','published'];
    private const STATUS_LABELS=['development'=>'En desarrollo','under_review'=>'En revisión','changes_required'=>'Requiere cambios','approved'=>'Aprobado','defense'=>'En tribunal','tribunal_approved'=>'Aprobado por el Tribunal','published'=>'Publicado'];
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
        if(($f['group']??'')==='finished')$w[]="p.status IN ('approved','defense','tribunal_approved','published')";
        if(($f['attention']??'')==='observations')$w[]="EXISTS(SELECT 1 FROM project_observations po WHERE po.project_id=p.id AND po.status='pending')";
        if($f['type_id']>0){$w[]='p.project_type_id=:t';$x['t']=$f['type_id'];}
        if($f['period_id']>0){$w[]='p.academic_period_id=:a';$x['a']=$f['period_id'];}
        $from=" FROM projects p JOIN project_types pt ON pt.id=p.project_type_id JOIN careers c ON c.id=p.career_id JOIN academic_periods ap ON ap.id=p.academic_period_id LEFT JOIN users u ON u.id=p.tutor_id WHERE ".implode(' AND ',$w);
        $sql="SELECT p.id,p.code,p.title,p.subtitle,p.status,p.project_type_id,p.career_id,p.academic_period_id,p.tutor_id,p.updated_at,pt.name type_name,c.name career_name,ap.name period_name,u.full_name tutor_name,(SELECT COUNT(*) FROM project_participants pp WHERE pp.project_id=p.id AND pp.status='active') participant_count".$from.' ORDER BY p.updated_at DESC';
        return PaginationService::run(Database::connection(),'SELECT COUNT(*)'.$from,$sql,$x,$pagination?:PaginationService::request());
    }
    public function summary():array{$r=Database::connection()->query("SELECT COUNT(*) total,SUM(status='development') development,SUM(status IN ('under_review','changes_required')) review,SUM(status='approved') approved,SUM(status IN ('defense','tribunal_approved')) defense FROM projects WHERE deleted_at IS NULL")->fetch()?:[];return array_map('intval',$r);}
    public function catalogs():array{$d=Database::connection();return ['types'=>$d->query('SELECT id,code,name FROM project_types WHERE is_active=1 ORDER BY name')->fetchAll(),'careers'=>$d->query('SELECT id,name FROM careers WHERE is_active=1 ORDER BY name')->fetchAll(),'periods'=>$d->query("SELECT id,name FROM academic_periods WHERE status IN ('active','planned') ORDER BY (status='active') DESC, starts_on DESC")->fetchAll(),'teachers'=>$d->query("SELECT u.id,u.full_name FROM users u JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.status='active' AND tp.can_tutor=1 ORDER BY u.full_name")->fetchAll()];}
    public function save(array $v,int $id,int $actor):int
    {
        $this->validate($v);
        return Database::transaction(function(PDO $d)use($v,$id,$actor):int{
            $tutor=$v['tutor_id']?:null;
            $type=$d->prepare('SELECT code FROM project_types WHERE id=:id AND is_active=1');
            $type->execute(['id'=>$v['project_type_id']]);
            $typeCode=(string)$type->fetchColumn();
            if($typeCode==='')throw new InvalidArgumentException('El tipo de proyecto ya no está disponible.');
            if($id){
                $q=$d->prepare('SELECT id,code,title,subtitle,status,project_type_id,career_id,academic_period_id,tutor_id,created_at FROM projects WHERE id=:id AND deleted_at IS NULL FOR UPDATE');
                $q->execute(['id'=>$id]);$before=$q->fetch();
                if(!$before)throw new InvalidArgumentException('El proyecto ya no existe.');
                $incoming=['title'=>$v['title'],'subtitle'=>$v['subtitle']?:null,'project_type_id'=>$v['project_type_id'],'career_id'=>$v['career_id'],'academic_period_id'=>$v['academic_period_id'],'tutor_id'=>$tutor,'status'=>$v['status']];
                $changed=[];
                foreach($incoming as $field=>$value){
                    $old=$before[$field]??null;
                    if(in_array($field,['project_type_id','career_id','academic_period_id','tutor_id'],true)){$old=$old===null?null:(int)$old;$value=$value===null?null:(int)$value;}
                    else{$old=$old===null?null:(string)$old;$value=$value===null?null:(string)$value;}
                    if($old!==$value)$changed[$field]=[$old,$value];
                }
                if(!$changed)throw new InvalidArgumentException('No se detectaron cambios en el proyecto.');
                $code=$before['code'];
                if((int)$before['project_type_id']!==$v['project_type_id']){
                    $code=(new ProjectCodeService())->next($d,$v['project_type_id'],$typeCode,(int)date('Y',strtotime($before['created_at'])));
                    if($code!==(string)$before['code'])$changed['code']=[(string)$before['code'],$code];
                }
                $q=$d->prepare('UPDATE projects SET code=:code,title=:title,subtitle=:subtitle,project_type_id=:type,career_id=:career,academic_period_id=:period,tutor_id=:tutor,status=:status WHERE id=:id');
                $q->execute(['code'=>$code,'title'=>$v['title'],'subtitle'=>$v['subtitle']?:null,'type'=>$v['project_type_id'],'career'=>$v['career_id'],'period'=>$v['academic_period_id'],'tutor'=>$tutor,'status'=>$v['status'],'id'=>$id]);
                [$previous,$next,$history]=$this->describeChanges($d,$changed);
                $next['_history_changes']=$history;
                (new ProjectAuditService($d))->record($id,$actor,'project_updated','project',$id,$previous,$next);
                return $id;
            }
            $code=(new ProjectCodeService())->next($d,$v['project_type_id'],$typeCode,(int)date('Y'));
            $q=$d->prepare("INSERT INTO projects(code,project_type_id,career_id,academic_period_id,title,subtitle,tutor_id,status,current_stage,created_by) VALUES(:code,:type,:career,:period,:title,:subtitle,:tutor,:status,'registration',:creator)");
            $q->execute(['code'=>$code,'type'=>$v['project_type_id'],'career'=>$v['career_id'],'period'=>$v['academic_period_id'],'title'=>$v['title'],'subtitle'=>$v['subtitle']?:null,'tutor'=>$tutor,'status'=>$v['status'],'creator'=>$actor]);
            $id=(int)$d->lastInsertId();
            (new ProjectAuditService($d))->record($id,$actor,'project_created','project',$id,null,$v+['code'=>$code]);
            return $id;
        });
    }
    private function describeChanges(PDO $db,array $changes):array
    {
        $labels=['title'=>'Título','subtitle'=>'Descripción breve','project_type_id'=>'Tipo','career_id'=>'Carrera','academic_period_id'=>'Periodo','tutor_id'=>'Tutor','status'=>'Estado','code'=>'Código'];
        $previous=[];$next=[];$history=[];
        foreach($changes as $field=>[$old,$new]){
            $oldLabel=$this->readableValue($db,$field,$old);$newLabel=$this->readableValue($db,$field,$new);
            $key=$labels[$field]??$field;$previous[$key]=$oldLabel;$next[$key]=$newLabel;
            $history[]=['field'=>$key,'verb'=>in_array($field,['subtitle','career_id'],true)?'cambiada':'cambiado','from'=>$oldLabel,'to'=>$newLabel];
        }
        return [$previous,$next,$history];
    }
    private function readableValue(PDO $db,string $field,mixed $value):string
    {
        if($value===null||$value==='')return 'Sin asignar';
        if($field==='status')return self::STATUS_LABELS[(string)$value]??(string)$value;
        $tables=['project_type_id'=>['project_types','name'],'career_id'=>['careers','name'],'academic_period_id'=>['academic_periods','name'],'tutor_id'=>['users','full_name']];
        if(!isset($tables[$field]))return (string)$value;
        [$table,$column]=$tables[$field];$q=$db->prepare("SELECT $column FROM $table WHERE id=:id");$q->execute(['id'=>(int)$value]);
        $label=$q->fetchColumn();
        return $label===false?'Sin asignar':(string)$label;
    }
    public function trash(int $id,string $reason,int $actor):void{if($id<1||mb_strlen(trim($reason))<5)throw new InvalidArgumentException('Indica brevemente el motivo de eliminación.');Database::transaction(function(PDO $d)use($id,$reason,$actor):void{$q=$d->prepare('SELECT id,title,status FROM projects WHERE id=:id AND deleted_at IS NULL');$q->execute(['id'=>$id]);$before=$q->fetch();if(!$before)throw new InvalidArgumentException('El proyecto ya no está disponible.');$d->prepare('UPDATE projects SET deleted_at=CURRENT_TIMESTAMP,deleted_by=:actor,deletion_reason=:reason WHERE id=:id')->execute(['actor'=>$actor,'reason'=>trim($reason),'id'=>$id]);(new ProjectAuditService($d))->record($id,$actor,'project_trashed','project',$id,$before,['deleted'=>true],trim($reason));});}
    private function validate(array $v):void{if(mb_strlen($v['title'])<5)throw new InvalidArgumentException('Ingresa un título de al menos cinco caracteres.');if($v['project_type_id']<1||$v['career_id']<1||$v['academic_period_id']<1)throw new InvalidArgumentException('Completa tipo, carrera y periodo académico.');if(!in_array($v['status'],self::STATUSES,true))throw new InvalidArgumentException('El estado seleccionado no es válido.');if(in_array($v['status'],['defense','tribunal_approved'],true)){$q=Database::connection()->prepare('SELECT code FROM project_types WHERE id=:id');$q->execute(['id'=>$v['project_type_id']]);if($q->fetchColumn()!=='thesis')throw new InvalidArgumentException('Los estados del Tribunal solo corresponden a proyectos de tesis.');}}
}
