<?php
declare(strict_types=1);
final class PrivateProjectFileService
{
    private const MIME_BY_EXTENSION=['pdf'=>['application/pdf'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],'zip'=>['application/zip','application/x-zip-compressed']];
    private string $root;
    public function __construct(){ $this->root=ROOT_PATH.'/storage/private/projects'; }
    public function validateUpload(array $file):array
    {
        $limits=$this->limits();$error=(int)($file['error']??UPLOAD_ERR_NO_FILE);if($error!==UPLOAD_ERR_OK)throw new InvalidArgumentException($error===UPLOAD_ERR_NO_FILE?'No se seleccionó ningún archivo.':'El archivo no se recibió correctamente (error '.$error.').');$size=(int)($file['size']??0);if($size<1||$size>$limits['max_bytes'])throw new InvalidArgumentException('El archivo está vacío o supera el límite individual de '.$limits['max_mb'].' MB.');$raw=(string)($file['name']??'');$original=basename($raw);if($raw===''||$raw!==$original||preg_match('/[\x00-\x1F\x7F]/u',$raw)||!preg_match('/^[\pL\pN _().-]+$/u',$raw))throw new InvalidArgumentException('El nombre del archivo contiene caracteres o rutas no seguras.');$extension=mb_strtolower(pathinfo($original,PATHINFO_EXTENSION));if(!in_array($extension,$limits['extensions'],true))throw new InvalidArgumentException('La extensión del archivo no está permitida.');$temporary=(string)($file['tmp_name']??'');$mime=is_file($temporary)?(new finfo(FILEINFO_MIME_TYPE))->file($temporary):'';if(!in_array($mime,self::MIME_BY_EXTENSION[$extension]??[],true))throw new InvalidArgumentException('El contenido del archivo no coincide con su formato.');return ['original_name'=>$original,'extension'=>$extension,'mime_type'=>$mime,'size_bytes'=>$size];
    }
    public function limits():array{try{$model=new SystemSettingModel();$settings=$model->all();$policy=$model->fileUploadPolicy();}catch(Throwable){$settings=(new SystemSettingModel())->defaults();$policy=['max_mb'=>20,'total_max_mb'=>35,'max_bytes'=>20*1024*1024,'max_total_bytes'=>35*1024*1024];}$configuredPrivate=$settings['file_extensions_private']??$settings['file_extensions'];$extensions=array_values(array_intersect(array_keys(self::MIME_BY_EXTENSION),(array)$configuredPrivate));$mimes=[];foreach($extensions as $extension)$mimes=[...$mimes,...self::MIME_BY_EXTENSION[$extension]];return ['extensions'=>$extensions,'mime_types'=>array_values(array_unique($mimes)),'max_bytes'=>$policy['max_bytes'],'max_total_bytes'=>$policy['max_total_bytes'],'max_mb'=>$policy['max_mb'],'max_total_mb'=>$policy['total_max_mb']];}
    public function projectDirectory(int $projectId):string{if($projectId<1)throw new InvalidArgumentException('Identificador inválido.');return $this->root.DIRECTORY_SEPARATOR.$projectId;}
    /** Valida un archivo privado que ya fue recibido y almacenado temporalmente. */
    public function validateStoredFile(string $path,array $metadata):array
    {
        $limits=$this->limits();$extension=mb_strtolower((string)($metadata['extension']??''));
        if(!is_file($path)||!is_readable($path))throw new RuntimeException('El archivo temporal no está disponible.');
        if(!in_array($extension,$limits['extensions'],true)||!isset(self::MIME_BY_EXTENSION[$extension]))throw new InvalidArgumentException('El formato del archivo no está permitido.');
        $size=filesize($path);if($size===false||$size<1||$size>(int)$limits['max_bytes']||$size!==(int)($metadata['size_bytes']??-1))throw new InvalidArgumentException('El tamaño del archivo temporal no es válido.');
        $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,self::MIME_BY_EXTENSION[$extension],true)||$mime!==(string)($metadata['mime_type']??''))throw new InvalidArgumentException('El contenido del archivo temporal no coincide con su formato.');
        $hash=hash_file('sha256',$path);if(!is_string($hash)||!hash_equals((string)($metadata['checksum_sha256']??''),$hash))throw new InvalidArgumentException('La integridad del archivo temporal no es válida.');
        return ['extension'=>$extension,'mime_type'=>$mime,'size_bytes'=>(int)$size,'checksum_sha256'=>$hash];
    }
    /** Promueve un archivo ya validado al directorio privado definitivo. */
    public function promoteStoredFile(int $projectId,string $source,string $extension):array
    {
        $directory=$this->projectDirectory($projectId);if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('No fue posible preparar el almacenamiento definitivo.');
        $storage=bin2hex(random_bytes(32)).'.'.$extension;$destination=$directory.DIRECTORY_SEPARATOR.$storage;
        if(!@rename($source,$destination))throw new RuntimeException('No fue posible promover el archivo temporal.');
        return ['storage_name'=>$storage,'storage_path'=>'storage/private/projects/'.$projectId.'/'.$storage,'absolute_path'=>$destination];
    }
    public function resolveStoredFile(int $projectId,string $storageName):string{$extensions=implode('|',array_map(static fn(string $value):string=>preg_quote($value,'/'),array_keys(self::MIME_BY_EXTENSION)));if(!preg_match('/^[a-f0-9]{32,64}\.('.$extensions.')$/',$storageName))throw new InvalidArgumentException('Nombre de almacenamiento inválido.');$directory=$this->projectDirectory($projectId);$candidate=$directory.DIRECTORY_SEPARATOR.$storageName;$resolvedDirectory=realpath($directory);$resolvedFile=realpath($candidate);if($resolvedDirectory===false||$resolvedFile===false||!str_starts_with($resolvedFile,$resolvedDirectory.DIRECTORY_SEPARATOR))throw new RuntimeException('El archivo privado no existe.');return $resolvedFile;}
}
