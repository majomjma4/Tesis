<?php
declare(strict_types=1);
final class ProjectPackageService
{
    public function describe(int $projectId,array $files):array
    {
        $files=array_values(array_filter($files,fn(array $f):bool=>empty($f['deleted_at'])&&empty($f['purged_at'])));$size=array_sum(array_map(fn(array $f):int=>(int)$f['size_bytes'],$files));
        return ['available'=>count($files)>1,'file_count'=>count($files),'size_bytes'=>$size,'size'=>ArchiveService::formatBytes($size),'source'=>'generated','download_url'=>route('project-package-download').'&project_id='.$projectId];
    }
    public function prepare(int $projectId,array $files):array
    {
        $description=$this->describe($projectId,$files);if(!$description['available'])throw new InvalidArgumentException('El proyecto no tiene archivos disponibles.');
        $storage=new ProjectDocumentFileService();$integrity=new ArchivePackageIntegrityService();
        $entries=$integrity->validateSources($files,fn(array $file):string=>$storage->resolveStoredFile($projectId,(string)$file['storage_name']));
        $directory=sys_get_temp_dir().DIRECTORY_SEPARATOR.'tesis-project-packages';if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('No fue posible preparar el paquete.');
        $path=$directory.DIRECTORY_SEPARATOR.'project-'.$projectId.'-'.bin2hex(random_bytes(12)).'.zip';
        $integrity->build($path,$entries);
        return ['path'=>$path,'temporary'=>true]+$description;
    }
}
