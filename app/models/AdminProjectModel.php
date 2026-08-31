<?php
declare(strict_types=1);
final class AdminProjectModel
{
    private const STATUSES=ProjectCapabilityService::INSTITUTIONAL_ACTIVE_STATUSES;
    private const WRITABLE_STATUSES=['development','under_review','approved','defense','tribunal_approved','published'];
    private const PROJECT_KEYWORD_EXCLUSIONS=['reglamento','normativa','plantilla','formato','guía documental','manual','tutorial'];
    private const STATUS_LABELS=['development'=>'En desarrollo','under_review'=>'En revisión','approved'=>'Aprobado','defense'=>'En tribunal','tribunal_approved'=>'Aprobado por el Tribunal','published'=>'Publicado'];
    public function listing(array $f,array $pagination=[]):array
    {
        [$from,$x]=$this->filteredQuery($f);
        $sql="SELECT p.id,p.code,p.title,p.subtitle,p.status,p.project_type_id,p.career_id,p.academic_period_id,p.tutor_id,p.presentation_file_id,p.published_at,p.updated_at,pt.name type_name,pt.code type_code,c.name career_name,ap.name period_name,u.full_name tutor_name,
             CASE WHEN p.status='published' AND p.published_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND,p.published_at,CURRENT_TIMESTAMP) ELSE NULL END publication_reversion_elapsed_seconds,
             (SELECT COUNT(*) FROM project_participants pp WHERE pp.project_id=p.id AND pp.status='active') participant_count,
             (SELECT COUNT(*) FROM project_participants pa WHERE pa.project_id=p.id AND pa.role_code='student' AND pa.status='active' AND pa.removed_at IS NULL) author_count,
             (SELECT COUNT(*) FROM project_participants pj WHERE pj.project_id=p.id AND pj.role_code IN ('tribunal','jury') AND pj.status='active' AND pj.removed_at IS NULL) tribunal_count,
             (SELECT COUNT(*) FROM project_files pf WHERE pf.project_id=p.id AND pf.deleted_at IS NULL AND pf.purged_at IS NULL) active_file_count".$from.' ORDER BY p.updated_at DESC, p.id DESC';
        $result=PaginationService::run(Database::connection(),'SELECT COUNT(*)'.$from,$sql,$x,$pagination?:PaginationService::request());
        $files=Database::connection()->prepare(
            "SELECT id,original_name name,extension,size_bytes
             FROM project_files WHERE project_id=:project_id AND deleted_at IS NULL
               AND LOWER(extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
             ORDER BY id"
        );
        $keywordModel=new ProjectKeywordModel();
        $participantsByProject=[];
        $projectIds=array_values(array_filter(array_map('intval',array_column($result['items'],'id'))));
        $reviewSituations=(new ProjectReviewSituationService())->forProjects($projectIds);
        if($projectIds){
            $placeholders=implode(',',array_fill(0,count($projectIds),'?'));
            $participants=Database::connection()->prepare("SELECT pp.project_id,pp.user_id,pp.role_code,pp.is_leader,u.username,u.full_name,u.email,
                    sp.institutional_code,(tp.user_id IS NOT NULL) is_teacher,(sp.user_id IS NOT NULL) is_student
                FROM project_participants pp
                INNER JOIN users u ON u.id=pp.user_id
                LEFT JOIN teacher_profiles tp ON tp.user_id=u.id
                LEFT JOIN student_profiles sp ON sp.user_id=u.id
                WHERE pp.project_id IN ($placeholders) AND pp.status='active' AND pp.removed_at IS NULL
                  AND LOWER(pp.role_code) IN ('student','tutor','co_tutor','cotutor','co-tutor','tribunal','jury')
                ORDER BY pp.project_id,pp.assigned_at,pp.user_id");
            $participants->execute($projectIds);
            foreach($participants->fetchAll() as $participant)$participantsByProject[(int)$participant['project_id']][]=$participant;
        }
        foreach($result['items'] as &$item){
            $files->execute(['project_id'=>$item['id']]);
            $item['presentation_files']=array_map(static function(array $file):array{
                $extension=strtolower((string)$file['extension']);
                $format=in_array($extension,['jpg','jpeg'],true)?'JPG':strtoupper($extension);
                $icon=match($extension){
                    'pdf'=>'fa-regular fa-file-pdf',
                    'docx'=>'fa-regular fa-file-word',
                    'txt'=>'fa-regular fa-file-lines',
                    'png','jpg','jpeg','webp'=>'fa-regular fa-file-image',
                    default=>'fa-regular fa-file',
                };
                return [
                    'id'=>(int)$file['id'],'name'=>(string)$file['name'],
                    'extension'=>$extension,'format'=>$format,'icon'=>$icon,
                    'size'=>ArchiveService::formatBytes((int)$file['size_bytes']),
                ];
            },$files->fetchAll());
            $item['presentation_file_id']=(int)($item['presentation_file_id']??0);
            $item['keywords']=$keywordModel->forProject((int)$item['id']);
            $item['participants']=$participantsByProject[(int)$item['id']]??[];
            $item['review_situation']=$reviewSituations[(int)$item['id']]??ProjectReviewSituationService::emptySituation();
        }
        unset($item);
        return $result;
    }
    public function summary(array $filters=[]):array
    {
        [$from,$params]=$this->filteredQuery($filters);
        $statement=Database::connection()->prepare("SELECT COUNT(*) total,SUM(p.status='development') development,SUM(p.status='under_review') review,SUM(p.status='approved') approved,SUM(p.status IN ('defense','tribunal_approved')) defense".$from);
        $statement->execute($params);
        return array_map('intval',$statement->fetch()?:[]);
    }
    public function catalogs():array{$d=Database::connection();return ['types'=>$d->query('SELECT id,code,name FROM project_types WHERE is_active=1 ORDER BY name')->fetchAll(),'careers'=>$d->query('SELECT id,name FROM careers WHERE is_active=1 ORDER BY name')->fetchAll(),'periods'=>$d->query("SELECT id,name,status,starts_on FROM academic_periods WHERE status IN ('active','closed') ORDER BY (status='active') DESC, starts_on DESC")->fetchAll(),'teachers'=>$d->query("SELECT u.id,u.username,u.full_name,u.email FROM users u JOIN teacher_profiles tp ON tp.user_id=u.id WHERE u.status='active' AND tp.can_tutor=1 ORDER BY u.full_name")->fetchAll(),'students'=>$d->query("SELECT DISTINCT u.id,u.username,u.full_name,u.email,sp.institutional_code FROM users u INNER JOIN student_profiles sp ON sp.user_id=u.id INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='student' WHERE u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL ORDER BY u.full_name")->fetchAll(),'keywords'=>$this->projectKeywordCatalog()];}
    private function projectKeywordCatalog():array
    {
        return array_values(array_filter(
            (new SupportMaterialModel())->keywordCatalog(),
            static fn(string $keyword):bool=>!in_array(mb_strtolower(trim($keyword),'UTF-8'),self::PROJECT_KEYWORD_EXCLUSIONS,true)
        ));
    }
    private function filteredQuery(array $filters):array
    {
        $where=[
            ProjectCapabilityService::institutionalActiveProjectWhere('p'),
        ];
        $params=[];
        $search=(string)($filters['search']??'');
        if($search!==''){
            $where[]='(p.title LIKE :q_title OR p.code LIKE :q_code OR u.full_name LIKE :q_tutor)';
            $term='%'.$search.'%';
            $params['q_title']=$term;
            $params['q_code']=$term;
            $params['q_tutor']=$term;
        }
        $status=(string)($filters['status']??'');
        if(in_array($status,self::STATUSES,true)){$where[]='p.status=:s';$params['s']=$status;}
        $typeId=(int)($filters['type_id']??0);
        if($typeId>0){$where[]='p.project_type_id=:type_id';$params['type_id']=$typeId;}
        $periodId=(int)($filters['period_id']??0);
        if($periodId>0){$where[]='p.academic_period_id=:period_id';$params['period_id']=$periodId;}
        $reviewSituation=(string)($filters['review_situation']??'');
        if(in_array($reviewSituation,['pending','addressed','none'],true)){
            $condition=(new ProjectReviewSituationService())->filterCondition($reviewSituation,'p');
            if($condition!==null)$where[]=$condition;
        }
        $from=" FROM projects p JOIN project_types pt ON pt.id=p.project_type_id JOIN careers c ON c.id=p.career_id JOIN academic_periods ap ON ap.id=p.academic_period_id LEFT JOIN users u ON u.id=p.tutor_id WHERE ".implode(' AND ',$where);
        return [$from,$params];
    }
    public function save(array $v,int $id,int $actor,bool $allowStatusChange=false):int
    {
        $this->validate($v);
        return Database::transaction(function(PDO $d)use($v,$id,$actor,$allowStatusChange):int{
            $tutor=$v['tutor_id']?:null;
            $tutoringChange=['changed'=>false];
            $authorChange=['changed'=>false];
            $type=$d->prepare('SELECT code FROM project_types WHERE id=:id AND is_active=1');
            $type->execute(['id'=>$v['project_type_id']]);
            $typeCode=(string)$type->fetchColumn();
            if($typeCode==='')throw new InvalidArgumentException('El tipo de proyecto ya no está disponible.');
            $currentPeriodId=null;
            if($id){
                $q=$d->prepare('SELECT id,code,title,subtitle,summary,status,project_type_id,career_id,academic_period_id,tutor_id,presentation_file_id,approved_at,created_at FROM projects WHERE id=:id AND deleted_at IS NULL FOR UPDATE');
                $q->execute(['id'=>$id]);$before=$q->fetch();
                if(!$before)throw new InvalidArgumentException('El proyecto ya no existe.');
                $currentPeriodId=(int)$before['academic_period_id'];
                if(!$allowStatusChange)$v['status']=(string)$before['status'];
                $this->lockAndValidateAcademicPeriod($d,(int)$v['academic_period_id'],$currentPeriodId);
                if(!empty($v['tutoring_managed'])){
                    $tutoringChange=(new ProjectTutoringService())->sync($d,$id,(array)($v['tutoring_user_ids']??[]),(int)($v['tutoring_primary_id']??0),$before['tutor_id']===null?null:(int)$before['tutor_id']);
                    $tutor=(int)$tutoringChange['after_primary'];
                }
                if(!empty($v['authors_managed'])){
                    $authorChange=(new ProjectAuthorService())->sync($d,$id,(array)($v['author_user_ids']??[]),(int)($v['author_leader_id']??0));
                }
                if($v['status']==='published'){
                    $requestedPresentation=(int)($v['presentation_file_id']??0);
                    if($requestedPresentation>0){
                        $candidate=$d->prepare(
                            "SELECT id FROM project_files
                             WHERE id=:file_id AND project_id=:project_id AND deleted_at IS NULL
                               AND LOWER(extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
                             FOR UPDATE"
                        );
                        $candidate->execute(['file_id'=>$requestedPresentation,'project_id'=>$id]);
                        if(!$candidate->fetchColumn())throw new InvalidArgumentException('El archivo de presentación seleccionado no es válido.');
                        $d->prepare('UPDATE projects SET presentation_file_id=:file_id WHERE id=:id')
                            ->execute(['file_id'=>$requestedPresentation,'id'=>$id]);
                        if($requestedPresentation!==(int)($before['presentation_file_id']??0)){
                            (new ProjectAuditService($d))->record(
                                $id,$actor,
                                empty($before['presentation_file_id'])?'project.presentation_selected':'project.presentation_changed',
                                'project_file',$requestedPresentation,
                                ['presentation_file_id'=>$before['presentation_file_id']?:null],
                                ['presentation_file_id'=>$requestedPresentation]
                            );
                        }
                    }
                }
                $incoming=['title'=>$v['title'],'subtitle'=>$v['subtitle']?:null,'summary'=>$v['summary']?:null,'project_type_id'=>$v['project_type_id'],'career_id'=>$v['career_id'],'academic_period_id'=>$v['academic_period_id'],'tutor_id'=>$tutor,'status'=>$v['status']];
                $changed=[];
                foreach($incoming as $field=>$value){
                    $old=$before[$field]??null;
                    if(in_array($field,['project_type_id','career_id','academic_period_id','tutor_id'],true)){$old=$old===null?null:(int)$old;$value=$value===null?null:(int)$value;}
                    else{$old=$old===null?null:(string)$old;$value=$value===null?null:(string)$value;}
                    if($old!==$value)$changed[$field]=[$old,$value];
                }
                $keywordChange=(new ProjectKeywordModel())->syncDifferential($d,$id,(array)($v['keywords']??[]),(new SupportMaterialModel())->keywordCatalog());
                if(!$changed&&!$keywordChange['changed']&&!$tutoringChange['changed']&&!$authorChange['changed'])throw new InvalidArgumentException('No se detectaron cambios en el proyecto.');
                $publishing=$v['status']==='published'&&(string)$before['status']!=='published';
                $summary=(string)($v['summary']??'');$descriptionChanged=false;$descriptionOrigin='existing';
                if($publishing){
                    $requiredStatus=$typeCode==='thesis'?'tribunal_approved':'approved';
                    if((string)$before['status']!==$requiredStatus)throw new InvalidArgumentException('El proyecto ya no se encuentra en un estado válido para publicación.');
                    if(trim($summary)===''){
                        $summary=(new ProjectDescriptionService($d))->normalizePublicationDescription((string)($v['public_description']??''));
                        $descriptionOrigin=in_array((string)($v['description_origin']??''),['introduction','institutional','unavailable'],true)?(string)$v['description_origin']:'administrator';
                        $descriptionChanged=true;
                    }
                }
                $code=$before['code'];
                if((int)$before['project_type_id']!==$v['project_type_id']){
                    $code=(new ProjectCodeService())->next($d,$v['project_type_id'],$typeCode,(int)date('Y',strtotime($before['created_at'])));
                    if($code!==(string)$before['code'])$changed['code']=[(string)$before['code'],$code];
                }
                $finalStatus=$typeCode==='thesis'?'tribunal_approved':'approved';
                $recordAcademicCompletion=isset($changed['status'])
                    && (string)$changed['status'][1]===$finalStatus
                    && empty($before['approved_at']);
                if($changed){$q=$d->prepare('UPDATE projects SET code=:code,title=:title,subtitle=:subtitle,summary=:summary,project_type_id=:type,career_id=:career,academic_period_id=:period,tutor_id=:tutor,status=:status,approved_at=CASE WHEN :record_completion=1 AND approved_at IS NULL THEN CURRENT_TIMESTAMP ELSE approved_at END,published_at=CASE WHEN :publishing=1 THEN CURRENT_TIMESTAMP ELSE published_at END,is_available=CASE WHEN :publishing_available=1 THEN 1 ELSE is_available END WHERE id=:id');
                $q->execute(['code'=>$code,'title'=>$v['title'],'subtitle'=>$v['subtitle']?:null,'summary'=>$summary?:null,'type'=>$v['project_type_id'],'career'=>$v['career_id'],'period'=>$v['academic_period_id'],'tutor'=>$tutor,'status'=>$v['status'],'record_completion'=>$recordAcademicCompletion?1:0,'publishing'=>$publishing?1:0,'publishing_available'=>$publishing?1:0,'id'=>$id]);}
                elseif($keywordChange['changed']||$tutoringChange['changed']||$authorChange['changed']){$d->prepare('UPDATE projects SET updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>$id]);}
                if($publishing)(new ProjectRepositoryPackageService())->buildForProject($id);
                if($descriptionChanged)(new ProjectAuditService($d))->record($id,$actor,'project_description_updated','project',$id,['summary'=>null],['summary'=>$summary,'origin'=>$descriptionOrigin,'edited_by_administrator'=>true]);
                $describedChanges=$changed;
                if($tutoringChange['changed'])unset($describedChanges['tutor_id']);
                [$previous,$next,$history]=$this->describeChanges($d,$describedChanges);
                if($keywordChange['changed']){$beforeKeywords=$keywordChange['before']?implode(', ',$keywordChange['before']):'Sin etiquetas';$afterKeywords=$keywordChange['after']?implode(', ',$keywordChange['after']):'Sin etiquetas';$previous['Etiquetas']=$beforeKeywords;$next['Etiquetas']=$afterKeywords;$history[]=['field'=>'Etiquetas','verb'=>'cambiadas','from'=>$beforeKeywords,'to'=>$afterKeywords];}
                if($tutoringChange['changed']){
                    $beforeTutoring=implode(', ',array_column($tutoringChange['before'],'name'));
                    $afterTutoring=implode(', ',array_column($tutoringChange['after'],'name'));
                    $previous['Tutoría']=$beforeTutoring;
                    $next['Tutoría']=$afterTutoring;
                    $next['_tutoring']=['added'=>$tutoringChange['added'],'removed'=>$tutoringChange['removed'],'previous_reference'=>$tutoringChange['before_primary'],'new_reference'=>$tutoringChange['after_primary']];
                    $history[]=['field'=>'Tutoría','verb'=>'actualizada','from'=>$beforeTutoring,'to'=>$afterTutoring];
                }
                if($authorChange['changed']){
                    $beforeAuthors=implode(', ',array_column($authorChange['before'],'name'))?:'Sin autores';
                    $afterAuthors=implode(', ',array_column($authorChange['after'],'name'))?:'Sin autores';
                    $previous['Autores']=$beforeAuthors;
                    $next['Autores']=$afterAuthors;
                    $next['_authors']=['added'=>$authorChange['added'],'removed'=>$authorChange['removed'],'previous_leader'=>$authorChange['before_leader'],'new_leader'=>$authorChange['after_leader']];
                    $history[]=['field'=>'Autores','verb'=>'actualizados','from'=>$beforeAuthors,'to'=>$afterAuthors];
                    (new ProjectAuditService($d))->record($id,$actor,'project_authors_updated','project_participants',$id,['authors'=>$beforeAuthors,'leader_id'=>$authorChange['before_leader']],['authors'=>$afterAuthors,'leader_id'=>$authorChange['after_leader'],'added'=>$authorChange['added'],'removed'=>$authorChange['removed']]);
                }
                $next['_history_changes']=$history;
                $auditId=(new ProjectAuditService($d))->record($id,$actor,'project_updated','project',$id,$previous,$next);
                $academicNotifications=new ProjectAcademicNotificationService();
                if(!empty($tutoringChange['added']))$academicNotifications->tutorAssigned($d,$id,$code,(string)$v['title'],(array)$tutoringChange['added'],$actor);
                if($recordAcademicCompletion)$academicNotifications->finalApproval($d,$id,$code,(string)$v['title'],(string)$v['status'],self::STATUS_LABELS[(string)$v['status']]??(string)$v['status'],$auditId);
                if(isset($changed['status'])){
                    (new ProjectDescriptionService($d))->registerStatusReminder($id,$auditId);
                    $from=self::STATUS_LABELS[(string)$changed['status'][0]]??(string)$changed['status'][0];
                    $to=self::STATUS_LABELS[(string)$changed['status'][1]]??(string)$changed['status'][1];
                    (new AdminActivityService($d))->record($actor,'project_status_changed','Cambió el estado de “'.$v['title'].'” de '.$from.' a '.$to,'Proyectos','project',$id,$v['title'],'correct',['from'=>$changed['status'][0],'to'=>$changed['status'][1]]);
                    if($publishing){(new ProjectAuditService($d))->record($id,$actor,'project_published','project',$id,['status'=>$changed['status'][0]],['status'=>'published','description_origin'=>$descriptionChanged?$descriptionOrigin:'existing']);(new ProjectDocumentArchiveService())->archiveHistoricalVersionsForProjectInTransaction($d,$id,$actor,'Publicación institucional del proyecto.');}
                }
                return $id;
            }
            $this->lockAndValidateAcademicPeriod($d,(int)$v['academic_period_id'],null);
            $code=(new ProjectCodeService())->next($d,$v['project_type_id'],$typeCode,(int)date('Y'));
            $q=$d->prepare("INSERT INTO projects(code,project_type_id,career_id,academic_period_id,title,subtitle,tutor_id,status,current_stage,created_by) VALUES(:code,:type,:career,:period,:title,:subtitle,:tutor,:status,'registration',:creator)");
            $q->execute(['code'=>$code,'type'=>$v['project_type_id'],'career'=>$v['career_id'],'period'=>$v['academic_period_id'],'title'=>$v['title'],'subtitle'=>$v['subtitle']?:null,'tutor'=>$tutor,'status'=>$v['status'],'creator'=>$actor]);
            $id=(int)$d->lastInsertId();
            (new ProjectKeywordModel())->syncDifferential($d,$id,(array)($v['keywords']??[]),(new SupportMaterialModel())->keywordCatalog());
            (new ProjectAuditService($d))->record($id,$actor,'project_created','project',$id,null,$v+['code'=>$code]);
            return $id;
        });
    }
    private function lockAndValidateAcademicPeriod(PDO $db,int $periodId,?int $currentPeriodId):void
    {
        $period=$db->prepare('SELECT id,status FROM academic_periods WHERE id=:id FOR UPDATE');
        $period->execute(['id'=>$periodId]);
        $row=$period->fetch();
        if(!$row)throw new InvalidArgumentException('El periodo academico seleccionado no existe.');
        $status=(string)$row['status'];
        if($currentPeriodId!==null&&$periodId===$currentPeriodId&&in_array($status,['active','closed'],true))return;
        if($status!=='active')throw new InvalidArgumentException('El periodo academico seleccionado ya no esta disponible para esta operacion.');
    }

    private function describeChanges(PDO $db,array $changes):array
    {
        $labels=['title'=>'Título','subtitle'=>'Descripción breve','summary'=>'Descripción','project_type_id'=>'Tipo','career_id'=>'Carrera','academic_period_id'=>'Periodo','tutor_id'=>'Tutor','status'=>'Estado','code'=>'Código'];
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
    private function validate(array $v):void{if(mb_strlen($v['title'])<5)throw new InvalidArgumentException('Ingresa un título de al menos cinco caracteres.');if($v['project_type_id']<1||$v['career_id']<1||$v['academic_period_id']<1)throw new InvalidArgumentException('Completa tipo, carrera y periodo académico.');if(!in_array($v['status'],self::WRITABLE_STATUSES,true))throw new InvalidArgumentException('El estado seleccionado no es válido.');if(in_array($v['status'],['defense','tribunal_approved'],true)){$q=Database::connection()->prepare('SELECT code FROM project_types WHERE id=:id');$q->execute(['id'=>$v['project_type_id']]);if($q->fetchColumn()!=='thesis')throw new InvalidArgumentException('Los estados del Tribunal solo corresponden a proyectos de tesis.');}}
}
