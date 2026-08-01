<?php

declare(strict_types=1);

final class SupportMaterialPackageService
{
    public function describe(array $material): array
    {
        $files = array_values(array_filter((array) ($material['files'] ?? []), static fn (array $file): bool => empty($file['package'])));
        $stored = is_array($material['package'] ?? null) ? $material['package'] : null;
        $analysis = $stored === null ? null : $this->analyzeStoredPackage($stored, $files);
        $size = array_sum(array_map(static fn (array $file): int => (int) ($file['size_bytes'] ?? 0), $files));
        return [
            'available' => count($files) > 1
                && $this->allFilesAvailable($files)
                && (!empty($analysis['exact']) || $this->canCreatePackage()),
            'file_count' => count($files),
            'size_bytes' => $size,
            'size' => ArchiveService::formatBytes($size),
            'source' => !empty($analysis['exact']) ? 'stored' : 'generated',
            'browsable' => false,
            'stored_outdated' => $stored !== null && empty($analysis['exact']),
            'stored_analysis' => $analysis,
        ];
    }

    public function prepare(array $material): array
    {
        $description = $this->describe($material);
        if (!$description['available']) {
            throw new InvalidArgumentException('Este material no requiere un paquete completo.');
        }
        $regularFiles = array_values(array_filter(
            (array) ($material['files'] ?? []),
            static fn (array $file): bool => empty($file['package'])
        ));
        return $this->generateVerified($regularFiles, (int) ($material['id'] ?? 0));
    }

    private function analyzeStoredPackage(array $stored, array $files): array
    {
        $storedPath = $this->resolveStoredPath((string) ($stored['path'] ?? ''));
        if ($storedPath === null) return ['exact' => false, 'browsable' => false, 'status' => 'not_found'];
        $inspection = (new ArchiveService())->inspectPackage($storedPath);
        if (!$inspection['success']) {
            return ['exact' => false, 'browsable' => false, 'status' => $inspection['status']];
        }
        $expected = [];
        foreach ($files as $file) {
            $expected[$this->comparisonKey((string) $file['name'], (int) $file['size_bytes'])] =
                ($expected[$this->comparisonKey((string) $file['name'], (int) $file['size_bytes'])] ?? 0) + 1;
        }
        $actual = [];
        $hasDirectories = false;
        $hasNestedPaths = false;
        foreach ($inspection['entries'] as $entry) {
            if ($entry['is_dir']) {
                $hasDirectories = true;
                continue;
            }
            if (str_contains((string) $entry['name'], '/')) $hasNestedPaths = true;
            $key = $this->comparisonKey(basename((string) $entry['name']), (int) $entry['size']);
            $actual[$key] = ($actual[$key] ?? 0) + 1;
        }
        ksort($expected);
        ksort($actual);
        $exact = !$hasDirectories && !$hasNestedPaths && $expected === $actual;
        return [
            'exact' => $exact,
            'browsable' => false,
            'status' => 'ready',
        ];
    }

    private function comparisonKey(string $name, int $size): string
    {
        return mb_strtolower(trim($name), 'UTF-8') . "\0" . $size;
    }

    private function canCreatePackage(): bool
    {
        return class_exists('ZipArchive') || class_exists('PharData');
    }

    private function allFilesAvailable(array $files): bool
    {
        foreach ($files as $file) {
            if ($this->resolveStoredPath((string) ($file['path'] ?? '')) === null) return false;
        }
        return true;
    }

    private function generateVerified(array $files,int $materialId):array
    {
        $integrity=new ArchivePackageIntegrityService();
        $entries=$integrity->validateSources($files,fn(array $file):?string=>$this->resolveStoredPath((string)($file['path']??'')));
        $directory=sys_get_temp_dir().DIRECTORY_SEPARATOR.'tesis-support-material-packages';
        if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('No fue posible preparar el paquete.');
        $path=$directory.DIRECTORY_SEPARATOR.'material-'.$materialId.'-'.bin2hex(random_bytes(12)).'.zip';
        $integrity->build($path,$entries);
        return ['path'=>$path,'temporary'=>true,'file_count'=>count($entries),'source'=>'generated','size_bytes'=>(int)filesize($path)];
    }

    private function generate(array $files, int $materialId): array
    {
        $signatureParts = [];
        foreach ($files as $file) {
            $path = $this->resolveStoredPath((string) ($file['path'] ?? ''));
            if ($path === null) throw new RuntimeException('No fue posible incorporar todos los archivos al paquete.');
            $signatureParts[] = [
                'id' => (int) ($file['id'] ?? 0),
                'path' => str_replace('\\', '/', (string) ($file['path'] ?? '')),
                'size' => (int) ($file['size_bytes'] ?? 0),
                'modified' => (int) (filemtime($path) ?: 0),
            ];
        }
        $cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tesis-support-material-packages';
        if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0775, true) && !is_dir($cacheDirectory)) {
            throw new RuntimeException('No fue posible preparar la caché del paquete.');
        }
        $signature = hash('sha256', json_encode($signatureParts, JSON_UNESCAPED_SLASHES) ?: '');
        $zipPath = $cacheDirectory . DIRECTORY_SEPARATOR . 'material-' . $materialId . '-' . $signature . '.zip';
        if (is_file($zipPath) && is_readable($zipPath) && (int) filesize($zipPath) > 0) {
            return ['path' => $zipPath, 'temporary' => false, 'file_count' => count($files), 'source' => 'cached', 'size_bytes' => (int) filesize($zipPath)];
        }
        $lockPath = $cacheDirectory . DIRECTORY_SEPARATOR . 'material-' . $materialId . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('No fue posible bloquear la preparación del paquete.');
        }
        if (is_file($zipPath) && is_readable($zipPath) && (int) filesize($zipPath) > 0) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return ['path' => $zipPath, 'temporary' => false, 'file_count' => count($files), 'source' => 'cached', 'size_bytes' => (int) filesize($zipPath)];
        }
        $temporaryBase = tempnam($cacheDirectory, 'building-');
        if ($temporaryBase === false) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw new RuntimeException('No fue posible preparar el paquete.');
        }
        $buildingPath = $temporaryBase . '.zip';
        @unlink($temporaryBase);
        $usedNames = [];
        try {
            if (class_exists('ZipArchive')) {
                $archive = new ZipArchive();
                if ($archive->open($buildingPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('No fue posible crear el paquete.');
                }
                try {
                    foreach ($files as $file) {
                        $name = $this->uniqueSafeName((string) $file['name'], $usedNames);
                        $sourcePath = $this->resolveStoredPath((string) ($file['path'] ?? ''));
                        if ($sourcePath === null || !$archive->addFile($sourcePath, $name)) {
                            throw new RuntimeException('No fue posible incorporar todos los archivos al paquete.');
                        }
                    }
                } finally {
                    $archive->close();
                }
            } else {
                $archive = new PharData($buildingPath);
                foreach ($files as $file) {
                    $name = $this->uniqueSafeName((string) $file['name'], $usedNames);
                    $sourcePath = $this->resolveStoredPath((string) ($file['path'] ?? ''));
                    if ($sourcePath === null) {
                        throw new RuntimeException('No fue posible incorporar todos los archivos al paquete.');
                    }
                    $archive->addFile($sourcePath, $name);
                }
                unset($archive);
            }
            if (!is_file($buildingPath) || filesize($buildingPath) === false) throw new RuntimeException('No fue posible finalizar el paquete.');
            if (!@rename($buildingPath, $zipPath)) {
                if (!is_file($zipPath)) throw new RuntimeException('No fue posible conservar el paquete preparado.');
                @unlink($buildingPath);
            }
            foreach (glob($cacheDirectory . DIRECTORY_SEPARATOR . 'material-' . $materialId . '-*.zip') ?: [] as $cached) {
                if ($cached !== $zipPath && is_file($cached)) @unlink($cached);
            }
            return ['path' => $zipPath, 'temporary' => false, 'file_count' => count($files), 'source' => 'cached', 'size_bytes' => (int) filesize($zipPath)];
        } catch (Throwable $exception) {
            if (is_file($buildingPath)) @unlink($buildingPath);
            throw $exception;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function uniqueSafeName(string $originalName, array &$usedNames): string
    {
        $base = basename(str_replace('\\', '/', $originalName));
        $base = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $base) ?: 'archivo';
        $base = mb_substr(trim($base, " ."), 0, 180, 'UTF-8') ?: 'archivo';
        $extension = pathinfo($base, PATHINFO_EXTENSION);
        $stem = $extension === '' ? $base : mb_substr($base, 0, -(mb_strlen($extension, 'UTF-8') + 1), 'UTF-8');
        $candidate = $base;
        $suffix = 2;
        while (isset($usedNames[mb_strtolower($candidate, 'UTF-8')])) {
            $candidate = $stem . ' (' . $suffix++ . ')' . ($extension === '' ? '' : '.' . $extension);
        }
        $usedNames[mb_strtolower($candidate, 'UTF-8')] = true;
        return $candidate;
    }

    private function resolveStoredPath(string $candidate): ?string
    {
        $base = realpath(ROOT_PATH . '/storage/support-materials');
        $path = realpath($candidate);
        if ($base === false || $path === false || !is_file($path) || !is_readable($path)) return null;
        return str_starts_with(strtolower($path), strtolower($base . DIRECTORY_SEPARATOR)) ? $path : null;
    }
}
