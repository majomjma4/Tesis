<?php

declare(strict_types=1);

final class FilePreviewService
{
    private const TEXT_PREVIEW_LIMIT = 524288;
    private const PDF_PREVIEW_LIMIT = 20971520;
    private const IMAGE_PREVIEW_LIMIT = 10485760;

    private const CODE_EXTENSIONS = [
        'php', 'html', 'htm', 'css', 'js', 'mjs', 'ts', 'tsx', 'jsx', 'java', 'py', 'cs',
        'cpp', 'c', 'h', 'sql', 'dart', 'kt', 'kts', 'sh', 'bat', 'ps1', 'yaml', 'yml',
    ];

    private const TEXT_EXTENSIONS = ['txt', 'md', 'markdown', 'json', 'xml', 'csv', 'log', 'ini'];
    private const IMAGE_MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    public function prepare(array $file, string $contentUrl, string $downloadUrl): array
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $detectedMime = $this->detectMime($file['stream']);
        $base = [
            'name' => $file['name'],
            'path' => $file['path'],
            'size' => ArchiveService::formatBytes((int) $file['size']),
            'size_bytes' => (int) $file['size'],
            'extension' => $extension,
            'mime' => $detectedMime,
            'download_url' => $downloadUrl,
            'content_url' => '',
            'preview_type' => 'unsupported',
            'language' => '',
            'content' => '',
            'blocks' => [],
            'truncated' => false,
        ];

        if ((int) $file['size'] === 0) {
            return array_merge($base, [
                'status' => 'empty',
                'message' => 'Este archivo no contiene información para mostrar.',
                'type_label' => $this->describeType($extension),
            ]);
        }

        if ($extension === 'pdf') {
            if ($detectedMime !== 'application/pdf') {
                return $this->unsupported($base, $extension, 'La extensión y el tipo del archivo no coinciden.');
            }
            if ((int) $file['size'] > self::PDF_PREVIEW_LIMIT) {
                return $this->tooLarge($base, $extension);
            }
            return array_merge($base, [
                'status' => 'ready',
                'message' => '',
                'preview_type' => 'pdf',
                'type_label' => 'Documento PDF',
                'content_url' => $contentUrl,
            ]);
        }

        if (array_key_exists($extension, self::IMAGE_MIME_BY_EXTENSION)) {
            if ($detectedMime !== self::IMAGE_MIME_BY_EXTENSION[$extension]) {
                return $this->unsupported($base, $extension, 'La extensión y el tipo del archivo no coinciden.');
            }
            if ((int) $file['size'] > self::IMAGE_PREVIEW_LIMIT) {
                return $this->tooLarge($base, $extension);
            }
            return array_merge($base, [
                'status' => 'ready',
                'message' => '',
                'preview_type' => 'image',
                'type_label' => 'Imagen ' . strtoupper($extension),
                'content_url' => $contentUrl,
            ]);
        }

        if ($extension === 'svg') {
            return $this->unsupported($base, $extension, 'La vista previa SVG está deshabilitada por seguridad.');
        }

        if ($extension === 'docx') {
            if (!in_array($detectedMime, [
                'application/zip',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/octet-stream',
            ], true)) {
                return $this->unsupported($base, $extension, 'La extensión y el tipo del archivo no coinciden.');
            }

            $docx = (new DocxPreviewService())->extract($file);
            return array_merge($base, [
                'status' => $docx['status'],
                'message' => $docx['message'],
                'preview_type' => $docx['status'] === 'ready' ? 'docx' : 'unsupported',
                'type_label' => 'Documento de Word',
                'blocks' => $docx['blocks'],
                'truncated' => $docx['truncated'],
            ]);
        }

        if (in_array($extension, self::CODE_EXTENSIONS, true) || in_array($extension, self::TEXT_EXTENSIONS, true)) {
            if (!$this->isTextMime($detectedMime)) {
                return $this->unsupported($base, $extension, 'La extensión y el tipo del archivo no coinciden.');
            }
            if ((int) $file['size'] > self::TEXT_PREVIEW_LIMIT) {
                return $this->tooLarge($base, $extension);
            }

            rewind($file['stream']);
            $content = stream_get_contents($file['stream'], self::TEXT_PREVIEW_LIMIT + 1);
            if ($content === false || preg_match('//u', $content) !== 1) {
                return $this->unsupported($base, $extension, 'No fue posible interpretar el archivo como texto seguro.');
            }

            $truncated = strlen($content) > self::TEXT_PREVIEW_LIMIT;
            if ($truncated) {
                $content = substr($content, 0, self::TEXT_PREVIEW_LIMIT);
            }
            if ($extension === 'json') {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            $isCode = in_array($extension, self::CODE_EXTENSIONS, true) || in_array($extension, ['json', 'xml'], true);
            return array_merge($base, [
                'status' => 'ready',
                'message' => $truncated ? 'Vista previa limitada por el tamaño del archivo.' : '',
                'preview_type' => $isCode ? 'code' : 'text',
                'type_label' => $this->describeType($extension),
                'language' => $this->resolveLanguage($extension),
                'content' => $content,
                'truncated' => $truncated,
            ]);
        }

        return $this->unsupported($base, $extension, 'Este formato no puede visualizarse dentro de la plataforma.');
    }

    public function canStreamInline(array $file): bool
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = $this->detectMime($file['stream']);
        if ($extension === 'pdf') {
            return $mime === 'application/pdf' && (int) $file['size'] <= self::PDF_PREVIEW_LIMIT;
        }
        return isset(self::IMAGE_MIME_BY_EXTENSION[$extension])
            && $mime === self::IMAGE_MIME_BY_EXTENSION[$extension]
            && (int) $file['size'] <= self::IMAGE_PREVIEW_LIMIT;
    }

    private function detectMime($stream): string
    {
        rewind($stream);
        $sample = stream_get_contents($stream, 8192);
        rewind($stream);
        if ($sample === false || $sample === '') {
            return 'application/x-empty';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return (string) ($finfo->buffer($sample) ?: 'application/octet-stream');
    }

    private function isTextMime(string $mime): bool
    {
        return str_starts_with($mime, 'text/') || in_array($mime, [
            'application/json', 'application/xml', 'application/javascript', 'application/x-httpd-php',
            'application/x-empty', 'application/octet-stream',
        ], true);
    }

    private function unsupported(array $base, string $extension, string $message): array
    {
        return array_merge($base, [
            'status' => 'unsupported',
            'message' => $message,
            'type_label' => $this->describeType($extension),
        ]);
    }

    private function tooLarge(array $base, string $extension): array
    {
        return array_merge($base, [
            'status' => 'too_large',
            'message' => 'Este archivo es demasiado grande para visualizarse dentro de la plataforma.',
            'type_label' => $this->describeType($extension),
        ]);
    }

    private function describeType(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'Documento PDF',
            'docx' => 'Documento de Word',
            'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg' => 'Imagen',
            'md', 'markdown' => 'Markdown',
            'json' => 'JSON',
            'xml' => 'XML',
            'csv' => 'CSV',
            'txt', 'log', 'ini' => 'Archivo de texto',
            default => in_array($extension, self::CODE_EXTENSIONS, true) ? 'Código fuente' : ($extension === '' ? 'Archivo' : strtoupper($extension)),
        };
    }

    private function resolveLanguage(string $extension): string
    {
        return match ($extension) {
            'js', 'mjs', 'jsx' => 'JavaScript',
            'ts', 'tsx' => 'TypeScript',
            'py' => 'Python',
            'cs' => 'C#',
            'cpp', 'c', 'h' => 'C/C++',
            'md', 'markdown' => 'Markdown',
            'yml', 'yaml' => 'YAML',
            default => $extension === '' ? 'Texto' : strtoupper($extension),
        };
    }
}
