<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$database = Database::connection();
$projects = [];
foreach ($database->query('SELECT id,deleted_at FROM projects')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $projects[(int) $row['id']] = $row;
}

$files = [];
foreach ($database->query('SELECT id,project_id,storage_name,checksum_sha256,extension FROM project_files WHERE deleted_at IS NULL AND purged_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $files[(int) $row['id']] = $row;
}

$previewRoot = ROOT_PATH . '/storage/private/project-previews';
$lockRoot = $previewRoot . '/.locks';
$documentStorage = new ProjectDocumentFileService();
$locks = [];

if (is_dir($lockRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($lockRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $entry) {
        if (!$entry->isFile()) continue;

        $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($lockRoot) + 1));
        $segments = explode('/', $relative);
        $projectId = (int) ($segments[0] ?? 0);
        $fileName = (string) end($segments);
        $parsed = preg_match('/^([0-9]+)_([a-f0-9]+)\.lock$/i', $fileName, $match) === 1;
        $fileId = $parsed ? (int) $match[1] : 0;
        $identity = $parsed ? strtolower($match[2]) : '';

        $handle = @fopen($entry->getPathname(), 'c+');
        $lockAvailable = null;
        if (is_resource($handle)) {
            $lockAvailable = @flock($handle, LOCK_EX | LOCK_NB);
            if ($lockAvailable) @flock($handle, LOCK_UN);
            fclose($handle);
        }

        $projectExists = isset($projects[$projectId]);
        $fileExists = isset($files[$fileId]) && (int) $files[$fileId]['project_id'] === $projectId;
        $sourceExists = false;
        if ($fileExists) {
            try {
                $sourceExists = $documentStorage->resolveStoredFile(
                    $projectId,
                    (string) $files[$fileId]['storage_name']
                ) !== null;
            } catch (Throwable) {
                $sourceExists = false;
            }
        }

        $previewPath = $previewRoot . '/' . $projectId . '/' . $fileId . '_' . $identity . '.pdf';
        $previewExists = is_file($previewPath);
        $previewValid = false;
        if ($previewExists) {
            $previewHandle = @fopen($previewPath, 'rb');
            $previewValid = is_resource($previewHandle) && fread($previewHandle, 5) === '%PDF-';
            if (is_resource($previewHandle)) fclose($previewHandle);
        }

        if (!$parsed) {
            $classification = 'INVALID';
        } elseif ($lockAvailable === false) {
            $classification = 'ACTIVE';
        } elseif ($lockAvailable === null) {
            $classification = 'UNKNOWN';
        } elseif (!$projectExists || !$fileExists || !$sourceExists) {
            $classification = 'ORPHAN';
        } else {
            $classification = 'STALE';
        }

        $locks[] = [
            'path' => $relative,
            'project_id' => $projectId,
            'file_id' => $fileId,
            'identity_length' => strlen($identity),
            'modified_at' => date(DATE_ATOM, $entry->getMTime()),
            'size_bytes' => $entry->getSize(),
            'content' => trim((string) @file_get_contents($entry->getPathname())) === '' ? 'empty' : 'nonempty',
            'pid' => null,
            'process_active' => $lockAvailable === false,
            'project_exists' => $projectExists,
            'file_exists' => $fileExists,
            'source_exists' => $sourceExists,
            'preview_exists' => $previewExists,
            'preview_valid' => $previewValid,
            'classification' => $classification,
        ];
    }
}

$previews = [];
if (is_dir($previewRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($previewRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $entry) {
        $normalizedPath = str_replace('\\', '/', $entry->getPathname());
        if (!$entry->isFile() || strtolower($entry->getExtension()) !== 'pdf' || str_contains($normalizedPath, '/.locks/')) continue;

        $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($previewRoot) + 1));
        $segments = explode('/', $relative);
        $projectId = (int) ($segments[0] ?? 0);
        $fileName = (string) ($segments[1] ?? '');
        $parsed = preg_match('/^([0-9]+)_([a-f0-9]{64})\.pdf$/i', $fileName, $match) === 1;
        $fileId = $parsed ? (int) $match[1] : 0;
        $projectExists = isset($projects[$projectId]);
        $fileExists = isset($files[$fileId]) && (int) $files[$fileId]['project_id'] === $projectId;
        $sourceExists = false;
        if ($fileExists) {
            try {
                $sourceExists = $documentStorage->resolveStoredFile(
                    $projectId,
                    (string) $files[$fileId]['storage_name']
                ) !== null;
            } catch (Throwable) {
                $sourceExists = false;
            }
        }

        $handle = @fopen($entry->getPathname(), 'rb');
        $validPdf = is_resource($handle) && fread($handle, 5) === '%PDF-';
        if (is_resource($handle)) fclose($handle);
        $previews[] = [
            'path' => $relative,
            'project_exists' => $projectExists,
            'file_exists' => $fileExists,
            'source_exists' => $sourceExists,
            'valid_pdf' => $validPdf,
            'size_bytes' => $entry->getSize(),
        ];
    }
}

echo json_encode([
    'lock_count' => count($locks),
    'lock_classifications' => array_count_values(array_column($locks, 'classification')),
    'locks' => $locks,
    'preview_count' => count($previews),
    'preview_summary' => [
        'valid_pdf' => count(array_filter($previews, static fn(array $item): bool => $item['valid_pdf'])),
        'with_source' => count(array_filter($previews, static fn(array $item): bool => $item['source_exists'])),
        'without_source' => count(array_filter($previews, static fn(array $item): bool => !$item['source_exists'])),
        'without_file_row' => count(array_filter($previews, static fn(array $item): bool => !$item['file_exists'])),
    ],
    'orphan_previews' => array_values(array_filter($previews, static fn(array $item): bool => !$item['source_exists'])),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
