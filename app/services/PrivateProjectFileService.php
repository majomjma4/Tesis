<?php

declare(strict_types=1);

/** Define validación y resolución segura para futuros archivos privados de proyectos. */
final class PrivateProjectFileService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'zip'];
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip', 'application/x-zip-compressed',
    ];
    private const MAX_BYTES = 20 * 1024 * 1024;

    private string $root;

    public function __construct()
    {
        $this->root = ROOT_PATH . '/storage/private/projects';
    }

    public function validateUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('El archivo no se recibió correctamente.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('El archivo supera el límite permitido de 20 MB.');
        }
        $original = basename((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('La extensión del archivo no está permitida.');
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        $mime = is_file($temporary) ? (new finfo(FILEINFO_MIME_TYPE))->file($temporary) : '';
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('El contenido del archivo no coincide con un formato permitido.');
        }
        return ['original_name' => $original, 'extension' => $extension, 'mime_type' => $mime, 'size_bytes' => $size];
    }

    public function projectDirectory(int $projectId): string
    {
        if ($projectId < 1) throw new InvalidArgumentException('Identificador de proyecto inválido.');
        return $this->root . DIRECTORY_SEPARATOR . $projectId;
    }

    public function resolveStoredFile(int $projectId, string $storageName): string
    {
        if (!preg_match('/^[a-f0-9]{32,64}\.(pdf|docx|zip)$/', $storageName)) {
            throw new InvalidArgumentException('Nombre de almacenamiento inválido.');
        }
        $directory = $this->projectDirectory($projectId);
        $candidate = $directory . DIRECTORY_SEPARATOR . $storageName;
        $resolvedDirectory = realpath($directory);
        $resolvedFile = realpath($candidate);
        if ($resolvedDirectory === false || $resolvedFile === false || !str_starts_with($resolvedFile, $resolvedDirectory . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('El archivo privado no existe.');
        }
        return $resolvedFile;
    }
}
