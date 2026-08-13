<?php
declare(strict_types=1);
final class ProjectDocumentFileService
{
    private const MIME=['pdf'=>['application/pdf'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'webp'=>['image/webp'],'txt'=>['text/plain'],'zip'=>['application/zip','application/x-zip-compressed']];
    public function limits():array
    {
        try {
            $model = new SystemSettingModel();$settings = $model->all();$policy=$model->fileUploadPolicy();
            $configured = (array)($settings['file_extensions_project'] ?? array_keys(self::MIME));
        } catch (Throwable) {
            $configured = array_keys(self::MIME);$policy=['max_mb'=>20,'total_max_mb'=>35,'max_bytes'=>20*1024*1024,'max_total_bytes'=>35*1024*1024];
        }
        $allowed = array_values(array_intersect(array_keys(self::MIME), $configured));
        return ['extensions'=>$allowed,'max_operation_files'=>5,'max_file_bytes'=>$policy['max_bytes'],'max_operation_bytes'=>$policy['max_total_bytes'],'max_file_mb'=>$policy['max_mb'],'max_operation_mb'=>$policy['total_max_mb'],'max_name_length'=>200];
    }
    public function storeUpload(int $projectId,array $file):array
    {
        $limits=$this->limits();
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);if($error!==UPLOAD_ERR_OK)throw new InvalidArgumentException($error===UPLOAD_ERR_NO_FILE?'Selecciona un archivo.':'El archivo no se recibió correctamente.');$size=(int)($file['size']??0);if($size<1||$size>(int)$limits['max_file_bytes'])throw new InvalidArgumentException('El archivo está vacío o supera el límite máximo permitido de '.$limits['max_file_mb'].' MB.');$raw=(string)($file['name']??'');$name=basename(str_replace('\\','/',$raw));if($name===''||$raw!==$name||mb_strlen($name,'UTF-8')>200||preg_match('/[\x00-\x1F\x7F]/u',$name))throw new InvalidArgumentException('El nombre del archivo no es válido.');$ext=mb_strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,$limits['extensions'],true)||!isset(self::MIME[$ext]))throw new InvalidArgumentException('El formato del archivo no está permitido por la configuración actual.');$tmp=(string)($file['tmp_name']??'');$mime=is_file($tmp)?(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp):'';if(!in_array($mime,self::MIME[$ext],true))throw new InvalidArgumentException('El contenido no coincide con la extensión del archivo.');$dir=ROOT_PATH.'/storage/private/projects/'.$projectId;if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible preparar el almacenamiento del proyecto.');$storage=bin2hex(random_bytes(32)).'.'.$ext;$path=$dir.DIRECTORY_SEPARATOR.$storage;if(!move_uploaded_file($tmp,$path))throw new RuntimeException('No fue posible guardar el archivo recibido.');$hash=hash_file('sha256',$path);if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/',$hash)){@unlink($path);throw new RuntimeException('No fue posible calcular la integridad del archivo recibido.');}return ['original_name'=>$name,'extension'=>$ext,'mime_type'=>$mime,'size_bytes'=>$size,'storage_name'=>$storage,'storage_path'=>'storage/private/projects/'.$projectId.'/'.$storage,'checksum_sha256'=>$hash,'absolute_path'=>$path];
    }
    /** Almacena una carga temporal para una publicación sin modificar el expediente vigente. */
    public function stagePublicationUpload(int $userId,string $preparationId,array $file):array
    {
        if($userId<1||!preg_match('/^[a-f0-9]{32}$/',$preparationId))throw new InvalidArgumentException('La preparación de publicación no es válida.');
        $limits=$this->limits();$error=(int)($file['error']??UPLOAD_ERR_NO_FILE);if($error!==UPLOAD_ERR_OK)throw new InvalidArgumentException($error===UPLOAD_ERR_NO_FILE?'Selecciona un archivo.':'El archivo no se recibió correctamente.');$size=(int)($file['size']??0);if($size<1||$size>(int)$limits['max_file_bytes'])throw new InvalidArgumentException('El archivo está vacío o supera el límite máximo permitido de '.$limits['max_file_mb'].' MB.');$raw=(string)($file['name']??'');$name=basename(str_replace('\\','/',$raw));if($name===''||$raw!==$name||mb_strlen($name,'UTF-8')>200||preg_match('/[\x00-\x1F\x7F]/u',$name))throw new InvalidArgumentException('El nombre del archivo no es válido.');$ext=mb_strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,$limits['extensions'],true)||!isset(self::MIME[$ext]))throw new InvalidArgumentException('El formato del archivo no está permitido por la configuración actual.');$tmp=(string)($file['tmp_name']??'');if(!is_uploaded_file($tmp))throw new InvalidArgumentException('El archivo no se recibió correctamente.');$mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!in_array($mime,self::MIME[$ext],true))throw new InvalidArgumentException('El contenido no coincide con la extensión del archivo.');
        $dir=ROOT_PATH.'/storage/private/project-publication-preparations/'.$userId.'/'.$preparationId;if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible preparar el almacenamiento temporal.');$storage=bin2hex(random_bytes(32)).'.'.$ext;$path=$dir.DIRECTORY_SEPARATOR.$storage;if(!move_uploaded_file($tmp,$path))throw new RuntimeException('No fue posible guardar el archivo recibido.');$hash=hash_file('sha256',$path);if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/',$hash)){@unlink($path);throw new RuntimeException('No fue posible calcular la integridad del archivo recibido.');}
        if($ext==='zip'&&empty((new ArchiveService())->inspectPackage($path)['success'])){@unlink($path);throw new InvalidArgumentException('No fue posible validar el contenido del archivo ZIP.');}
        return ['original_name'=>$name,'extension'=>$ext,'mime_type'=>$mime,'size_bytes'=>$size,'storage_name'=>$storage,'checksum_sha256'=>$hash,'absolute_path'=>$path];
    }
    /** Promueve una carga preparada al almacenamiento privado definitivo y permite compensarla. */
    public function promotePublicationUpload(int $projectId,array $stored):array
    {
        $name=(string)($stored['storage_name']??'');if($projectId<1||!preg_match('/^[a-f0-9]{64}\.[a-z0-9]+$/',$name)||empty($stored['absolute_path'])||!is_file((string)$stored['absolute_path']))throw new RuntimeException('El archivo preparado ya no está disponible.');$dir=ROOT_PATH.'/storage/private/projects/'.$projectId;if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible preparar el almacenamiento del proyecto.');$target=$dir.DIRECTORY_SEPARATOR.$name;if(!@rename((string)$stored['absolute_path'],$target))throw new RuntimeException('No fue posible promover el archivo preparado.');$stored['absolute_path']=$target;$stored['storage_path']='storage/private/projects/'.$projectId.'/'.$name;return $stored;
    }
    public function restorePublicationUpload(array $prepared,array $promoted):void
    {
        $from=(string)($promoted['absolute_path']??'');$to=(string)($prepared['absolute_path']??'');if($from!==''&&$to!==''&&is_file($from)){if(!is_dir(dirname($to)))@mkdir(dirname($to),0775,true);@rename($from,$to);}
    }
    public function resolveStoredFile(int $projectId,string $storageName):string{$ext=implode('|',array_map(fn($v)=>preg_quote($v,'/'),array_keys(self::MIME)));if(!preg_match('/^[a-f0-9]{32,64}\.('.$ext.')$/',$storageName))throw new InvalidArgumentException('Nombre de almacenamiento inválido.');$base=realpath(ROOT_PATH.'/storage/private/projects/'.$projectId);$path=realpath(ROOT_PATH.'/storage/private/projects/'.$projectId.'/'.$storageName);if($base===false||$path===false||!is_file($path)||!str_starts_with(strtolower($path),strtolower($base.DIRECTORY_SEPARATOR)))throw new RuntimeException('El archivo no existe.');return $path;}
    public function discardStored(array $stored):void{if(!empty($stored['absolute_path'])&&is_file((string)$stored['absolute_path']))@unlink((string)$stored['absolute_path']);}
}
