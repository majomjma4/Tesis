<?php

declare(strict_types=1);

/** Manages the single institutional ZIP package for a published project. */
final class ProjectRepositoryPackageService
{
    public static function packagePath(int $projectId): string
    {
        return ROOT_PATH . '/storage/repository/project_' . $projectId . '.zip';
    }

    /** @return array{available:bool,download_url:string,file_count:int,size_bytes:int,size:string,source:string} */
    public function describe(int $projectId, string $downloadUrl = ''): array
    {
        $descriptor = ['available'=>false,'download_url'=>'','file_count'=>0,'size_bytes'=>0,'size'=>'','source'=>'stored'];
        if ($projectId < 1) return $descriptor;
        $project = Database::connection()->prepare('SELECT status FROM projects WHERE id=:id AND deleted_at IS NULL');
        $project->execute(['id'=>$projectId]);
        if ((string)$project->fetchColumn() !== 'published') return $descriptor;

        $files = $this->activeFiles($projectId);
        $descriptor['file_count'] = count($files);
        $descriptor['download_url'] = $downloadUrl;
        if ($files === []) return $descriptor;
        $path = self::packagePath($projectId);
        if (!is_file($path) || !is_readable($path)) return $descriptor;
        clearstatcache(true, $path);
        $size = (int)(filesize($path) ?: 0);
        if ($size < 1 || !$this->matchesActiveFiles($path, $files)) return $descriptor;
        $descriptor['available'] = true;
        $descriptor['size_bytes'] = $size;
        $descriptor['size'] = ArchiveService::formatBytes($size);
        return $descriptor;
    }

    /** Generates a package only for a published, non-deleted project. */
    public function sync(int $projectId): ?array
    {
        if ($projectId < 1) return null;
        $statement = Database::connection()->prepare('SELECT status FROM projects WHERE id=:id AND deleted_at IS NULL');
        $statement->execute(['id'=>$projectId]);
        if ((string)$statement->fetchColumn() !== 'published') return null;
        return $this->buildPackage($projectId);
    }

    /** Builds a package when the caller already controls the publication transition. */
    public function buildForProject(int $projectId): ?array
    {
        return $projectId > 0 ? $this->buildPackage($projectId) : null;
    }

    public function invalidate(int $projectId): void
    {
        $path = self::packagePath($projectId);
        if (is_file($path)) @unlink($path);
    }

    private function buildPackage(int $projectId): ?array
    {
        $rows = $this->activeFiles($projectId);
        if ($rows === []) {
            $this->invalidate($projectId);
            return null;
        }

        $storage = new ProjectDocumentFileService();
        $fileList = [];
        foreach ($rows as $row) {
            try {
                $fileList[] = [
                    'name'=>(string)$row['original_name'],
                    'original_name'=>(string)$row['original_name'],
                    'path'=>$storage->resolveStoredFile($projectId, (string)$row['storage_name']),
                    'size_bytes'=>(int)$row['size_bytes'],
                ];
            } catch (Throwable $error) {
                // Never leave a stale ZIP downloadable after the active set changed.
                $this->invalidate($projectId);
                throw new RuntimeException('No fue posible incorporar un archivo activo al paquete del proyecto.', 0, $error);
            }
        }

        $targetDirectory = ROOT_PATH . '/storage/repository';
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('No fue posible preparar el directorio del paquete institucional.');
        }

        $entries = (new ArchivePackageIntegrityService())->validateSources(
            $fileList,
            static fn(array $file): ?string => is_file((string)($file['path'] ?? '')) ? (string)$file['path'] : null
        );
        $temporary = $targetDirectory . DIRECTORY_SEPARATOR . 'building_project_' . $projectId . '_' . bin2hex(random_bytes(8)) . '.tmp.zip';
        $final = self::packagePath($projectId);
        (new ArchivePackageIntegrityService())->build($temporary, $entries);

        // Windows cannot always replace an existing file with rename(). Keep a recoverable backup.
        $backup = $final . '.previous-' . bin2hex(random_bytes(6));
        $hadPrevious = is_file($final);
        if ($hadPrevious && !rename($final, $backup)) {
            @unlink($temporary);
            throw new RuntimeException('No fue posible preparar el reemplazo del paquete anterior.');
        }
        if (!rename($temporary, $final)) {
            if ($hadPrevious && is_file($backup)) @rename($backup, $final);
            @unlink($temporary);
            throw new RuntimeException('No fue posible conservar el paquete institucional actualizado.');
        }
        if ($hadPrevious && is_file($backup)) @unlink($backup);

        clearstatcache(true, $final);
        $size = (int)(filesize($final) ?: 0);
        if ($size < 1 || !$this->matchesActiveFiles($final, $rows)) {
            $this->invalidate($projectId);
            throw new RuntimeException('El paquete institucional generado no coincide con los archivos activos.');
        }
        return ['path'=>$final,'file_count'=>count($entries),'size_bytes'=>$size,'size'=>ArchiveService::formatBytes($size)];
    }

    /** @return list<array{id:int,original_name:string,size_bytes:int,storage_name:string}> */
    private function activeFiles(int $projectId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id,original_name,size_bytes,storage_name FROM project_files WHERE project_id=:id AND deleted_at IS NULL AND purged_at IS NULL ORDER BY sort_order ASC,id ASC'
        );
        $statement->execute(['id'=>$projectId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function matchesActiveFiles(string $path, array $files): bool
    {
        $inspection = (new ArchiveService())->inspectPackage($path);
        if (empty($inspection['success'])) return false;
        $expected = [];
        $usedNames = [];
        foreach ($files as $file) {
            $key = $this->comparisonKey($this->safeUniqueName((string)$file['original_name'], $usedNames), (int)$file['size_bytes']);
            $expected[$key] = ($expected[$key] ?? 0) + 1;
        }
        $actual = [];
        foreach ((array)($inspection['entries'] ?? []) as $entry) {
            if (!empty($entry['is_dir']) || str_contains((string)($entry['name'] ?? ''), '/')) return false;
            $key = $this->comparisonKey(basename((string)($entry['name'] ?? '')), (int)($entry['size'] ?? 0));
            $actual[$key] = ($actual[$key] ?? 0) + 1;
        }
        ksort($expected);
        ksort($actual);
        return $expected === $actual;
    }

    private function comparisonKey(string $name, int $size): string
    {
        return mb_strtolower(trim($name), 'UTF-8') . "\0" . $size;
    }

    private function safeUniqueName(string $name, array &$used): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $base) ?: 'archivo';
        $base = mb_substr(trim($base, " ."), 0, 180, 'UTF-8') ?: 'archivo';
        $extension = pathinfo($base, PATHINFO_EXTENSION);
        $stem = $extension === '' ? $base : mb_substr($base, 0, -(mb_strlen($extension, 'UTF-8') + 1), 'UTF-8');
        $candidate = $base;
        $suffix = 2;
        while (isset($used[mb_strtolower($candidate, 'UTF-8')])) {
            $candidate = $stem . ' (' . $suffix++ . ')' . ($extension === '' ? '' : '.' . $extension);
        }
        $used[mb_strtolower($candidate, 'UTF-8')] = true;
        return $candidate;
    }
}
