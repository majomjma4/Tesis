<?php

declare(strict_types=1);

final class SupportMaterialFileService
{
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
        if ($size < 1 || $size > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('El archivo está vacío o supera el límite de 25 MB.');
        }
        $originalName = basename((string) ($upload['name'] ?? ''));
        if ($originalName === '' || preg_match('/[\x00-\x1F\x7F]/u', $originalName)) {
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

        return [
            'original_name' => $originalName,
            'storage_name' => $storageName,
            'relative_path' => $materialId . '/' . $storageName,
            'extension' => $extension,
            'mime_type' => $mime,
            'size_bytes' => $size,
        ];
    }
}
