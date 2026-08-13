<?php

declare(strict_types=1);

/** Estado temporal, por sesión, de archivos que un estudiante prepara antes de publicar. */
final class ProjectPublicationPreparationService
{
    private const SESSION_KEY = 'student_project_publication_preparations';

    public function begin(int $projectId,int $userId):array
    {
        $preview=(new ProjectStudentPublicationService())->preview($projectId,$userId);
        $this->startSession();$id=bin2hex(random_bytes(16));$items=[];
        foreach($preview['files'] as $file)$items[(int)$file['id']]=['file_id'=>(int)$file['id'],'name'=>(string)$file['name'],'size_bytes'=>(int)($file['size_bytes']??0),'extension'=>(string)($file['extension']??''),'included'=>true];
        $_SESSION[self::SESSION_KEY][$id]=['id'=>$id,'project_id'=>$projectId,'user_id'=>$userId,'created_at'=>time(),'items'=>$items,'additions'=>[],'replacements'=>[]];
        return $this->summary($this->plan($id,$projectId,$userId))+['preparation_id'=>$id];
    }

    public function add(int $projectId,int $userId,string $id,array $upload):array
    {
        $plan=$this->plan($id,$projectId,$userId);(new ProjectStudentPublicationService())->preview($projectId,$userId);
        $stored=(new ProjectDocumentFileService())->stagePublicationUpload($userId,$id,$upload);
        try {
            if($this->hasChecksum($plan,(string)$stored['checksum_sha256']))throw new ProjectStudentPublicationException('Este archivo ya está incluido en la publicación.',422);
            $key=bin2hex(random_bytes(8));$plan['additions'][$key]=$stored+['key'=>$key,'included'=>true];$this->save($plan);return $this->summary($plan);
        } catch(Throwable $error) {(new ProjectDocumentFileService())->discardStored($stored);throw $error;}
    }

    public function replace(int $projectId,int $userId,string $id,int $fileId,array $upload):array
    {
        $plan=$this->plan($id,$projectId,$userId);if(!isset($plan['items'][$fileId])||empty($plan['items'][$fileId]['included']))throw new ProjectStudentPublicationException('El archivo seleccionado ya no forma parte de la publicación.',422);
        (new ProjectStudentPublicationService())->preview($projectId,$userId);$current=$this->currentChecksum($projectId,$fileId);$stored=(new ProjectDocumentFileService())->stagePublicationUpload($userId,$id,$upload);
        try {
            if(hash_equals($current,(string)$stored['checksum_sha256']))throw new ProjectStudentPublicationException('Este archivo es idéntico a la versión actual. No es necesario reemplazarlo.',422);
            if($this->hasChecksum($plan,(string)$stored['checksum_sha256'],$fileId))throw new ProjectStudentPublicationException('Este archivo ya está incluido en la publicación.',422);
            if(isset($plan['replacements'][$fileId]))(new ProjectDocumentFileService())->discardStored($plan['replacements'][$fileId]);
            $plan['replacements'][$fileId]=$stored+['file_id'=>$fileId];$this->save($plan);return $this->summary($plan);
        } catch(Throwable $error) {(new ProjectDocumentFileService())->discardStored($stored);throw $error;}
    }

    public function setIncluded(int $projectId,int $userId,string $id,int $fileId,bool $included):array
    {
        $plan=$this->plan($id,$projectId,$userId);if(!isset($plan['items'][$fileId]))throw new ProjectStudentPublicationException('El archivo seleccionado no existe.',422);(new ProjectStudentPublicationService())->preview($projectId,$userId);
        $plan['items'][$fileId]['included']=$included;if(!$included&&isset($plan['replacements'][$fileId])){(new ProjectDocumentFileService())->discardStored($plan['replacements'][$fileId]);unset($plan['replacements'][$fileId]);}$this->save($plan);return $this->summary($plan);
    }

    public function removeAddition(int $projectId,int $userId,string $id,string $key):array
    {
        $plan=$this->plan($id,$projectId,$userId);if(!preg_match('/^[a-f0-9]{16}$/',$key)||!isset($plan['additions'][$key]))throw new ProjectStudentPublicationException('El archivo agregado ya no está disponible.',422);(new ProjectStudentPublicationService())->preview($projectId,$userId);(new ProjectDocumentFileService())->discardStored($plan['additions'][$key]);unset($plan['additions'][$key]);$this->save($plan);return $this->summary($plan);
    }

    public function cancel(int $projectId,int $userId,string $id):void
    {
        $plan=$this->plan($id,$projectId,$userId);$this->discard($plan);$this->startSession();unset($_SESSION[self::SESSION_KEY][$id]);
    }

    /** @return array<string,mixed> */
    public function plan(string $id,int $projectId,int $userId):array
    {
        $this->startSession();$plan=$_SESSION[self::SESSION_KEY][$id]??null;if(!is_array($plan)||(int)($plan['project_id']??0)!==$projectId||(int)($plan['user_id']??0)!==$userId)throw new ProjectStudentPublicationException('La preparación de publicación ya no está disponible. Vuelve a intentarlo.',409);return $plan;
    }

    public function complete(array $plan):void
    {
        $this->discard($plan);$this->startSession();unset($_SESSION[self::SESSION_KEY][(string)$plan['id']]);
    }

    /** @return array{file_count:int,files:list<array<string,mixed>>} */
    public function summary(array $plan):array
    {
        $files=[];foreach((array)$plan['items'] as $fileId=>$item){if(empty($item['included']))continue;$replacement=$plan['replacements'][$fileId]??null;$files[]=['key'=>'existing:'.$fileId,'file_id'=>(int)$fileId,'name'=>(string)($replacement['original_name']??$item['name']),'size_bytes'=>(int)($replacement['size_bytes']??$item['size_bytes']??0),'extension'=>(string)($replacement['extension']??$item['extension']??''),'kind'=>$replacement?'updated':'current','replaceable'=>true];}
        foreach((array)$plan['additions'] as $key=>$item){if(empty($item['included']))continue;$files[]=['key'=>'new:'.$key,'file_id'=>null,'name'=>(string)$item['original_name'],'size_bytes'=>(int)($item['size_bytes']??0),'extension'=>(string)($item['extension']??''),'kind'=>'new','replaceable'=>false];}
        return ['file_count'=>count($files),'files'=>$files];
    }

    private function save(array $plan):void{$this->startSession();$_SESSION[self::SESSION_KEY][(string)$plan['id']]=$plan;}
    private function startSession():void{(new AuthSessionService())->start();$_SESSION[self::SESSION_KEY]??=[];}
    private function hasChecksum(array $plan,string $checksum,?int $except=null):bool
    {
        foreach((array)$plan['replacements'] as $fileId=>$file)if((int)$fileId!==$except&&hash_equals((string)$file['checksum_sha256'],$checksum))return true;
        foreach((array)$plan['additions'] as $file)if(hash_equals((string)$file['checksum_sha256'],$checksum))return true;
        $ids=[];foreach((array)($plan['items']??[]) as $fileId=>$item)if(!empty($item['included'])&&(int)$fileId!==$except)$ids[]=(int)$fileId;if($ids===[])return false;$marks=implode(',',array_fill(0,count($ids),'?'));$query=Database::connection()->prepare("SELECT checksum_sha256 FROM project_files WHERE project_id=? AND id IN ($marks) AND deleted_at IS NULL AND purged_at IS NULL");$query->execute([(int)$plan['project_id'],...$ids]);foreach($query->fetchAll(PDO::FETCH_COLUMN) as $current)if(hash_equals((string)$current,$checksum))return true;return false;
    }
    private function currentChecksum(int $projectId,int $fileId):string{$query=Database::connection()->prepare('SELECT checksum_sha256 FROM project_files WHERE project_id=:project AND id=:file AND deleted_at IS NULL AND purged_at IS NULL');$query->execute(['project'=>$projectId,'file'=>$fileId]);$checksum=$query->fetchColumn();if(!is_string($checksum)||!preg_match('/^[a-f0-9]{64}$/',$checksum))throw new ProjectStudentPublicationException('El archivo seleccionado ya no está disponible.',409);return $checksum;}
    private function discard(array $plan):void{$storage=new ProjectDocumentFileService();foreach((array)($plan['additions']??[]) as $file)$storage->discardStored($file);foreach((array)($plan['replacements']??[]) as $file)$storage->discardStored($file);$path=ROOT_PATH.'/storage/private/project-publication-preparations/'.(int)$plan['user_id'].'/'.(string)$plan['id'];if(is_dir($path))@rmdir($path);}
}
