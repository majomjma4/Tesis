<?php

declare(strict_types=1);

final class SupportMaterialFileService
{
    private const MAX_OPERATION_FILES = 5;
    private const MAX_FILE_BYTES = 26214400;
    private const MAX_OPERATION_BYTES = 36700160;
    private const MAX_NAME_LENGTH = 200;
    private const MIME_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'txt' => ['text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    public function store(int $materialId, array $upload): array
    {
        if ($materialId < 1) throw new InvalidArgumentException('El material no es válido.');
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                $error === UPLOAD_ERR_NO_FILE
                    ? 'Selecciona un archivo.'
                    : 'El archivo no se recibió correctamente.'
            );
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('El archivo está vacío o supera el límite de 25 MB.');
        }
        $rawName = (string) ($upload['name'] ?? '');
        $originalName = basename(str_replace('\\', '/', $rawName));
        if ($originalName === '' || $rawName !== $originalName
            || mb_strlen($originalName, 'UTF-8') > self::MAX_NAME_LENGTH
            || preg_match('/[\x00-\x1F\x7F]/u', $originalName)) {
            throw new InvalidArgumentException('El nombre del archivo no es válido.');
        }
        $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::MIME_BY_EXTENSION[$extension])) {
            throw new InvalidArgumentException('El formato del archivo no está permitido.');
        }
        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $mime = is_file($temporaryPath) ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath) : '';
        if (!in_array($mime, self::MIME_BY_EXTENSION[$extension], true)) {
            throw new InvalidArgumentException('El contenido no coincide con la extensión del archivo.');
        }

        $directory = ROOT_PATH . '/storage/support-materials/' . $materialId;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible preparar el almacenamiento del material.');
        }
        $storageName = bin2hex(random_bytes(20)) . '.' . $extension;
        $destination = $directory . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('No fue posible guardar el archivo recibido.');
        }
        $sha256 = hash_file('sha256', $destination);
        if (!is_string($sha256) || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            @unlink($destination);
            throw new RuntimeException('No fue posible calcular la integridad del archivo recibido.');
        }

        return [
            'original_name' => $originalName,
            'storage_name' => $storageName,
            'relative_path' => $materialId . '/' . $storageName,
            'extension' => $extension,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => $sha256,
            'absolute_path' => $destination,
        ];
    }

    public function limits(): array
    {
        return [
            'max_operation_files' => self::MAX_OPERATION_FILES,
            'max_file_bytes' => self::MAX_FILE_BYTES,
            'max_operation_bytes' => self::MAX_OPERATION_BYTES,
            'max_file_mb' => 25,
            'max_name_length' => self::MAX_NAME_LENGTH,
            'extensions' => array_keys(self::MIME_BY_EXTENSION),
        ];
    }

    public function resolveRelativePath(string $relativePath): ?string
    {
        if ($relativePath === '' || str_contains($relativePath, "\0")) return null;
        $base = realpath(ROOT_PATH . '/storage/support-materials');
        if ($base === false) return null;
        $candidate = ROOT_PATH . '/storage/support-materials/' . str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $relativePath
        );
        $path = realpath($candidate);
        if ($path === false
            || !str_starts_with(strtolower($path), strtolower($base . DIRECTORY_SEPARATOR))
            || !is_file($path)
            || !is_readable($path)) {
            return null;
        }
        return $path;
    }

    public function isAvailable(string $relativePath): bool
    {
        return $this->resolveRelativePath($relativePath) !== null;
    }

    public function discard(array $stored): bool
    {
        $path = $this->resolveRelativePath((string) ($stored['relative_path'] ?? ''));
        if ($path === null) return false;
        return @unlink($path);
    }
}
