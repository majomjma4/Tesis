<?php

declare(strict_types=1);

final class DocxPreviewService
{
    private const FILE_LIMIT = 15728640;
    private const XML_LIMIT = 2097152;
    private const TEXT_LIMIT = 524288;
    private const BLOCK_LIMIT = 1000;
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    // Inicio de extracción segura de DOCX
    // Valida el paquete, limita su contenido y devuelve bloques estructurados sin ejecutar elementos activos.
    public function extract(array $file): array
    {
        if ((int) $file['size'] > self::FILE_LIMIT) {
            return $this->error('too_large', 'Este documento es demasiado grande para visualizarse dentro de la plataforma.');
        }
        if (!class_exists('DOMDocument') || (!class_exists('ZipArchive') && !class_exists('PharData'))) {
            return $this->error('unsupported', 'El servidor no dispone de los componentes necesarios para visualizar DOCX.');
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'repository_docx_');
        if ($temporaryBase === false) {
            return $this->error('unreadable', 'No fue posible preparar la vista previa del documento.');
        }
        $temporaryPath = $temporaryBase . '.zip';

        try {
            if (!rename($temporaryBase, $temporaryPath)) {
                throw new RuntimeException('No fue posible crear el archivo temporal.');
            }
            rewind($file['stream']);
            $target = fopen($temporaryPath, 'wb');
            if ($target === false) {
                throw new RuntimeException('No fue posible abrir el archivo temporal.');
            }
            try {
                if (stream_copy_to_stream($file['stream'], $target, self::FILE_LIMIT + 1) === false) {
                    throw new RuntimeException('No fue posible copiar el documento.');
                }
            } finally {
                fclose($target);
            }

            $contentTypes = $this->readEntry($temporaryPath, '[Content_Types].xml', 131072);
            if ($contentTypes === null || !str_contains($contentTypes, 'wordprocessingml.document.main+xml')) {
                return $this->error('invalid', 'El archivo no contiene una estructura DOCX compatible.');
            }
            if (stripos($contentTypes, 'macroEnabled') !== false || stripos($contentTypes, 'vbaProject') !== false) {
                return $this->error('unsupported', 'Los documentos con macros no pueden visualizarse dentro de la plataforma.');
            }

            $documentXml = $this->readEntry($temporaryPath, 'word/document.xml', self::XML_LIMIT);
            if ($documentXml === null) {
                return $this->error('invalid', 'El documento está dañado o no contiene texto legible.');
            }

            return $this->parseDocument($documentXml);
        } catch (Throwable $exception) {
            error_log('DocxPreviewService: ' . $exception->getMessage());
            return $this->error('invalid', 'El documento DOCX está dañado o no es compatible.');
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
            if (is_file($temporaryBase)) {
                unlink($temporaryBase);
            }
        }
    }
    // Final de extracción segura de DOCX

    // Inicio de lectura y transformación del documento
    // Recupera document.xml y convierte párrafos, listas y tablas en datos simples para la vista.
    private function readEntry(string $archivePath, string $entryPath, int $limit): ?string
    {
        if (class_exists('ZipArchive')) {
            $archive = new ZipArchive();
            if ($archive->open($archivePath) !== true) {
                return null;
            }
            try {
                $stat = $archive->statName($entryPath);
                if ($stat === false || (int) ($stat['size'] ?? 0) > $limit) {
                    return null;
                }
                $content = $archive->getFromName($entryPath, $limit + 1);
                return $content === false || strlen($content) > $limit ? null : $content;
            } finally {
                $archive->close();
            }
        }

        new PharData($archivePath);
        $stream = @fopen('phar://' . str_replace('\\', '/', $archivePath) . '/' . $entryPath, 'rb');
        if ($stream === false) {
            return null;
        }
        try {
            $content = stream_get_contents($stream, $limit + 1);
            return $content === false || strlen($content) > $limit ? null : $content;
        } finally {
            fclose($stream);
        }
    }

    private function parseDocument(string $xml): array
    {
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                return $this->error('invalid', 'El contenido principal del documento no es válido.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NAMESPACE);
        $nodes = $xpath->query('//w:body/*[self::w:p or self::w:tbl]');
        $blocks = [];
        $textLength = 0;
        $truncated = false;

        foreach ($nodes ?: [] as $node) {
            if (count($blocks) >= self::BLOCK_LIMIT || $textLength >= self::TEXT_LIMIT) {
                $truncated = true;
                break;
            }
            if ($node->localName === 'tbl') {
                $rows = [];
                foreach ($xpath->query('./w:tr', $node) ?: [] as $rowNode) {
                    $cells = [];
                    foreach ($xpath->query('./w:tc', $rowNode) ?: [] as $cellNode) {
                        $cells[] = $this->nodeText($xpath, $cellNode);
                    }
                    if ($cells !== []) {
                        $rows[] = $cells;
                        $textLength += array_sum(array_map('strlen', $cells));
                    }
                }
                if ($rows !== []) {
                    $blocks[] = ['type' => 'table', 'rows' => $rows];
                }
                continue;
            }

            $text = $this->nodeText($xpath, $node);
            if ($text === '') {
                continue;
            }
            $styleNode = $xpath->query('./w:pPr/w:pStyle', $node)?->item(0);
            $style = $styleNode instanceof DOMElement ? $styleNode->getAttributeNS(self::WORD_NAMESPACE, 'val') : '';
            $type = 'paragraph';
            $level = 0;
            if (preg_match('/(?:Heading|Título|Titulo)\s*([1-6])/iu', $style, $matches) === 1) {
                $type = 'heading';
                $level = (int) $matches[1];
            } elseif (($xpath->query('./w:pPr/w:numPr', $node)?->length ?? 0) > 0) {
                $type = 'list';
            }
            $blocks[] = ['type' => $type, 'text' => $text, 'level' => $level];
            $textLength += strlen($text);
        }

        if ($blocks === []) {
            return $this->error('empty', 'El documento no contiene texto que pueda mostrarse.');
        }

        return [
            'status' => 'ready',
            'message' => $truncated ? 'La vista previa fue limitada por la extensión del documento.' : '',
            'blocks' => $blocks,
            'truncated' => $truncated,
        ];
    }

    private function nodeText(DOMXPath $xpath, DOMNode $node): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $node) ?: [] as $textNode) {
            $parts[] = $textNode->localName === 't' ? $textNode->textContent : ($textNode->localName === 'tab' ? "\t" : "\n");
        }
        return trim(implode('', $parts));
    }
    // Final de lectura y transformación del documento

    // Inicio de respuestas de error DOCX
    // Mantiene un formato uniforme ante paquetes dañados, vacíos o no compatibles.
    private function error(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message, 'blocks' => [], 'truncated' => false];
    }
    // Final de respuestas de error DOCX
}
