<?php

declare(strict_types=1);

/** Read-only inventory for physical support-material files not represented in the DB. */
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$config = require APP_PATH . '/config/app.php';
$timezone = (string) ($config['timezone'] ?? 'America/Guayaquil');
if (in_array($timezone, timezone_identifiers_list(), true)) date_default_timezone_set($timezone);

$database = Database::connection();
$fileService = new SupportMaterialFileService();
$materials = [];
foreach ($database->query('SELECT id,title,status,is_available,deleted_at,purged_at FROM support_materials ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $materials[(int) $row['id']] = [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'status' => (string) $row['status'],
        'is_available' => (int) ($row['is_available'] ?? 0) === 1,
        'deleted' => $row['deleted_at'] !== null,
        'purged' => $row['purged_at'] !== null,
    ];
}

$records = $database->query(
    "SELECT 'current' source,id,material_id,original_name,relative_path,size_bytes,sha256
     FROM support_material_files WHERE purged_at IS NULL
     UNION ALL
     SELECT 'version' source,id,material_id,original_name,relative_path,size_bytes,sha256
     FROM support_material_file_versions
     ORDER BY material_id,source,id"
)->fetchAll(PDO::FETCH_ASSOC);

$registeredPaths = [];
$registeredHashes = [];
$registeredNames = [];
foreach ($records as $record) {
    $relative = str_replace('\\', '/', (string) $record['relative_path']);
    $materialId = (int) $record['material_id'];
    $name = basename(str_replace('\\', '/', (string) $record['original_name']));
    $registeredPaths[$relative] = true;
    $normalizedName = static function (string $value): string {
        $value = mb_strtolower($value, 'UTF-8');
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_D);
            if (is_string($normalized)) $value = (string) preg_replace('/\p{Mn}+/u', '', $normalized);
        }
        return (string) preg_replace('/[^a-z0-9]+/u', '', $value);
    };
    $registeredNames[$normalizedName($name)][] = [
        'source' => (string) $record['source'],
        'id' => (int) $record['id'],
        'material_id' => $materialId,
        'original_name' => $name,
        'relative_path' => $relative,
    ];
    $path = $fileService->resolveRelativePath($relative);
    if ($path !== null) {
        $hash = strtolower((string) hash_file('sha256', $path));
        if (preg_match('/^[a-f0-9]{64}$/', $hash) === 1) {
            $registeredHashes[$hash][] = [
                'source' => (string) $record['source'],
                'id' => (int) $record['id'],
                'material_id' => $materialId,
                'original_name' => $name,
                'relative_path' => $relative,
            ];
        }
    }
}

$normalize = static function (string $value): string {
    $value = mb_strtolower($value, 'UTF-8');
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_D);
        if (is_string($normalized)) $value = (string) preg_replace('/\p{Mn}+/u', '', $normalized);
    }
    return (string) preg_replace('/[^a-z0-9]+/u', '', $value);
};

/* Search only source/configuration text. Runtime storage, vendor, recovery and backups are excluded. */
$references = [];
$referenceRoots = [
    ROOT_PATH . '/app', ROOT_PATH . '/public', ROOT_PATH . '/scripts',
    ROOT_PATH . '/database', ROOT_PATH . '/README.md', ROOT_PATH . '/z archivos md',
];
$textExtensions = ['php', 'js', 'css', 'md', 'txt', 'json', 'sql', 'ps1', 'html', 'xml', 'ini', 'yml', 'yaml'];
$skipDirectory = static function (string $path): bool {
    $normalized = strtolower(str_replace('\\', '/', $path));
    foreach (['/.git/', '/vendor/', '/storage/', '/dist/', '/recovery/', '/backups/', '/qa-backups/'] as $needle) {
        if (str_contains($normalized, $needle)) return true;
    }
    return false;
};
$sourceFiles = [];
foreach ($referenceRoots as $root) {
    if (is_file($root)) { $sourceFiles[] = $root; continue; }
    if (!is_dir($root)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if ($entry->isFile()) $sourceFiles[] = $entry->getPathname();
    }
}

$unregistered = [];
$storageRoot = ROOT_PATH . '/storage/support-materials';
$allowedExtensions = ['pdf', 'docx', 'xlsx', 'pptx', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'zip'];
if (is_dir($storageRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if (!$entry->isFile()) continue;
        $absolute = $entry->getPathname();
        $relative = str_replace('\\', '/', substr($absolute, strlen($storageRoot) + 1));
        if (in_array($relative, ['.gitkeep', 'README.md'], true) || isset($registeredPaths[$relative])) continue;

        $name = basename($relative);
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $hash = strtolower((string) hash_file('sha256', $absolute));
        $parent = explode('/', $relative)[0] ?? '';
        $currentLayout = preg_match('/^[1-9][0-9]*\/[a-f0-9]{40}\.[a-z0-9]+$/i', $relative) === 1;
        $hashMatches = $registeredHashes[$hash] ?? [];
        $nameMatches = $registeredNames[$normalize($name)] ?? [];
        $titleMatches = [];
        foreach ($materials as $material) {
            $titleStem = strtolower((string) pathinfo($name, PATHINFO_FILENAME));
            if ($titleStem !== '' && $normalize($titleStem) === $normalize((string) $material['title'])) {
                $titleMatches[] = ['material_id' => $material['id'], 'title' => $material['title']];
            }
        }

        $fileReferences = [];
        foreach ($sourceFiles as $source) {
            if ($skipDirectory($source) || filesize($source) > 10 * 1024 * 1024) continue;
            $sourceExtension = strtolower((string) pathinfo($source, PATHINFO_EXTENSION));
            if (!in_array($sourceExtension, $textExtensions, true)) continue;
            $contents = @file_get_contents($source);
            if (!is_string($contents)) continue;
            $sourceRelative = str_replace('\\', '/', substr($source, strlen(ROOT_PATH) + 1));
            if (stripos($contents, $name) === false && stripos($contents, $relative) === false) continue;
            $sourceLower = strtolower($sourceRelative);
            $category = str_contains($sourceLower, 'backup') ? 'backup'
                : ((str_contains($sourceLower, 'qa') || str_contains($sourceLower, 'fixture')) ? 'qa' : 'code');
            $fileReferences[] = ['path' => $sourceRelative, 'category' => $category];
        }

        $fixture = in_array($relative, [
            'guia_perfil_tesis.pdf', 'lista_de_verificacion_para_elaboracion_del_perfil_de_tesis.txt',
            'material_tesis_completo.zip', 'seguimiento_practicas.docx', 'instructivo_proyectos_pis.pdf',
            'informe_vinculacion.docx', 'reglamento_material_apoyo.txt',
        ], true) || count(array_filter($fileReferences, static fn(array $ref): bool => $ref['category'] === 'qa')) > 0;
        $temporary = preg_match('/(^|[._-])(tmp|temp|partial|part)([._-]|$)/i', $name) === 1;
        $materialId = ctype_digit($parent) ? (int) $parent : 0;
        $clearMaterialMapping = $currentLayout && isset($materials[$materialId])
            && ($nameMatches !== [] || $titleMatches !== []) && in_array($extension, $allowedExtensions, true);
        $classification = $hashMatches !== [] ? 'B_DUPLICATE_REGISTERED'
            : ($fixture ? 'C_QA_FIXTURE'
            : (!$currentLayout ? 'D_LEGACY_ARTIFACT'
            : ($temporary ? 'E_TEMPORARY'
            : ($clearMaterialMapping ? 'A_VALID_UNREGISTERED' : 'F_UNKNOWN'))));

        $unregistered[] = [
            'relative_path' => $relative,
            'name' => $name,
            'extension' => $extension,
            'size_bytes' => (int) $entry->getSize(),
            'sha256' => $hash,
            'modified_at' => date(DATE_ATOM, $entry->getMTime()),
            'hash_match_registered' => $hashMatches,
            'name_match_registered' => $nameMatches,
            'name_match_material_title' => $titleMatches,
            'references' => $fileReferences,
            'current_storage_layout' => $currentLayout,
            'material_id_from_path' => $materialId,
            'allowed_extension' => in_array($extension, $allowedExtensions, true),
            'likely_real_user_file' => $currentLayout && !$fixture && !$temporary && $hashMatches === [] && $nameMatches === [] ? 'plausible' : 'undetermined',
            'classification' => $classification,
        ];
    }
}

$duplicateGroups = [];
foreach ($unregistered as $row) $duplicateGroups[$row['sha256']][] = $row['relative_path'];
$duplicateGroups = array_values(array_filter($duplicateGroups, static fn(array $paths): bool => count($paths) > 1));
$classificationCounts = array_count_values(array_column($unregistered, 'classification'));
$extensionCounts = array_count_values(array_column($unregistered, 'extension'));

echo json_encode([
    'checked_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'read_only' => true,
    'registered_records_checked' => count($records),
    'registered_paths' => count($registeredPaths),
    'materials' => array_values($materials),
    'unregistered_count' => count($unregistered),
    'classification_counts' => $classificationCounts,
    'extension_counts' => $extensionCounts,
    'duplicate_hash_groups_unregistered' => $duplicateGroups,
    'unregistered_files' => $unregistered,
    'changes_made' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
