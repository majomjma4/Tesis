<?php

declare(strict_types=1);

/** Abstrae almacenamiento activo/archivado sin revelar rutas a contratos externos. */
final class ProjectDocumentStorageService
{
    public function verifyHistoricalBinary(array $version):array
    {
        try{$path=(new ProjectDocumentFileService())->resolveStoredFile((int)$version['project_id'],(string)$version['storage_name']);}
        catch(Throwable $e){return ['exists'=>false,'verified'=>false,'checksum_verified'=>false,'size_verified'=>false,'mime_verified'=>false,'reason'=>'El binario histórico no está disponible.'];}
        $checksum=hash_file('sha256',$path);$size=filesize($path);$mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($path);
        $checksumOk=is_string($checksum)&&hash_equals((string)$version['checksum_sha256'],$checksum);$sizeOk=is_int($size)&&(int)$version['size_bytes']===$size;$mimeOk=$mime===(string)$version['mime_type'];
        return ['exists'=>true,'verified'=>$checksumOk&&$sizeOk&&$mimeOk,'checksum_verified'=>$checksumOk,'size_verified'=>$sizeOk,'mime_verified'=>$mimeOk,'reason'=>$checksumOk&&$sizeOk&&$mimeOk?null:'La integridad, tamaño o tipo MIME no coincide con el manifiesto técnico.'];
    }
    public function logicalLocation(string $tier):string{return $tier==='archive'?'project_document_archive':'project_document_active';}
}
