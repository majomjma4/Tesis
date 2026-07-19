<?php
declare(strict_types=1);

final class PrivateProjectFileService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'zip'];
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'];
    private const MAX_BYTES = 20 * 1024 * 1024;
    private const MAX_TOTAL_BYTES = 50 * 1024 * 1024;
    private string $root;
    public function __construct() { $this->root = ROOT_PATH . '/storage/private/projects'; }

    public function validateUpload(array $file): array
    {
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) throw new InvalidArgumentException($uploadError === UPLOAD_ERR_NO_FILE ? 'No se seleccionó ningún archivo.' : 'El archivo no se recibió correctamente (error ' . $uploadError . ').');
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) throw new InvalidArgumentException('El archivo está vacío o supera el límite individual de 20 MB.');
        $rawName = (string) ($file['name'] ?? ''); $original = basename($rawName);
        if ($rawName === '' || $rawName !== $original || preg_match('/[\x00-\x1F\x7F]/u', $rawName) || !preg_match('/^[\pL\pN _().-]+$/u', $rawName)) throw new InvalidArgumentException('El nombre del archivo contiene caracteres o rutas no seguras.');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) throw new InvalidArgumentException('La extensión del archivo no está permitida.');
        $temporary = (string) ($file['tmp_name'] ?? '');
        $mime = is_file($temporary) ? (new finfo(FILEINFO_MIME_TYPE))->file($temporary) : '';
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) throw new InvalidArgumentException('El contenido del archivo no coincide con un formato permitido.');
        return ['original_name' => $original, 'extension' => $extension, 'mime_type' => $mime, 'size_bytes' => $size];
    }

    public function limits(): array { return ['extensions' => self::ALLOWED_EXTENSIONS, 'mime_types' => self::ALLOWED_MIME_TYPES, 'max_bytes' => self::MAX_BYTES, 'max_total_bytes' => self::MAX_TOTAL_BYTES, 'max_mb' => 20, 'max_total_mb' => 50]; }
    public function projectDirectory(int $projectId): string { if ($projectId < 1) throw new InvalidArgumentException('Identificador inválido.'); return $this->root . DIRECTORY_SEPARATOR . $projectId; }
    public function resolveStoredFile(int $projectId, string $storageName): string
    {
        if (!preg_match('/^[a-f0-9]{32,64}\.(pdf|docx|zip)$/', $storageName)) throw new InvalidArgumentException('Nombre de almacenamiento inválido.');
        $directory = $this->projectDirectory($projectId); $candidate = $directory . DIRECTORY_SEPARATOR . $storageName;
        $resolvedDirectory = realpath($directory); $resolvedFile = realpath($candidate);
        if ($resolvedDirectory === false || $resolvedFile === false || !str_starts_with($resolvedFile, $resolvedDirectory . DIRECTORY_SEPARATOR)) throw new RuntimeException('El archivo privado no existe.');
        return $resolvedFile;
    }
}
