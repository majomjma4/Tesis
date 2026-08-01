<?php
declare(strict_types=1);

final class ArchivePackageIntegrityService
{
    public function validateSources(array $files, callable $resolver): array
    {
        $entries=[];$used=[];
        foreach($files as $file){
            $path=$resolver($file);
            if(!is_string($path)||!is_file($path)||!is_readable($path))throw new RuntimeException('No fue posible generar el paquete completo porque uno o más archivos no están disponibles. No se descargó un paquete incompleto.');
            $size=filesize($path);if($size===false)throw new RuntimeException('No fue posible generar el paquete completo porque uno o más archivos no están disponibles. No se descargó un paquete incompleto.');
            $name=$this->safeUniqueName((string)($file['name']??$file['original_name']??'archivo'),$used);
            $hash=hash_file('sha256',$path);if(!is_string($hash))throw new RuntimeException('No fue posible generar el paquete completo porque uno o más archivos no están disponibles. No se descargó un paquete incompleto.');
            $entries[]=['path'=>$path,'name'=>$name,'size'=>(int)$size,'sha256'=>$hash];
        }
        if(!$entries)throw new InvalidArgumentException('No existen archivos disponibles para generar el paquete.');
        return $entries;
    }

    public function build(string $path,array $entries): void
    {
        if(is_file($path))@unlink($path);
        try{
            if(class_exists('ZipArchive')){
                $zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No fue posible crear el paquete.');
                try{foreach($entries as $entry)if(!$zip->addFile($entry['path'],$entry['name']))throw new RuntimeException('No fue posible incorporar todos los archivos al paquete.');}
                finally{if(!$zip->close())throw new RuntimeException('No fue posible cerrar el paquete.');}
            }elseif(class_exists('PharData')){
                $zip=new PharData($path);foreach($entries as $entry)$zip->addFile($entry['path'],$entry['name']);unset($zip);
            }else throw new RuntimeException('El servidor no dispone de soporte para generar paquetes ZIP.');
            clearstatcache(true,$path);
            if(!is_file($path)||!is_readable($path)||(int)filesize($path)<1||!$this->matches($path,$entries))throw new RuntimeException('No fue posible verificar el contenido completo del paquete.');
        }catch(Throwable $error){if(is_file($path))@unlink($path);throw $error;}
    }

    public function matches(string $path,array $entries): bool
    {
        if(!is_file($path)||!is_readable($path)||(int)filesize($path)<1)return false;
        $expected=[];foreach($entries as $entry)$expected[$entry['name']]=['size'=>(int)$entry['size'],'sha256'=>$entry['sha256']];
        try{
            $actual=[];
            if(class_exists('ZipArchive')){
                $zip=new ZipArchive();if($zip->open($path)!==true)return false;
                try{for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);if(!is_array($stat)||str_ends_with((string)$stat['name'],'/'))continue;$stream=$zip->getStream((string)$stat['name']);if($stream===false)return false;$hash=hash_init('sha256');hash_update_stream($hash,$stream);fclose($stream);$actual[(string)$stat['name']]=['size'=>(int)$stat['size'],'sha256'=>hash_final($hash)];}}
                finally{$zip->close();}
            }else{
                $zip=new PharData($path);foreach(new RecursiveIteratorIterator($zip) as $entry){if($entry->isDir())continue;$hash=@hash_file('sha256',$entry->getPathName());if(!is_string($hash)){unset($zip);return false;}$actual[$entry->getFilename()]=['size'=>(int)$entry->getSize(),'sha256'=>$hash];}unset($zip);
            }
            ksort($expected);ksort($actual);return $actual===$expected;
        }catch(Throwable){return false;}
    }

    private function safeUniqueName(string $name,array &$used):string
    {
        $base=basename(str_replace('\\','/',$name));$base=preg_replace('/[\x00-\x1F\x7F\/\\:*?"<>|]+/u','_',$base)?:'archivo';$base=mb_substr(trim($base," ."),0,180,'UTF-8')?:'archivo';$extension=pathinfo($base,PATHINFO_EXTENSION);$stem=$extension===''?$base:mb_substr($base,0,-(mb_strlen($extension,'UTF-8')+1),'UTF-8');$candidate=$base;$suffix=2;while(isset($used[mb_strtolower($candidate,'UTF-8')]))$candidate=$stem.' ('.$suffix++.')'.($extension===''?'':'.'.$extension);$used[mb_strtolower($candidate,'UTF-8')]=true;return $candidate;
    }
}
