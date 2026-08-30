<?php

declare(strict_types=1);

/** Convierte un borrador validado en un proyecto definitivo de forma atómica. */
final class ProjectDraftRegistrationService
{
    public function register(int $userId,array $policy,string $draftId,bool $submitForReview=false): array
    {
        if($userId<1||!preg_match('/^[a-f0-9-]{36}$/i',$draftId))throw new ProjectDraftRegistrationException('El borrador ya no está disponible.');
        $db=Database::connection();$moved=[];$projectId=0;$code='';$submission=null;$draftStorage=new ProjectDraftStorageService($db);$fileStorage=new PrivateProjectFileService();
        try {
            $db->beginTransaction();
            $draftRow=$this->lockedDraft($db,$userId,$draftId);
            $this->lockActivePeriod($db);
            $draftService=new ProjectDraftService();$catalogs=$draftService->catalogs($userId,$policy);
            if($catalogs['active_period']===null||$catalogs['student']===null)throw new ProjectDraftRegistrationException((string)$catalogs['availability_message'],['academic'=>(string)$catalogs['availability_message']]);
            $payload=$this->payload((string)$draftRow['payload']);$draft=$draftService->normalize($payload,$policy,$catalogs);
            $errors=$draftService->validate($draft,$policy,$catalogs);if($errors!==[])throw new ProjectDraftRegistrationException('Revisa la información indicada antes de registrar el proyecto.',$errors);
            $files=$this->lockedFiles($db,$userId,$draftId);$this->validateTemporaryFiles($files,$draftStorage,$fileStorage);

            $type=$catalogs['types'][$draft['type']]??null;if($type===null)throw new ProjectDraftRegistrationException('El tipo de proyecto ya no está disponible.',['type'=>'Selecciona nuevamente el tipo de proyecto.']);
            $student=$catalogs['student'];$code=(new ProjectCodeService())->next($db,(int)$type['id'],(string)$draft['type'],(int)date('Y'));
            $projectId=$this->insertProject($db,$code,$draft,$type,$student,(int)$catalogs['active_period']['id'],'development');
            $this->insertParticipants($db,$projectId,$draft,$userId);
            $this->syncKeywords($db,$projectId,$draft,$catalogs);
            $this->promoteFiles($db,$projectId,$files,$draftStorage,$fileStorage,$userId,$draftId,$moved);
            (new ProjectAuditService($db))->record($projectId,$userId,'project_created','project',$projectId,null,[
                'code'=>$code,'type'=>(string)$type['label'],'tutor_id'=>(int)$draft['tutor_id'],'participants'=>count($draft['members']),'files'=>count($files),
                'academic_period_id'=>(int)$catalogs['active_period']['id'],'status'=>'development','current_stage'=>'registration',
            ]);
            if($submitForReview)$submission=(new StudentProjectSubmissionService())->submitForReviewInTransaction($db,$projectId,$userId);
            $db->prepare('DELETE FROM project_draft_files WHERE draft_id=:draft AND user_id=:user')->execute(['draft'=>$draftId,'user'=>$userId]);
            $delete=$db->prepare('DELETE FROM project_drafts WHERE id=:draft AND user_id=:user');$delete->execute(['draft'=>$draftId,'user'=>$userId]);
            if($delete->rowCount()!==1)throw new RuntimeException('No fue posible consumir el borrador.');
            $db->commit();
        } catch(Throwable $exception) {
            if($db->inTransaction())$db->rollBack();$this->restoreMovedFiles($moved);throw $exception;
        }
        try {$draftStorage->cleanupConsumedDirectory($userId,$draftId);}catch(Throwable $exception){error_log('Project registration temporary cleanup: '.$exception->getMessage());}
        return array_merge(['project_id'=>$projectId,'project_code'=>$code,'redirect_url'=>route('project-detail').'&id='.$projectId],$submission??[]);
    }

    private function lockedDraft(PDO $db,int $userId,string $draftId):array
    {
        $q=$db->prepare('SELECT id,payload FROM project_drafts WHERE id=:draft AND user_id=:user AND expires_at>UTC_TIMESTAMP() FOR UPDATE');$q->execute(['draft'=>$draftId,'user'=>$userId]);$row=$q->fetch();
        if(!$row)throw new ProjectDraftRegistrationException('El borrador ya fue registrado o no está disponible.');return $row;
    }

    private function lockActivePeriod(PDO $db):void
    {
        $q=$db->query("SELECT id FROM academic_periods WHERE status='active' ORDER BY starts_on DESC,id DESC FOR UPDATE");
        $rows=$q->fetchAll(PDO::FETCH_COLUMN);
        if(count($rows)>1)throw new ProjectDraftRegistrationException('La configuracion academica tiene mas de un periodo activo.',['period'=>'La configuracion academica tiene mas de un periodo activo.']);
        if(!$rows)throw new ProjectDraftRegistrationException('No existe un periodo academico activo.',['period'=>'No existe un periodo academico activo.']);
    }

    private function payload(string $json):array
    {
        try{$payload=json_decode($json,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){throw new ProjectDraftRegistrationException('El borrador no contiene información válida.');}
        if(!is_array($payload))throw new ProjectDraftRegistrationException('El borrador no contiene información válida.');return $payload;
    }

    private function lockedFiles(PDO $db,int $userId,string $draftId):array
    {
        $q=$db->prepare('SELECT id,draft_id,user_id,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256,zip_meta FROM project_draft_files WHERE draft_id=:draft AND user_id=:user ORDER BY created_at,id FOR UPDATE');$q->execute(['draft'=>$draftId,'user'=>$userId]);return $q->fetchAll();
    }

    private function validateTemporaryFiles(array $files,ProjectDraftStorageService $draftStorage,PrivateProjectFileService $fileStorage):void
    {
        $total=0;foreach($files as $file){$path=$draftStorage->resolveStoredFile((int)$file['user_id'],(string)$file['draft_id'],(string)$file['storage_name']);$verified=$fileStorage->validateStoredFile($path,$file);$total+=(int)$verified['size_bytes'];if($verified['extension']==='zip'){ $zip=(new ArchiveService())->inspectPackage($path);if(empty($zip['success']))throw new ProjectDraftRegistrationException('Uno de los archivos ZIP temporales ya no es válido.',['files'=>'Vuelve a cargar el archivo ZIP.']); }}
        if($total>(int)$fileStorage->limits()['max_total_bytes'])throw new ProjectDraftRegistrationException('Los archivos temporales superan el límite permitido.',['files'=>'Reduce los archivos cargados.']);
    }

    private function insertProject(PDO $db,string $code,array $draft,array $type,array $student,int $periodId,string $status='under_review'):int
    {
        $research=in_array($draft['type'],['thesis','thesis_profile'],true)?(int)$draft['research_line']:null;
        $modality=$draft['type']==='practice'?'individual':($draft['type']==='thesis'?(string)$draft['modality']:null);
        $q=$db->prepare("INSERT INTO projects(code,project_type_id,career_id,academic_period_id,title,subtitle,summary,modality,research_line_id,academic_subject_id,proposed_tutor_id,tutor_id,status,current_stage,is_available,created_by) VALUES(:code,:type,:career,:period,:title,NULL,:summary,:modality,:research,NULL,NULL,:tutor,:status,'registration',1,:creator)");
        $q->execute(['code'=>$code,'type'=>(int)$type['id'],'career'=>(int)$student['career_id'],'period'=>$periodId,'title'=>$draft['title'],'summary'=>$draft['description'],'modality'=>$modality,'research'=>$research,'tutor'=>(int)$draft['tutor_id'],'status'=>$status,'creator'=>(int)$student['user_id']]);
        return (int)$db->lastInsertId();
    }

    private function insertParticipants(PDO $db,int $projectId,array $draft,int $creatorId):void
    {
        $insert=$db->prepare("INSERT INTO project_participants(project_id,user_id,role_code,permission_level,is_leader,status) VALUES(:project,:user,:role,:permission,:leader,'active')");
        foreach($draft['members'] as $member){$id=(int)$member;$insert->execute(['project'=>$projectId,'user'=>$id,'role'=>'student','permission'=>$id===$creatorId?'manage':'contribute','leader'=>$id===$creatorId?1:0]);}
        $insert->execute(['project'=>$projectId,'user'=>(int)$draft['tutor_id'],'role'=>'tutor','permission'=>'review','leader'=>0]);
    }

    private function syncKeywords(PDO $db,int $projectId,array $draft,array $catalogs):void
    {
        $allowed=array_merge(array_map(static fn(array $keyword):string=>(string)$keyword['name'],(array)$catalogs['keywords']),(array)$draft['tags']);
        (new ProjectKeywordModel())->syncDifferential($db,$projectId,(array)$draft['tags'],$allowed);
    }

    private function promoteFiles(PDO $db,int $projectId,array $files,ProjectDraftStorageService $draftStorage,PrivateProjectFileService $fileStorage,int $userId,string $draftId,array &$moved):void
    {
        $insert=$db->prepare("INSERT INTO project_files(project_id,delivery_id,category,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256,sort_order,uploaded_by) VALUES(:project,NULL,'delivery',:name,:storage,:path,:mime,:extension,:size,:hash,:order,:user)");
        foreach($files as $order=>$file){$source=$draftStorage->resolveStoredFile($userId,$draftId,(string)$file['storage_name']);$fileStorage->validateStoredFile($source,$file);$stored=$fileStorage->promoteStoredFile($projectId,$source,(string)$file['extension']);$moved[]=['source'=>$source,'destination'=>$stored['absolute_path']];$insert->execute(['project'=>$projectId,'name'=>$file['original_name'],'storage'=>$stored['storage_name'],'path'=>$stored['storage_path'],'mime'=>$file['mime_type'],'extension'=>$file['extension'],'size'=>(int)$file['size_bytes'],'hash'=>$file['checksum_sha256'],'order'=>$order,'user'=>$userId]);}
    }

    private function restoreMovedFiles(array $moved):void
    {
        foreach(array_reverse($moved) as $file){$source=(string)$file['source'];$destination=(string)$file['destination'];if(!is_file($destination))continue;try{$dir=dirname($source);if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible recrear el directorio temporal.');if(!@rename($destination,$source))throw new RuntimeException('No fue posible restaurar un archivo temporal.');}catch(Throwable $exception){error_log('Project registration filesystem compensation: '.$exception->getMessage());}}
    }
}

final class ProjectDraftRegistrationException extends RuntimeException
{
    public function __construct(string $message,public readonly array $errors=[]){parent::__construct($message);}
}
