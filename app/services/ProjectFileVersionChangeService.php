<?php

declare(strict_types=1);

/** Caso de uso transaccional para reemplazos académicos con declaración estructurada. */
final class ProjectFileVersionChangeService
{
    public function replaceInTransaction(PDO $db,int $projectId,int $fileId,string $expectedChecksum,array $stored,int $actor,string $context,array $input):array
    {
        $model=new ProjectDocumentModel($db);$project=$model->lockProject($projectId);$this->authorize($db,$project,$actor,$context);
        $current=$model->findActiveFile($projectId,$fileId,true);
        if(!preg_match('/^[a-f0-9]{64}$/',$expectedChecksum)||!hash_equals((string)$current['checksum_sha256'],$expectedChecksum))$this->conflict();
        $newChecksum=(string)($stored['checksum_sha256']??'');
        if(!preg_match('/^[a-f0-9]{64}$/',$newChecksum))throw new ProjectDocumentVersionException('No fue posible validar la integridad del archivo nuevo.');
        if(hash_equals($expectedChecksum,$newChecksum))throw new ProjectDocumentVersionException('El archivo seleccionado tiene el mismo contenido que la versión actual.');
        $reason=$this->text((string)($input['reason']??''),5,500,'El motivo es obligatorio y debe contener entre 5 y 500 caracteres.');
        $summary=$this->text((string)($input['declared_summary']??''),20,2000,'El resumen de cambios es obligatorio y debe contener entre 20 y 2000 caracteres.');
        $sections=$this->sections($input['sections']??[]);$observationIds=$this->ids($input['addressed_observation_ids']??[]);
        $previousStatus=$this->documentStatus($db,$projectId,$fileId,$expectedChecksum);
        $observations=$this->lockObservations($db,$projectId,$fileId,$observationIds,$actor,$context);
        $conflict=(new ProjectDocumentModel($db))->activeFileConflict($projectId,(string)$stored['original_name'],(int)$stored['size_bytes'],$newChecksum,$fileId);
        if($conflict!==null)throw new ProjectDocumentVersionException(($conflict['conflict_type']??'')==='name_size'?'Ya existe otro archivo activo con el mismo nombre y tamaño.':'Ya existe otro archivo activo con el mismo contenido.');
        $replacement=$model->replace($projectId,$fileId,$stored,$actor,$reason);
        $previousNumber=(int)$replacement['version_number'];$newNumber=$previousNumber+1;
        $insert=$db->prepare("INSERT INTO project_file_version_changes(project_id,file_id,previous_version_id,previous_checksum,new_checksum,previous_version_number,new_version_number,changed_by,reason,declared_summary,sections_json,previous_document_status,new_document_status)
            VALUES(:project,:file,:previous_version,:previous_checksum,:new_checksum,:previous_number,:new_number,:actor,:reason,:summary,:sections,:previous_status,'development')");
        $insert->execute(['project'=>$projectId,'file'=>$fileId,'previous_version'=>(int)$replacement['previous_version_id'],'previous_checksum'=>$expectedChecksum,'new_checksum'=>$newChecksum,'previous_number'=>$previousNumber,'new_number'=>$newNumber,'actor'=>$actor,'reason'=>$reason,'summary'=>$summary,'sections'=>$sections===[]?null:json_encode($sections,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'previous_status'=>$previousStatus]);
        $changeId=(int)$db->lastInsertId();
        if($observations!==[]){$link=$db->prepare('INSERT INTO project_file_version_addressed_observations(change_id,observation_id) VALUES(:change,:observation)');foreach($observations as $observation){$link->execute(['change'=>$changeId,'observation'=>(int)$observation['id']]);}
            $placeholders=implode(',',array_fill(0,count($observationIds),'?'));$update=$db->prepare("UPDATE project_observations SET status='addressed' WHERE project_id=? AND id IN ($placeholders) AND status='pending'");$update->execute([$projectId,...$observationIds]);}
        (new ProjectDocumentReviewService($db))->recordCurrentStatus($projectId,$fileId,$newChecksum,'development',$actor);
        $auditPayload=['change_id'=>$changeId,'project_id'=>$projectId,'file_id'=>$fileId,'previous_checksum'=>$expectedChecksum,'new_checksum'=>$newChecksum,'previous_version_number'=>$previousNumber,'new_version_number'=>$newNumber,'reason'=>$reason,'declared_summary'=>$summary,'sections'=>$sections,'addressed_observation_ids'=>$observationIds,'previous_document_status'=>$previousStatus,'new_document_status'=>'development'];
        (new ProjectAuditService($db))->record($projectId,$actor,'project_document_version_created','project_file_version_change',$changeId,['checksum'=>$expectedChecksum,'version_number'=>$previousNumber,'document_status'=>$previousStatus],$auditPayload,$reason);
        $this->notifyTutor($db,$project,$current,$changeId,$newNumber,$summary,count($observationIds),$previousStatus);
        return ['change_id'=>$changeId,'file'=>$replacement['file'],'previous_version_id'=>(int)$replacement['previous_version_id'],'previous_version_number'=>$previousNumber,'new_version_number'=>$newNumber,'previous_checksum'=>$expectedChecksum,'new_checksum'=>$newChecksum,'previous_document_status'=>$previousStatus,'new_document_status'=>'development','reason'=>$reason,'declared_summary'=>$summary,'sections'=>$sections,'addressed_observation_ids'=>$observationIds];
    }

    private function authorize(PDO $db,array $project,int $actor,string $context):void
    {
        $identity=$db->prepare('SELECT is_admin FROM users WHERE id=:id AND status=\'active\' AND deleted_at IS NULL AND purged_at IS NULL');$identity->execute(['id'=>$actor]);$admin=$identity->fetchColumn();if($admin===false)throw new ProjectDocumentVersionException('No tienes autorización para reemplazar este documento.',403);
        if($context==='academic_management'&&$admin)return;
        if($context!=='academic'||(string)$project['status']!=='development')throw new ProjectDocumentVersionException('No tienes autorización para reemplazar este documento.',403);
        $student=$db->prepare("SELECT 1 FROM project_participants pp JOIN user_roles ur ON ur.user_id=pp.user_id JOIN roles r ON r.id=ur.role_id AND r.code='student' WHERE pp.project_id=:project AND pp.user_id=:actor AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL LIMIT 1");$student->execute(['project'=>(int)$project['id'],'actor'=>$actor]);if(!$student->fetchColumn())throw new ProjectDocumentVersionException('No tienes autorización para reemplazar este documento.',403);
    }
    private function lockObservations(PDO $db,int $project,int $file,array $ids,int $actor,string $context):array
    {
        if($ids===[])return [];if($context!=='academic')throw new ProjectDocumentVersionException('Solo el estudiante responsable puede declarar observaciones atendidas.',403);
        $placeholders=implode(',',array_fill(0,count($ids),'?'));$query=$db->prepare("SELECT id,project_id,file_id,status FROM project_observations WHERE id IN ($placeholders) FOR UPDATE");$query->execute($ids);$rows=$query->fetchAll();if(count($rows)!==count($ids))throw new ProjectDocumentVersionException('Una o más observaciones no existen.');
        foreach($rows as $row){if((int)$row['project_id']!==$project)throw new ProjectDocumentVersionException('Una observación pertenece a otro proyecto.');if($row['file_id']!==null&&(int)$row['file_id']!==$file)throw new ProjectDocumentVersionException('Una observación pertenece a otro archivo.');if((string)$row['status']==='resolved')throw new ProjectDocumentVersionException('Una observación ya fue resuelta y no puede marcarse como atendida.');}
        return $rows;
    }
    private function documentStatus(PDO $db,int $project,int $file,string $checksum):string{$q=$db->prepare('SELECT status FROM project_file_review_states WHERE project_id=:project AND file_id=:file AND checksum_sha256=:checksum');$q->execute(['project'=>$project,'file'=>$file,'checksum'=>$checksum]);$status=$q->fetchColumn();return is_string($status)?$status:'development';}
    private function notifyTutor(PDO $db,array $project,array $file,int $change,int $version,string $summary,int $observationCount,string $previousStatus):void
    { $tutor=(int)($project['tutor_id']??0);if($tutor<1||($previousStatus==='development'&&$observationCount===0&&(string)$project['status']!=='under_review'))return;$short=mb_strlen($summary)>180?mb_substr($summary,0,177).'...':$summary;$insert=$db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) SELECT u.id,:project,'review','Nueva versión documental registrada',:message,:url,'Abrir documentos',:metadata,:dedup FROM users u WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");$insert->execute(['user'=>$tutor,'project'=>(int)$project['id'],'message'=>'Se registró la versión '.$version.' de '.(string)$file['original_name'].'. '.$short,'url'=>route('project-detail').'&id='.(int)$project['id'].'&tab=files','metadata'=>json_encode(['change_id'=>$change,'file_id'=>(int)$file['id'],'version_number'=>$version,'addressed_observation_count'=>$observationCount],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'dedup'=>'document-version:'.$change.':'.$tutor]); }
    private function sections(mixed $input):array{if(is_string($input)){try{$input=json_decode($input,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){$input=preg_split('/[,\r\n]+/u',$input);}}if(!is_array($input))throw new ProjectDocumentVersionException('Las secciones modificadas no tienen un formato válido.');if(count($input)>20)throw new ProjectDocumentVersionException('Puedes registrar hasta 20 secciones modificadas.');$result=[];$seen=[];foreach($input as $value){$section=trim((string)$value);if($section===''||mb_strlen($section)>100)throw new ProjectDocumentVersionException('Cada sección debe contener entre 1 y 100 caracteres.');$key=mb_strtolower($section);if(isset($seen[$key]))throw new ProjectDocumentVersionException('No repitas secciones modificadas.');$seen[$key]=true;$result[]=$section;}return $result;}
    private function ids(mixed $input):array{if(is_string($input)){try{$decoded=json_decode($input,true,512,JSON_THROW_ON_ERROR);$input=$decoded;}catch(JsonException){$input=preg_split('/[,\s]+/',$input,-1,PREG_SPLIT_NO_EMPTY);}}if(!is_array($input))throw new ProjectDocumentVersionException('Las observaciones atendidas no tienen un formato válido.');$raw=array_map('intval',$input);$ids=array_values(array_filter($raw,static fn(int $id):bool=>$id>0));if(count($ids)!==count($raw)||count($ids)!==count(array_unique($ids)))throw new ProjectDocumentVersionException('Las observaciones atendidas contienen identificadores inválidos o duplicados.');return $ids;}
    private function text(string $value,int $min,int $max,string $message):string{$value=trim((string)preg_replace('/\s+/u',' ',$value));if(mb_strlen($value)<$min||mb_strlen($value)>$max)throw new ProjectDocumentVersionException($message);return $value;}
    private function conflict():never{throw new ProjectDocumentVersionException('El documento fue actualizado mientras preparabas la nueva versión. Recarga el expediente antes de continuar.',409);}
}
