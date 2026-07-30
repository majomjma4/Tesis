<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$config = require APP_PATH . '/config/app.php';
$timezone = (string) ($config['timezone'] ?? 'America/Guayaquil');
if (in_array($timezone, timezone_identifiers_list(), true)) date_default_timezone_set($timezone);

$fileService = new SupportMaterialFileService();
$database = Database::connection();
$records = $database->query(
    "SELECT 'current' source,id,id file_id,material_id,original_name,relative_path,
            size_bytes,sha256,NULL version_number
     FROM support_material_files
     WHERE purged_at IS NULL
     UNION ALL
     SELECT 'version' source,id,file_id,material_id,original_name,relative_path,
            size_bytes,sha256,version_number
     FROM support_material_file_versions
     ORDER BY source,material_id,id"
)->fetchAll();

$registeredPaths = [];
$missingRecords = [];
$sizeMismatches = [];
$hashMismatches = [];
$invalidHashes = [];
foreach ($records as $record) {
    $relativePath = str_replace('\\', '/', (string) $record['relative_path']);
    $registeredPaths[$relativePath] = true;
    $path = $fileService->resolveRelativePath($relativePath);
    if ($path === null) {
        $missingRecords[] = [
            'source' => (string) $record['source'],
            'id' => (int) $record['id'],
            'material_id' => (int) $record['material_id'],
            'original_name' => (string) $record['original_name'],
            'relative_path' => $relativePath,
        ];
        continue;
    }
    $actualSize = filesize($path);
    if ($actualSize === false || (int) $actualSize !== (int) $record['size_bytes']) {
        $sizeMismatches[] = [
            'source' => (string) $record['source'],
            'id' => (int) $record['id'],
            'expected' => (int) $record['size_bytes'],
            'actual' => $actualSize === false ? null : (int) $actualSize,
        ];
    }
    $expectedHash = strtolower(trim((string) ($record['sha256'] ?? '')));
    if ($expectedHash === '') continue;
    if (preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1) {
        $invalidHashes[] = [
            'source' => (string) $record['source'],
            'id' => (int) $record['id'],
            'sha256' => $expectedHash,
        ];
        continue;
    }
    $actualHash = hash_file('sha256', $path);
    if (!is_string($actualHash) || !hash_equals($expectedHash, strtolower($actualHash))) {
        $hashMismatches[] = [
            'source' => (string) $record['source'],
            'id' => (int) $record['id'],
            'expected' => $expectedHash,
            'actual' => is_string($actualHash) ? strtolower($actualHash) : null,
        ];
    }
}

$duplicateVersionNumbers = $database->query(
    'SELECT file_id,version_number,COUNT(*) occurrences
     FROM support_material_file_versions
     GROUP BY file_id,version_number
     HAVING COUNT(*)>1
     ORDER BY file_id,version_number'
)->fetchAll();
$versionRows = $database->query(
    'SELECT file_id,version_number
     FROM support_material_file_versions
     ORDER BY file_id,version_number'
)->fetchAll();
$numbersByFile = [];
foreach ($versionRows as $row) {
    $numbersByFile[(int) $row['file_id']][] = (int) $row['version_number'];
}
$versionGaps = [];
foreach ($numbersByFile as $fileId => $numbers) {
    $maximum = max($numbers);
    $missingNumbers = array_values(array_diff(range(1, $maximum), array_unique($numbers)));
    if ($missingNumbers !== []) {
        $versionGaps[] = ['file_id' => $fileId, 'missing_version_numbers' => $missingNumbers];
    }
}

$storageRoot = ROOT_PATH . '/storage/support-materials';
$unregisteredFiles = [];
if (is_dir($storageRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $entry) {
        if (!$entry->isFile()) continue;
        $absolutePath = $entry->getPathname();
        $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($storageRoot) + 1));
        if (in_array($relativePath, ['.gitkeep', 'README.md'], true)
            || isset($registeredPaths[$relativePath])) {
            continue;
        }
        $unregisteredFiles[] = [
            'relative_path' => $relativePath,
            'size_bytes' => $entry->getSize(),
        ];
    }
}

$result = [
    'checked_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'storage_directory_exists' => is_dir($storageRoot),
    'registered_records_checked' => count($records),
    'missing_records' => $missingRecords,
    'size_mismatches' => $sizeMismatches,
    'hash_mismatches' => $hashMismatches,
    'invalid_hashes' => $invalidHashes,
    'duplicate_version_numbers' => $duplicateVersionNumbers,
    'version_number_gaps_warning' => $versionGaps,
    'unregistered_files' => $unregisteredFiles,
    'changes_made' => false,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
$hasErrors = !is_dir($storageRoot) || $missingRecords !== [] || $sizeMismatches !== []
    || $hashMismatches !== [] || $invalidHashes !== [] || $duplicateVersionNumbers !== []
    || $unregisteredFiles !== [];
exit($hasErrors ? 2 : 0);
