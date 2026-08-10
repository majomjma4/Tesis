<?php
declare(strict_types=1);

final class ProfileAvatarStorageService
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_DIMENSION = 2048;
    private const FORMATS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function limits(): array
    {
        return ['max_bytes' => self::MAX_BYTES, 'max_mb' => 5, 'max_dimension' => self::MAX_DIMENSION];
    }

    public function store(array $upload): array
    {
        $this->validateUpload($upload);
        $temporary = (string) $upload['tmp_name'];
        $extension = mb_strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $image = @getimagesize($temporary);

        if (!is_array($image) || !in_array((string) ($image['mime'] ?? ''), self::FORMATS, true)) {
            throw new InvalidArgumentException('El archivo no contiene una imagen válida.');
        }
        if ((string) $image['mime'] !== $mime || self::FORMATS[$extension] !== $mime) {
            throw new InvalidArgumentException('El contenido de la imagen no coincide con su formato.');
        }
        if ((int) $image[0] < 1 || (int) $image[1] < 1 || (int) $image[0] > self::MAX_DIMENSION || (int) $image[1] > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('La imagen supera las dimensiones máximas permitidas.');
        }

        $directory = ROOT_PATH . '/storage/private/avatars';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible preparar el almacenamiento de la fotografía.');
        }
        $name = bin2hex(random_bytes(32)) . '.' . $extension;
        $absolute = $directory . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($temporary, $absolute)) {
            throw new RuntimeException('No fue posible guardar la fotografía.');
        }
        @chmod($absolute, 0640);

        return ['path' => 'avatars/' . $name, 'absolute_path' => $absolute, 'mime' => $mime];
    }

    public function resolve(string $relativePath): array
    {
        if (!preg_match('#^avatars/([a-f0-9]{64})\.(jpg|jpeg|png)$#', $relativePath, $matches)) {
            throw new InvalidArgumentException('La fotografía solicitada no es válida.');
        }
        $base = realpath(ROOT_PATH . '/storage/private/avatars');
        $path = realpath(ROOT_PATH . '/storage/private/' . $relativePath);
        if ($base === false || $path === false || !is_file($path) || !str_starts_with(strtolower($path), strtolower($base . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('La fotografía no está disponible.');
        }
        return ['path' => $path, 'mime' => self::FORMATS[$matches[2]]];
    }

    public function delete(string $relativePath): bool
    {
        try {
            $file = $this->resolve($relativePath);
        } catch (Throwable) {
            return false;
        }
        return @unlink($file['path']);
    }

    public function discard(array $stored): void
    {
        if (!empty($stored['absolute_path']) && is_file((string) $stored['absolute_path'])) @unlink((string) $stored['absolute_path']);
    }

    private function validateUpload(array $upload): void
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($error === UPLOAD_ERR_NO_FILE ? 'Selecciona una fotografía.' : 'La fotografía no se recibió correctamente.');
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) throw new InvalidArgumentException('La fotografía está vacía o supera el límite de 5 MB.');
        $name = (string) ($upload['name'] ?? '');
        $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::FORMATS[$extension])) throw new InvalidArgumentException('Solo se permiten imágenes JPG, JPEG o PNG.');
        $temporary = (string) ($upload['tmp_name'] ?? '');
        if (!is_uploaded_file($temporary) || !is_file($temporary)) throw new InvalidArgumentException('La fotografía no se recibió correctamente.');
    }
}
