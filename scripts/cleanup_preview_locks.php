<?php

declare(strict_types=1);

/** Safely removes only free stale/orphan preview lock markers; PDF files are never touched. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$action = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($action, ['dry-run', 'cleanup', 'verify'], true)) {
    fwrite(STDERR, "Usage: php scripts/cleanup_preview_locks.php [dry-run|cleanup|verify]\n");
    exit(1);
}

$database = Database::connection();
$projects = [];
foreach ($database->query('SELECT id FROM projects')->fetchAll(PDO::FETCH_ASSOC) as $row) $projects[(int) $row['id']] = true;
$files = [];
foreach ($database->query('SELECT id,project_id,storage_name FROM project_files WHERE deleted_at IS NULL AND purged_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $files[(int) $row['id']] = $row;
}
$documentStorage = new ProjectDocumentFileService();
$previewRoot = ROOT_PATH . '/storage/private/project-previews';
$lockRoot = $previewRoot . '/.locks';
$rows = [];

if (is_dir($lockRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($lockRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if (!$entry->isFile()) continue;
        $path = $entry->getPathname();
        $relative = str_replace('\\', '/', substr($path, strlen($lockRoot) + 1));
        $parsed = preg_match('/^([1-9][0-9]*)\/([1-9][0-9]*)_([a-f0-9]{64})\.lock$/i', $relative, $match) === 1;
        $projectId = $parsed ? (int) $match[1] : 0;
        $fileId = $parsed ? (int) $match[2] : 0;
        $identity = $parsed ? strtolower($match[3]) : '';
        $contentRaw = @file_get_contents($path);
        $content = is_string($contentRaw) ? trim($contentRaw) : '';
        $pid = null;
        if (preg_match('/^[1-9][0-9]*$/', $content) === 1) $pid = (int) $content;
        elseif ($content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['pid']) && ctype_digit((string) $decoded['pid'])) $pid = (int) $decoded['pid'];
        }
        $handle = @fopen($path, 'c+');
        $available = null;
        if (is_resource($handle)) {
            $available = @flock($handle, LOCK_EX | LOCK_NB);
            if ($available) @flock($handle, LOCK_UN);
            fclose($handle);
        }
        $projectExists = isset($projects[$projectId]);
        $fileExists = isset($files[$fileId]) && (int) $files[$fileId]['project_id'] === $projectId;
        $sourceExists = false;
        if ($fileExists) {
            try { $sourceExists = $documentStorage->resolveStoredFile($projectId, (string) $files[$fileId]['storage_name']) !== null; }
            catch (Throwable) { $sourceExists = false; }
        }
        $classification = !$parsed ? 'INVALID'
            : ($available === false ? 'ACTIVE'
            : ($available === null ? 'UNKNOWN'
            : ((!$projectExists || !$fileExists || !$sourceExists) ? 'ORPHAN' : 'STALE')));
        $rows[] = [
            'path' => $relative,
            'project_id' => $projectId,
            'file_id' => $fileId,
            'identity' => $identity,
            'modified_at' => date(DATE_ATOM, $entry->getMTime()),
            'size_bytes' => (int) $entry->getSize(),
            'content' => $content === '' ? 'empty' : 'nonempty',
            'pid' => $pid,
            'process_active' => $available === false,
            'project_exists' => $projectExists,
            'file_exists' => $fileExists,
            'source_exists' => $sourceExists,
            'classification' => $classification,
        ];
    }
}
usort($rows, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));

$removed = [];
if ($action === 'cleanup') {
    foreach ($rows as $row) {
        if (!in_array($row['classification'], ['STALE', 'ORPHAN'], true)) continue;
        $path = $lockRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $row['path']);
        if (!is_file($path)) continue;
        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) throw new RuntimeException('No se pudo revalidar lock: ' . $row['path']);
        $available = @flock($handle, LOCK_EX | LOCK_NB);
        if ($available !== true) {
            @fclose($handle);
            throw new RuntimeException('Abortado: el lock se activo durante la limpieza: ' . $row['path']);
        }
        if (!@unlink($path)) {
            @flock($handle, LOCK_UN); @fclose($handle);
            throw new RuntimeException('No se pudo retirar el lock: ' . $row['path']);
        }
        @flock($handle, LOCK_UN); @fclose($handle);
        $removed[] = ['path' => $row['path'], 'classification' => $row['classification']];
        echo 'removed=' . $row['classification'] . ' path=' . $row['path'] . PHP_EOL;
    }
}

$counts = array_count_values(array_column($rows, 'classification'));
$remaining = $action === 'cleanup' ? count($rows) - count($removed) : count($rows);
echo json_encode([
    'mode' => $action,
    'lock_count_before' => count($rows),
    'classification_counts' => $counts,
    'removed_count' => count($removed),
    'remaining_count_estimate' => $remaining,
    'removed' => $removed,
    'pdfs_touched' => 0,
    'database_changes' => 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

if ($action === 'verify' && $rows !== []) exit(2);
if ($action === 'cleanup' && $remaining !== 0) exit(2);
