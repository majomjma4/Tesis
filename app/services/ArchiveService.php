<?php

declare(strict_types=1);

final class ArchiveService
{
    private const MAX_ENTRIES = 2000;
    private const MAX_DEPTH = 12;
    private const MAX_PATH_LENGTH = 512;
    private const MAX_ENTRY_SIZE = 104857600;
    private const MAX_UNCOMPRESSED_SIZE = 524288000;
    private const MAX_COMPRESSION_RATIO = 200;

    // Inicio de acceso público al archivo ZIP
    // Lista directorios o abre archivos internos después de validar el contenedor y la ruta solicitada.
    public function listDirectory(string $zipPath, string $requestedPath = ''): array
    {
        $normalizedPath = $this->normalizeInternalPath($requestedPath);
        if ($normalizedPath === null) {
            return $this->error('invalid_path', 'La ruta solicitada no es válida.');
        }

        if (!is_file($zipPath)) {
            return $this->error('not_found', 'El archivo del proyecto no se encuentra disponible.');
        }

        if (!is_readable($zipPath)) {
            return $this->error('unreadable', 'No fue posible abrir el contenido del proyecto.');
        }

        try {
            $entries = $this->isEmptyZip($zipPath)
                ? []
                : (class_exists('ZipArchive')
                    ? $this->readEntriesWithZipArchive($zipPath)
                    : $this->readEntriesWithPharData($zipPath));
            $this->validateArchiveLimits($entries);
        } catch (DomainException $exception) {
            error_log('ArchiveService unsafe: ' . $exception->getMessage());
            return $this->error('unsafe', 'El paquete contiene una estructura no permitida.');
        } catch (Throwable $exception) {
            error_log('ArchiveService: ' . $exception->getMessage());
            return $this->error('unreadable', 'No fue posible abrir el contenido del proyecto.');
        }

        $directory = $this->buildDirectory($entries, $normalizedPath);
        if (!$directory['exists']) {
            return $this->error('invalid_path', 'La carpeta solicitada no existe dentro del proyecto.');
        }

        return [
            'success' => true,
            'status' => empty($directory['items']) ? 'empty' : 'ready',
            'message' => empty($directory['items']) ? 'Esta carpeta no contiene archivos.' : '',
            'path' => $normalizedPath,
            'breadcrumbs' => $this->buildBreadcrumbs($normalizedPath),
            'items' => $directory['items'],
            'meta' => $this->buildArchiveMeta($entries, filesize($zipPath) ?: 0),
        ];
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                return number_format($value, $value >= 10 ? 0 : 1, '.', '') . ' ' . $unit;
            }
            $value /= 1024;
        }

        return $bytes . ' B';
    }

    public function inspectPackage(string $zipPath): array
    {
        if (!is_file($zipPath) || !is_readable($zipPath)) {
            return ['success' => false, 'status' => 'not_found', 'message' => 'El paquete no está disponible.', 'entries' => []];
        }
        try {
            $entries = $this->isEmptyZip($zipPath)
                ? []
                : (class_exists('ZipArchive')
                    ? $this->readEntriesWithZipArchive($zipPath)
                    : $this->readEntriesWithPharData($zipPath));
            $this->validateArchiveLimits($entries);
            return [
                'success' => true,
                'status' => 'ready',
                'message' => '',
                'entries' => array_map(static fn (array $entry): array => [
                    'name' => (string) $entry['name'],
                    'is_dir' => (bool) $entry['is_dir'],
                    'size' => (int) $entry['size'],
                ], $entries),
            ];
        } catch (DomainException) {
            return ['success' => false, 'status' => 'unsafe', 'message' => 'El paquete contiene una estructura no permitida.', 'entries' => []];
        } catch (Throwable $exception) {
            error_log('ArchiveService inspect: ' . $exception->getMessage());
            return ['success' => false, 'status' => 'unreadable', 'message' => 'No fue posible abrir el paquete.', 'entries' => []];
        }
    }

    public function openFileStream(string $zipPath, string $requestedPath): array
    {
        $normalizedPath = $this->normalizeInternalPath($requestedPath);
        if ($normalizedPath === null || $normalizedPath === '') {
            return $this->downloadError('invalid_path', 'La ruta solicitada no es válida.');
        }
        if (!is_file($zipPath) || !is_readable($zipPath)) {
            return $this->downloadError('not_found', 'El archivo del proyecto no se encuentra disponible.');
        }

        try {
            $entries = $this->isEmptyZip($zipPath)
                ? []
                : (class_exists('ZipArchive') ? $this->readEntriesWithZipArchive($zipPath) : $this->readEntriesWithPharData($zipPath));
            $entry = null;
            foreach ($entries as $candidate) {
                if (!$candidate['is_dir'] && $candidate['name'] === $normalizedPath) {
                    $entry = $candidate;
                    break;
                }
            }
            if ($entry === null) {
                return $this->downloadError('not_found', 'El archivo solicitado no existe dentro del proyecto.');
            }

            if (class_exists('ZipArchive')) {
                $archive = new ZipArchive();
                if ($archive->open($zipPath) !== true) {
                    return $this->downloadError('unreadable', 'No fue posible abrir el contenido del proyecto.');
                }
                $stream = $archive->getStream($normalizedPath);
                if ($stream === false) {
                    $archive->close();
                    return $this->downloadError('unreadable', 'No fue posible leer el archivo solicitado.');
                }
            } else {
                $archive = null;
                $stream = fopen('phar://' . str_replace('\\', '/', $zipPath) . '/' . $normalizedPath, 'rb');
                if ($stream === false) {
                    return $this->downloadError('unreadable', 'No fue posible leer el archivo solicitado.');
                }
            }

            return [
                'success' => true,
                'status' => 'ready',
                'message' => '',
                'name' => basename($normalizedPath),
                'path' => $normalizedPath,
                'size' => (int) $entry['size'],
                'mime' => $this->resolveMimeType(strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION))),
                'stream' => $stream,
                'archive' => $archive,
            ];
        } catch (Throwable $exception) {
            error_log('ArchiveService download: ' . $exception->getMessage());
            return $this->downloadError('unreadable', 'No fue posible abrir el contenido del proyecto.');
        }
    }
    // Final de acceso público al archivo ZIP

    // Inicio de validación y lectura del contenedor
    // Normaliza rutas e intercambia entre ZipArchive y PharData sin permitir recorridos inseguros.
    private function normalizeInternalPath(string $path): ?string
    {
        if (str_contains($path, "\0")) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\//', $path) === 1) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return null;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function isEmptyZip(string $zipPath): bool
    {
        $handle = fopen($zipPath, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, 4) === "PK\x05\x06";
        } finally {
            fclose($handle);
        }
    }

    private function readEntriesWithZipArchive(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ZIP ilegible.');
        }

        $entries = [];
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                if ($index >= self::MAX_ENTRIES) {
                    throw new DomainException('El ZIP supera el límite de entradas.');
                }
                $stat = $zip->statIndex($index);
                if ($stat === false) {
                    continue;
                }
                $name = str_replace('\\', '/', (string) $stat['name']);
                if ($this->normalizeArchiveEntry($name) === null) {
                    throw new DomainException('Entrada ZIP con ruta no permitida.');
                }
                $attributes = 0;
                $operations = 0;
                $isLink = $zip->getExternalAttributesIndex($index, $operations, $attributes)
                    && (($attributes >> 16) & 0170000) === 0120000;
                if ($isLink) throw new DomainException('El ZIP contiene enlaces simbólicos.');
                $entries[] = [
                    'name' => rtrim($name, '/'),
                    'is_dir' => str_ends_with($name, '/'),
                    'size' => (int) ($stat['size'] ?? 0),
                    'compressed_size' => (int) ($stat['comp_size'] ?? 0),
                ];
            }
        } finally {
            $zip->close();
        }

        return $entries;
    }

    private function readEntriesWithPharData(string $zipPath): array
    {
        if (!class_exists('PharData')) {
            throw new RuntimeException('No existe un lector ZIP disponible.');
        }

        $archive = new PharData($zipPath);
        $iterator = new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::SELF_FIRST);
        $normalizedZipPath = str_replace('\\', '/', $zipPath);
        $prefix = 'phar://' . $normalizedZipPath . '/';
        $entries = [];

        foreach ($iterator as $key => $fileInfo) {
            if (count($entries) >= self::MAX_ENTRIES) {
                throw new DomainException('El ZIP supera el límite de entradas.');
            }
            $entryPath = str_replace('\\', '/', (string) $key);
            if (str_starts_with($entryPath, $prefix)) {
                $entryPath = substr($entryPath, strlen($prefix));
            } else {
                $markerPosition = strpos($entryPath, '.zip/');
                $entryPath = $markerPosition === false ? $entryPath : substr($entryPath, $markerPosition + 5);
            }

            if ($this->normalizeArchiveEntry($entryPath) === null) {
                throw new DomainException('Entrada ZIP con ruta no permitida.');
            }
            if ($fileInfo->isLink()) throw new DomainException('El ZIP contiene enlaces simbólicos.');
            $entries[] = [
                'name' => rtrim($entryPath, '/'),
                'is_dir' => $fileInfo->isDir(),
                'size' => $fileInfo->isDir() ? 0 : (int) $fileInfo->getSize(),
                'compressed_size' => 0,
            ];
        }

        return $entries;
    }

    private function normalizeArchiveEntry(string $entryPath): ?string
    {
        $trimmed = rtrim($entryPath, '/');
        if ($trimmed === '' || strlen($trimmed) > self::MAX_PATH_LENGTH) return null;
        if (substr_count(str_replace('\\', '/', $trimmed), '/') + 1 > self::MAX_DEPTH) return null;
        return $this->normalizeInternalPath($trimmed);
    }

    private function validateArchiveLimits(array $entries): void
    {
        if (count($entries) > self::MAX_ENTRIES) {
            throw new DomainException('El ZIP supera el límite de entradas.');
        }
        $totalSize = 0;
        foreach ($entries as $entry) {
            $size = (int) ($entry['size'] ?? 0);
            $compressedSize = (int) ($entry['compressed_size'] ?? 0);
            if ($size > self::MAX_ENTRY_SIZE) {
                throw new DomainException('Una entrada supera el tamaño permitido.');
            }
            $totalSize += $size;
            if ($totalSize > self::MAX_UNCOMPRESSED_SIZE) {
                throw new DomainException('El contenido descomprimido supera el límite permitido.');
            }
            if ($size > 1048576 && $compressedSize > 0 && ($size / $compressedSize) > self::MAX_COMPRESSION_RATIO) {
                throw new DomainException('El ZIP supera la proporción de compresión permitida.');
            }
        }
    }
    // Final de validación y lectura del contenedor

    // Inicio de construcción del explorador
    // Organiza entradas, breadcrumbs, metadatos, tipos, iconos y MIME consumidos por la interfaz.
    private function buildDirectory(array $entries, string $currentPath): array
    {
        $prefix = $currentPath === '' ? '' : $currentPath . '/';
        $items = [];
        $exists = $currentPath === '';

        foreach ($entries as $entry) {
            $entryName = $entry['name'];
            if ($currentPath !== '' && ($entryName === $currentPath || str_starts_with($entryName, $prefix))) {
                $exists = true;
            }
            if (!str_starts_with($entryName, $prefix) || $entryName === $currentPath) {
                continue;
            }

            $relativePath = substr($entryName, strlen($prefix));
            if ($relativePath === '') {
                continue;
            }
            $segments = explode('/', $relativePath);
            $itemName = $segments[0];
            $isDirectory = count($segments) > 1 || ($entry['is_dir'] && count($segments) === 1);
            $itemPath = $prefix . $itemName;
            $key = ($isDirectory ? 'folder:' : 'file:') . $itemName;

            if (!isset($items[$key])) {
                $extension = $isDirectory ? '' : strtolower(pathinfo($itemName, PATHINFO_EXTENSION));
                $items[$key] = [
                    'name' => $itemName,
                    'path' => $itemPath,
                    'kind' => $isDirectory ? 'folder' : 'file',
                    'extension' => $extension,
                    'type' => $isDirectory ? 'Carpeta' : $this->describeFileType($extension),
                    'size' => $isDirectory ? '—' : self::formatBytes((int) $entry['size']),
                    'size_bytes' => $isDirectory ? 0 : (int) $entry['size'],
                    'icon' => $this->resolveIcon($isDirectory, $extension),
                ];
            }
        }

        $items = array_values($items);
        usort($items, static function (array $first, array $second): int {
            if ($first['kind'] !== $second['kind']) {
                return $first['kind'] === 'folder' ? -1 : 1;
            }
            return strcasecmp($first['name'], $second['name']);
        });

        return ['exists' => $exists, 'items' => $items];
    }

    private function buildBreadcrumbs(string $currentPath): array
    {
        $breadcrumbs = [['label' => 'Inicio', 'path' => '']];
        if ($currentPath === '') {
            return $breadcrumbs;
        }

        $path = '';
        foreach (explode('/', $currentPath) as $segment) {
            $path = $path === '' ? $segment : $path . '/' . $segment;
            $breadcrumbs[] = ['label' => $segment, 'path' => $path];
        }

        return $breadcrumbs;
    }

    private function buildArchiveMeta(array $entries, int $archiveSize): array
    {
        $files = 0;
        $folders = [];
        foreach ($entries as $entry) {
            if ($entry['is_dir']) {
                $folders[$entry['name']] = true;
                continue;
            }
            $files++;
            $directory = dirname($entry['name']);
            while ($directory !== '.' && $directory !== '') {
                $folders[str_replace('\\', '/', $directory)] = true;
                $directory = dirname($directory);
            }
        }

        return [
            'files_count' => $files,
            'folders_count' => count($folders),
            'size' => self::formatBytes($archiveSize),
            'size_bytes' => $archiveSize,
        ];
    }

    private function describeFileType(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'Documento PDF',
            'doc', 'docx' => 'Documento de Word',
            'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg' => 'Imagen',
            'php', 'js', 'css', 'html', 'htm', 'ts', 'java', 'py', 'cs', 'cpp', 'c', 'sql' => 'Código fuente',
            'md', 'markdown' => 'Markdown',
            'json' => 'JSON',
            'txt', 'log', 'ini' => 'Archivo de texto',
            'zip', 'rar', '7z', 'tar', 'gz' => 'Archivo comprimido',
            default => $extension === '' ? 'Archivo' : strtoupper($extension),
        };
    }

    private function resolveIcon(bool $isDirectory, string $extension): string
    {
        if ($isDirectory) {
            return 'fa-folder';
        }

        return match ($extension) {
            'pdf' => 'fa-file-pdf',
            'doc', 'docx' => 'fa-file-word',
            'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg' => 'fa-file-image',
            'zip', 'rar', '7z', 'tar', 'gz' => 'fa-file-zipper',
            'php', 'js', 'css', 'html', 'htm', 'ts', 'java', 'py', 'cs', 'cpp', 'c', 'sql', 'md', 'json' => 'fa-file-code',
            default => 'fa-file-lines',
        };
    }

    private function resolveMimeType(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'txt', 'log', 'ini', 'md', 'markdown' => 'text/plain; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'xml' => 'application/xml; charset=UTF-8',
            'csv' => 'text/csv; charset=UTF-8',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }
    // Final de construcción del explorador

    // Inicio de respuestas de error
    // Devuelve estructuras uniformes para errores de navegación y descarga del archivo privado.
    private function error(string $status, string $message): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'path' => '',
            'breadcrumbs' => [['label' => 'Inicio', 'path' => '']],
            'items' => [],
            'meta' => ['files_count' => 0, 'folders_count' => 0, 'size' => '—', 'size_bytes' => 0],
        ];
    }

    private function downloadError(string $status, string $message): array
    {
        return ['success' => false, 'status' => $status, 'message' => $message];
    }
    // Final de respuestas de error
}
