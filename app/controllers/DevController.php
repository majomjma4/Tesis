<?php

declare(strict_types=1);

final class DevController
{
    public function reloadStamp(): void
    {
        $config = $GLOBALS['config'] ?? [];
        if (empty($config['dev_autoreload']) || ($config['environment'] ?? 'production') === 'production') {
            http_response_code(404);
            exit;
        }

        // Endpoint usado solo en desarrollo para detectar cambios y recargar la pagina.
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo json_encode([
            'stamp' => $this->getLastModifiedTime(),
        ]);
    }

    private function getLastModifiedTime(): int
    {
        // Directorios observados por el auto refresh de desarrollo.
        $paths = [
            APP_PATH,
            ROOT_PATH . '/public/assets/css',
            ROOT_PATH . '/public/assets/js',
            ROOT_PATH . '/index.php',
            ROOT_PATH . '/.gitattributes',
        ];

        $lastModified = 0;

        foreach ($paths as $path) {
            // Tambien permite observar archivos sueltos como index.php.
            if (is_file($path)) {
                $lastModified = max($lastModified, (int) filemtime($path));
                continue;
            }

            if (!is_dir($path)) {
                continue;
            }

            // Recorre carpetas de forma recursiva para detectar cambios en vistas/assets.
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $lastModified = max($lastModified, (int) $file->getMTime());
                }
            }
        }

        return $lastModified;
    }
}
