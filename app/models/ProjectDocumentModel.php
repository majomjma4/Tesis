<?php
declare(strict_types=1);

final class ProjectDocumentModel
{
    public const RESTORE_HOURS = 24;
    private PDO $db;
    public function __construct(?PDO $db=null){$this->db=$db??Database::connection();}

    public function lockProject(int $id):array
    {
        $q=$this->db->prepare("SELECT id,title,tutor_id,presentation_file_id,status,deleted_at FROM projects WHERE id=:id AND deleted_at IS NULL FOR UPDATE");
        $q->execute(['id'=>$id]);$row=$q->fetch();if(!$row)throw new InvalidArgumentException('El proyecto ya no está disponible.');return $row;
    }
    public function activeFiles(int $projectId):array
    {
        $q=$this->db->prepare("SELECT * FROM project_files WHERE project_id=:id AND deleted_at IS NULL AND purged_at IS NULL ORDER BY sort_order,id");$q->execute(['id'=>$projectId]);return $q->fetchAll();
    }
    public function findActiveFile(int $projectId,int $fileId,bool $lock=false):array
    {
        $q=$this->db->prepare("SELECT * FROM project_files WHERE project_id=:project AND id=:file AND deleted_at IS NULL AND purged_at IS NULL".($lock?' FOR UPDATE':''));$q->execute(['project'=>$projectId,'file'=>$fileId]);$row=$q->fetch();if(!$row)throw new InvalidArgumentException('El archivo ya no está disponible.');return $row;
    }
    public function activeFileConflict(int $projectId,string $originalName,int $sizeBytes,string $checksum,?int $excludeFileId=null):?array
    {
        $q=$this->db->prepare("SELECT id,original_name,size_bytes,checksum_sha256,
            CASE WHEN original_name=:name_match AND size_bytes=:size_match THEN 'name_size' ELSE 'checksum' END conflict_type
            FROM project_files
            WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL
              AND (:exclude_id IS NULL OR id<>:exclude_id_again)
              AND ((original_name=:name_lookup AND size_bytes=:size_lookup) OR checksum_sha256=:checksum)
            ORDER BY (original_name=:name_order AND size_bytes=:size_order) DESC,id LIMIT 1");
        $q->execute(['name_match'=>$originalName,'size_match'=>$sizeBytes,'project'=>$projectId,'exclude_id'=>$excludeFileId,'exclude_id_again'=>$excludeFileId,'name_lookup'=>$originalName,'size_lookup'=>$sizeBytes,'checksum'=>$checksum,'name_order'=>$originalName,'size_order'=>$sizeBytes]);$row=$q->fetch();return $row?:null;
    }
    public function add(int $projectId,array $stored,int $actor):array
    {
        $q=$this->db->prepare("INSERT INTO project_files(project_id,delivery_id,category,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256,sort_order,uploaded_by) SELECT :project,NULL,'repository',:name,:storage,:path,:mime,:extension,:size,:checksum,COALESCE(MAX(sort_order),0)+1,:actor FROM project_files WHERE project_id=:project2");
        $q->execute(['project'=>$projectId,'name'=>$stored['original_name'],'storage'=>$stored['storage_name'],'path'=>$stored['storage_path'],'mime'=>$stored['mime_type'],'extension'=>$stored['extension'],'size'=>$stored['size_bytes'],'checksum'=>$stored['checksum_sha256'],'actor'=>$actor,'project2'=>$projectId]);
        return $this->findActiveFile($projectId,(int)$this->db->lastInsertId());
    }
    public function replace(int $projectId,int $fileId,array $stored,int $actor,string $reason=''):array
    {
        $old=$this->findActiveFile($projectId,$fileId,true);
        $n=$this->db->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM project_file_versions WHERE file_id=:id');$n->execute(['id'=>$fileId]);$version=(int)$n->fetchColumn();
        $q=$this->db->prepare('INSERT INTO project_file_versions(file_id,project_id,version_number,original_name,storage_name,storage_path,extension,mime_type,size_bytes,checksum_sha256,replaced_by,replacement_reason) VALUES(:file,:project,:version,:name,:storage,:path,:extension,:mime,:size,:checksum,:actor,:reason)');
        $q->execute(['file'=>$fileId,'project'=>$projectId,'version'=>$version,'name'=>$old['original_name'],'storage'=>$old['storage_name'],'path'=>$old['storage_path'],'extension'=>$old['extension'],'mime'=>$old['mime_type'],'size'=>$old['size_bytes'],'checksum'=>$old['checksum_sha256'],'actor'=>$actor,'reason'=>$reason!==''?$reason:null]);
        $previousVersionId=(int)$this->db->lastInsertId();
        $q=$this->db->prepare('UPDATE project_files SET original_name=:name,storage_name=:storage,storage_path=:path,extension=:extension,mime_type=:mime,size_bytes=:size,checksum_sha256=:checksum,uploaded_by=:actor,created_at=UTC_TIMESTAMP() WHERE id=:file AND project_id=:project');
        $q->execute(['name'=>$stored['original_name'],'storage'=>$stored['storage_name'],'path'=>$stored['storage_path'],'extension'=>$stored['extension'],'mime'=>$stored['mime_type'],'size'=>$stored['size_bytes'],'checksum'=>$stored['checksum_sha256'],'actor'=>$actor,'file'=>$fileId,'project'=>$projectId]);
        return ['file'=>$this->findActiveFile($projectId,$fileId),'version_number'=>$version,'previous_version_id'=>$previousVersionId,'old'=>$old];
    }
    public function setPresentation(int $projectId,?int $fileId,int $actor):array
    {
        $project=$this->lockProject($projectId);if($fileId!==null){$file=$this->findActiveFile($projectId,$fileId,true);if(!in_array(strtolower((string)$file['extension']),['pdf','docx','png','jpg','jpeg','webp','txt'],true))throw new InvalidArgumentException('El archivo seleccionado no es compatible con la vista de presentación.');}
        $q=$this->db->prepare('UPDATE projects SET presentation_file_id=:file,updated_at=updated_at WHERE id=:project');$q->execute(['file'=>$fileId,'project'=>$projectId]);
        return ['previous_file_id'=>$project['presentation_file_id']===null?null:(int)$project['presentation_file_id'],'file_id'=>$fileId];
    }
    public function retire(int $projectId,array $ids,int $actor):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>$id>0)));if(!$ids)throw new InvalidArgumentException('Selecciona al menos un archivo.');
        $files=[];foreach($ids as $id)$files[]=$this->findActiveFile($projectId,$id,true);
        $placeholders=implode(',',array_fill(0,count($ids),'?'));$q=$this->db->prepare("UPDATE project_files SET deleted_at=UTC_TIMESTAMP(),deleted_by=? WHERE project_id=? AND id IN ($placeholders) AND deleted_at IS NULL");$q->execute([$actor,$projectId,...$ids]);
        $q=$this->db->prepare("UPDATE projects SET presentation_file_id=NULL WHERE id=? AND presentation_file_id IN ($placeholders)");$q->execute([$projectId,...$ids]);return $files;
    }
    public function restorable(int $projectId):array
    {
        $q=$this->db->prepare("SELECT f.*,u.full_name deleted_by_name,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),DATE_ADD(f.deleted_at,INTERVAL 24 HOUR)) remaining_seconds FROM project_files f LEFT JOIN users u ON u.id=f.deleted_by WHERE f.project_id=:id AND f.deleted_at IS NOT NULL AND f.purged_at IS NULL AND f.deleted_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR) ORDER BY f.deleted_at DESC");$q->execute(['id'=>$projectId]);
        $storage=new ProjectDocumentFileService();$files=[];foreach($q->fetchAll() as $f){$seconds=max(0,(int)$f['remaining_seconds']);if($seconds<1)continue;try{$storage->resolveStoredFile($projectId,(string)$f['storage_name']);}catch(Throwable){continue;}$files[]=['id'=>(int)$f['id'],'name'=>$f['original_name'],'extension'=>$f['extension'],'size'=>ArchiveService::formatBytes((int)$f['size_bytes']),'size_bytes'=>(int)$f['size_bytes'],'deleted_at'=>$f['deleted_at'],'deleted_at_label'=>date('d/m/Y H:i',strtotime($f['deleted_at'])),'deleted_by_name'=>$f['deleted_by_name']?:'Administración','remaining_seconds'=>$seconds,'remaining_label'=>floor($seconds/3600).' h '.floor(($seconds%3600)/60).' min'];}return $files;
    }
    public function inspectRestore(int $projectId,int $fileId):array
    {
        $q=$this->db->prepare("SELECT * FROM project_files WHERE project_id=:project AND id=:file AND deleted_at IS NOT NULL AND purged_at IS NULL AND deleted_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR) FOR UPDATE");$q->execute(['project'=>$projectId,'file'=>$fileId]);$f=$q->fetch();if(!$f)throw new InvalidArgumentException('El plazo de recuperación de este archivo finalizó.');
        $storage=new ProjectDocumentFileService();try{$retiredPath=$storage->resolveStoredFile($projectId,(string)$f['storage_name']);}catch(Throwable){throw new InvalidArgumentException('El archivo físico ya no está disponible y no puede restaurarse.');}
        $active=$this->db->prepare('SELECT id,original_name,storage_name FROM project_files WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL AND id<>:id FOR UPDATE');$active->execute(['project'=>$projectId,'id'=>$fileId]);$activeFiles=$active->fetchAll();$normalized=$this->normalizeRestoreName((string)$f['original_name']);$sameName=current(array_filter($activeFiles,fn(array $candidate):bool=>$this->normalizeRestoreName((string)$candidate['original_name'])===$normalized))?:null;$conflict=false;$finalName=(string)$f['original_name'];
        if($sameName!==null){try{$activePath=$storage->resolveStoredFile($projectId,(string)$sameName['storage_name']);}catch(Throwable){throw new RuntimeException('No fue posible verificar la integridad del archivo activo.');}$retiredHash=hash_file('sha256',$retiredPath);$activeHash=hash_file('sha256',$activePath);if(!is_string($retiredHash)||!is_string($activeHash))throw new RuntimeException('No fue posible verificar la integridad de los archivos.');if(hash_equals($retiredHash,$activeHash))throw new InvalidArgumentException('Este archivo ya se encuentra disponible dentro del proyecto.');$conflict=true;$finalName=$this->suggestRestoredName((string)$f['original_name'],$activeFiles);}
        return ['file_id'=>$fileId,'original_name'=>(string)$f['original_name'],'final_name'=>$finalName,'conflict'=>$conflict,'conflicting_name'=>$sameName===null?null:(string)$sameName['original_name']];
    }
    public function restore(int $projectId,int $fileId,int $actor,string $name):array
    {
        $inspection=$this->inspectRestore($projectId,$fileId);if($name===''||$this->normalizeRestoreName($name)!==$this->normalizeRestoreName((string)$inspection['final_name']))throw new InvalidArgumentException('El conflicto cambió. Revisa nuevamente el nombre propuesto.');$final=(string)$inspection['final_name'];
        $q=$this->db->prepare('UPDATE project_files SET original_name=:name,deleted_at=NULL,deleted_by=NULL WHERE project_id=:project AND id=:file AND deleted_at IS NOT NULL');$q->execute(['name'=>$final,'project'=>$projectId,'file'=>$fileId]);if($q->rowCount()!==1)throw new InvalidArgumentException('El archivo ya fue restaurado.');$f=$this->findActiveFile($projectId,$fileId);return $f+['final_name'=>$final,'conflict'=>$inspection['conflict'],'restored_original_name'=>$inspection['original_name']];
    }
    public function purge(int $projectId,array $ids,int $actor):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>$id>0)));if(!$ids)throw new InvalidArgumentException('Selecciona al menos un archivo.');$files=[];
        foreach($ids as $id){$q=$this->db->prepare('SELECT * FROM project_files WHERE project_id=:project AND id=:file AND deleted_at IS NOT NULL AND purged_at IS NULL FOR UPDATE');$q->execute(['project'=>$projectId,'file'=>$id]);$f=$q->fetch();if(!$f)throw new InvalidArgumentException('Uno de los archivos ya no está disponible.');$files[]=$f;}
        $ph=implode(',',array_fill(0,count($ids),'?'));$q=$this->db->prepare("UPDATE project_files SET purged_at=UTC_TIMESTAMP(),purged_by=? WHERE project_id=? AND id IN ($ph)");$q->execute([$actor,$projectId,...$ids]);return $files;
    }
    public function versions(int $projectId):array
    {
        $q=$this->db->prepare('SELECT v.*,u.full_name responsible FROM project_file_versions v LEFT JOIN users u ON u.id=v.replaced_by WHERE v.project_id=:id ORDER BY v.replaced_at DESC,v.id DESC');$q->execute(['id'=>$projectId]);return $q->fetchAll();
    }
    private function normalizeRestoreName(string $name):string{return mb_strtolower(trim($name),'UTF-8');}
    private function suggestRestoredName(string $originalName,array $activeFiles):string
    {
        $extension=pathinfo($originalName,PATHINFO_EXTENSION);$base=pathinfo($originalName,PATHINFO_FILENAME);$activeNames=array_fill_keys(array_map(fn(array $file):string=>$this->normalizeRestoreName((string)$file['original_name']),$activeFiles),true);
        for($number=1;$number<10000;$number++){$suffix=$number===1?' (restaurado)':' (restaurado '.$number.')';$candidate=$base.$suffix.($extension===''?'':'.'.$extension);if(!isset($activeNames[$this->normalizeRestoreName($candidate)]))return $candidate;}
        throw new RuntimeException('No fue posible generar un nombre disponible para la restauración.');
    }
}
