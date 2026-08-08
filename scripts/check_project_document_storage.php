<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$db = Database::connection();
$storage = new ProjectDocumentFileService();
$archive = new ArchiveService();
$rows = $db->query('SELECT id,project_id,storage_name,size_bytes,checksum_sha256,extension FROM project_files WHERE deleted_at IS NULL AND purged_at IS NULL ORDER BY id')->fetchAll();
$missing = [];
$unreadable = [];
$sizeMismatches = [];
$hashMismatches = [];
$zipChecks = [];
foreach ($rows as $row) {
    $id = (int) $row['id'];
    try {
        $path = $storage->resolveStoredFile((int) $row['project_id'], (string) $row['storage_name']);
    } catch (Throwable) {
        $missing[] = $id;
        continue;
    }
    if (!is_readable($path)) { $unreadable[] = $id; continue; }
    $size = filesize($path);
    if ($size === false || (int) $size !== (int) $row['size_bytes']) $sizeMismatches[] = $id;
    $hash = trim((string) ($row['checksum_sha256'] ?? ''));
    if ($hash !== '' && (!is_string($actual = hash_file('sha256', $path)) || !hash_equals(strtolower($hash), strtolower($actual)))) $hashMismatches[] = $id;
    if (strtolower((string) $row['extension']) === 'zip') {
        $inspection = $archive->inspectPackage($path);
        $zipChecks[] = ['file_id' => $id, 'success' => (bool) $inspection['success'], 'status' => (string) $inspection['status'], 'entries' => count((array) ($inspection['entries'] ?? []))];
    }
}
$root = ROOT_PATH . '/storage/private/projects';
$result = [
    'php_version' => PHP_VERSION,
    'zip_archive_available' => class_exists('ZipArchive'),
    'phar_data_available' => class_exists('PharData'),
    'fileinfo_available' => extension_loaded('fileinfo'),
    'storage_root_exists' => is_dir($root),
    'storage_root_readable' => is_readable($root),
    'registered_files_checked' => count($rows),
    'missing_file_ids' => $missing,
    'unreadable_file_ids' => $unreadable,
    'size_mismatch_file_ids' => $sizeMismatches,
    'hash_mismatch_file_ids' => $hashMismatches,
    'zip_checks' => $zipChecks,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit(($missing === [] && $unreadable === [] && $sizeMismatches === [] && $hashMismatches === []) ? 0 : 2);
