<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

try {
    $db = Database::connection();
    $checks = [
        'summary' => 'SELECT (SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL AND purged_at IS NULL) users, (SELECT COUNT(*) FROM projects WHERE deleted_at IS NOT NULL) projects, (SELECT COUNT(*) FROM support_materials WHERE deleted_at IS NOT NULL AND purged_at IS NULL) materials',
        'orphan_project_files' => 'SELECT COUNT(*) total FROM project_files f LEFT JOIN projects p ON p.id=f.project_id WHERE p.id IS NULL',
        'orphan_material_files' => 'SELECT COUNT(*) total FROM support_material_files f LEFT JOIN support_materials m ON m.id=f.material_id WHERE m.id IS NULL',
        'projects_missing_creator' => 'SELECT COUNT(*) total FROM projects p LEFT JOIN users u ON u.id=p.created_by WHERE u.id IS NULL',
        'projects_missing_tutor' => 'SELECT COUNT(*) total FROM projects p LEFT JOIN users u ON u.id=p.tutor_id WHERE p.tutor_id IS NOT NULL AND u.id IS NULL',
        'expired' => 'SELECT (SELECT COUNT(*) FROM users WHERE deleted_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 60 DAY) AND purged_at IS NULL) users, (SELECT COUNT(*) FROM projects WHERE deleted_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 60 DAY)) projects, (SELECT COUNT(*) FROM support_materials WHERE deleted_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 60 DAY) AND purged_at IS NULL) materials',
        'active_admins' => "SELECT COUNT(*) total FROM users WHERE is_admin=1 AND status='active' AND deleted_at IS NULL AND purged_at IS NULL",
    ];
    $result = [];
    foreach ($checks as $name => $sql) $result[$name] = $db->query($sql)->fetch();
    $result['missing_recoverable_files'] = ['projects'=>0, 'materials'=>0];
    $projectFiles = $db->query('SELECT project_id,storage_name FROM project_files WHERE purged_at IS NULL')->fetchAll();
    $projectStorage = new ProjectDocumentFileService();
    foreach ($projectFiles as $file) {
        try { $projectStorage->resolveStoredFile((int)$file['project_id'], (string)$file['storage_name']); }
        catch (Throwable) { $result['missing_recoverable_files']['projects']++; }
    }
    $materialFiles = $db->query('SELECT relative_path FROM support_material_files WHERE purged_at IS NULL')->fetchAll(PDO::FETCH_COLUMN);
    $materialStorage = new SupportMaterialFileService();
    foreach ($materialFiles as $path) if (!$materialStorage->isAvailable((string)$path)) $result['missing_recoverable_files']['materials']++;
    $summary = $result['summary'];
    $result['summary']['total'] = (int)$summary['users'] + (int)$summary['projects'] + (int)$summary['materials'];
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(((int)$result['orphan_project_files']['total'] + (int)$result['orphan_material_files']['total']) === 0 ? 0 : 2);
} catch (Throwable $exception) {
    fwrite(STDERR, 'No fue posible verificar la integridad de Papelera.' . PHP_EOL);
    error_log('Trash integrity check: ' . $exception->getMessage());
    exit(1);
}
