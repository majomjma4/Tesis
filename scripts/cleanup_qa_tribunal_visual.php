<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$action = strtolower(trim((string)($argv[1] ?? '')));
if (!in_array($action, ['dry-run', 'cleanup', 'verify'], true)) {
    fwrite(STDERR, "Uso: php scripts/cleanup_qa_tribunal_visual.php [dry-run|cleanup|verify]\n");
    exit(1);
}

$codes = array_map(static fn(int $n): string => sprintf('QA-TRIBUNAL-VISUAL-%02d', $n), range(1, 5));
$db = Database::connection();
$placeholders = implode(',', array_fill(0, count($codes), '?'));
$find = $db->prepare("SELECT id,code FROM projects WHERE code IN ($placeholders) ORDER BY code");
$find->execute($codes);
$projects = $find->fetchAll(PDO::FETCH_ASSOC);

if ($action === 'verify') {
    echo json_encode(['fixtures' => count($projects), 'codes' => array_column($projects, 'code')], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

if ($action === 'dry-run') {
    echo json_encode(['projects' => $projects, 'count' => count($projects)], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(count($projects) === 5 ? 0 : 1);
}

if (count($projects) !== 5 || array_column($projects, 'code') !== $codes) {
    throw new RuntimeException('Cleanup detenido: no se identificaron exactamente los cinco fixtures esperados.');
}

$ids = array_map(static fn(array $row): int => (int)$row['id'], $projects);
$idMarks = implode(',', array_fill(0, count($ids), '?'));
$db->beginTransaction();
try {
    $tables = [
        'project_file_review_states', 'project_file_versions', 'project_files', 'project_keywords',
        'project_observations', 'project_comments', 'project_deliveries', 'project_defenses',
        'project_adjustment_requests', 'project_downloads', 'project_events', 'project_favorites',
        'project_review_representations', 'repository_direct_publish_requests', 'notifications',
        'project_audit_log', 'project_participants', 'project_stages',
    ];
    foreach ($tables as $table) {
        $db->prepare("DELETE FROM `$table` WHERE project_id IN ($idMarks)")->execute($ids);
    }
    $db->prepare("DELETE FROM projects WHERE id IN ($idMarks) AND code IN ($placeholders)")->execute([...$ids, ...$codes]);
    $db->commit();
    echo json_encode(['removed' => 5, 'codes' => $codes], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
}
