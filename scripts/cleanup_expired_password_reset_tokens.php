<?php

declare(strict_types=1);

/** Removes only password-reset tokens that the current model already considers expired and unused. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$timezone = (string) ($GLOBALS['config']['timezone'] ?? 'America/Guayaquil');
if (in_array($timezone, timezone_identifiers_list(), true)) date_default_timezone_set($timezone);

$action = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($action, ['dry-run', 'cleanup', 'verify'], true)) {
    fwrite(STDERR, "Usage: php scripts/cleanup_expired_password_reset_tokens.php [dry-run|cleanup|verify]\n");
    exit(1);
}

$readRows = static function (PDO $db, bool $forUpdate = false): array {
    $sql = 'SELECT pr.id,pr.user_id,pr.created_at,pr.expires_at,pr.used_at,
                   u.id AS joined_user_id,u.status,u.deleted_at,u.purged_at
            FROM password_reset_tokens pr
            LEFT JOIN users u ON u.id=pr.user_id
            ORDER BY pr.id' . ($forUpdate ? ' FOR UPDATE' : '');
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
};
$describe = static function (array $row): array {
    $expired = PasswordResetModel::isTokenExpired($row);
    $unused = $row['used_at'] === null;
    $userValid = $row['joined_user_id'] !== null
        && (string) $row['status'] === 'active'
        && $row['deleted_at'] === null
        && $row['purged_at'] === null;
    return [
        'id' => (int) $row['id'],
        'user_id' => (int) $row['user_id'],
        'created_at' => $row['created_at'],
        'expires_at' => $row['expires_at'],
        'used_at' => $row['used_at'],
        'expired_by_current_logic' => $expired,
        'unused' => $unused,
        'user_exists' => $row['joined_user_id'] !== null,
        'user_status' => $row['status'],
        'user_deleted' => $row['deleted_at'] !== null,
        'user_purged' => $row['purged_at'] !== null,
        'currently_usable' => !$expired && $unused && $userValid,
        'cleanup_eligible' => $expired && $unused,
    ];
};

$database = Database::connection();
$rows = array_map($describe, $readRows($database));
$eligible = array_values(array_filter($rows, static fn(array $row): bool => $row['cleanup_eligible']));
$removed = [];

if ($action === 'cleanup') {
    $removed = Database::transaction(static function (PDO $db) use ($describe, $readRows): array {
        $lockedRows = array_map($describe, $readRows($db, true));
        $eligibleRows = array_values(array_filter($lockedRows, static fn(array $row): bool => $row['cleanup_eligible']));
        if ($eligibleRows === []) return [];
        $placeholders = implode(',', array_fill(0, count($eligibleRows), '?'));
        $delete = $db->prepare(
            'DELETE FROM password_reset_tokens
             WHERE used_at IS NULL AND id IN (' . $placeholders . ')'
        );
        $delete->execute(array_map(static fn(array $row): int => (int) $row['id'], $eligibleRows));
        if ($delete->rowCount() !== count($eligibleRows)) {
            throw new RuntimeException('El cleanup no retiro exactamente los tokens elegibles bloqueados.');
        }
        return $eligibleRows;
    });
}

$remainingRows = array_map($describe, $readRows($database));
$remaining = array_values(array_filter($remainingRows, static fn(array $row): bool => $row['cleanup_eligible']));
echo json_encode([
    'mode' => $action,
    'checked_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'ttl_minutes' => PasswordResetModel::tokenTtlMinutes(),
    'token_count_before' => count($rows),
    'expired_unused_before' => count($eligible),
    'eligible_tokens_before' => $eligible,
    'removed_count' => count($removed),
    'removed_tokens' => $removed,
    'token_count_after' => count($remainingRows),
    'expired_unused_after' => count($remaining),
    'currently_usable_after' => count(array_filter($remainingRows, static fn(array $row): bool => $row['currently_usable'])),
    'database_changes' => $action === 'cleanup' ? count($removed) : 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

if (in_array($action, ['verify', 'cleanup'], true) && $remaining !== []) exit(2);
