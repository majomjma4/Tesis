<?php
declare(strict_types=1);

/** Stages known private entity storage so database rollback can restore it. */
final class TrashStoragePurgeService
{
    public function stage(string $kind, int $id): array
    {
        $base = match ($kind) {
            'project' => ROOT_PATH . '/storage/private/projects',
            'material' => ROOT_PATH . '/storage/support-materials',
            default => throw new InvalidArgumentException('Tipo de almacenamiento no válido.'),
        };
        $source = $base . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($source)) return ['source'=>$source, 'staged'=>null, 'missing'=>true];
        $holding = $base . DIRECTORY_SEPARATOR . '.trash-purge';
        if (!is_dir($holding) && !mkdir($holding, 0775, true) && !is_dir($holding)) throw new RuntimeException('No fue posible preparar la eliminación segura de archivos.');
        $staged = $holding . DIRECTORY_SEPARATOR . $kind . '-' . $id . '-' . bin2hex(random_bytes(8));
        if (!rename($source, $staged)) throw new RuntimeException('No fue posible aislar los archivos para su eliminación.');
        return ['source'=>$source, 'staged'=>$staged, 'missing'=>false];
    }
    public function restore(array $stage): void
    {
        if (empty($stage['staged']) || !is_dir((string)$stage['staged'])) return;
        if (is_dir((string)$stage['source'])) throw new RuntimeException('La ubicación original del almacenamiento ya existe.');
        if (!@rename((string)$stage['staged'], (string)$stage['source'])) throw new RuntimeException('No fue posible restaurar el almacenamiento después del fallo.');
    }
    public function destroy(array $stage): void
    {
        $path=(string)($stage['staged']??'');if($path===''||!is_dir($path))return;$root=realpath(ROOT_PATH.'/storage');$real=realpath($path);
        if($root===false||$real===false||!str_starts_with(strtolower($real),strtolower($root.DIRECTORY_SEPARATOR)))throw new RuntimeException('La ruta temporal de eliminación no es segura.');
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $entry){if($entry->isDir()){if(!@rmdir($entry->getPathname()))throw new RuntimeException('No fue posible eliminar un directorio de almacenamiento.');}elseif(!@unlink($entry->getPathname()))throw new RuntimeException('No fue posible eliminar un archivo de almacenamiento.');}
        if(!@rmdir($real))throw new RuntimeException('No fue posible finalizar la limpieza del almacenamiento.');
    }
}
