<?php

declare(strict_types=1);

/** Read-only audit of password-reset tokens using the same expiry rule as the application. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();
$timezone = (string) ($GLOBALS['config']['timezone'] ?? 'America/Guayaquil');
if (in_array($timezone, timezone_identifiers_list(), true)) date_default_timezone_set($timezone);

$database = Database::connection();
$ttlMinutes = PasswordResetModel::tokenTtlMinutes();
$rows = $database->query(
    'SELECT pr.id,pr.user_id,pr.created_at,pr.expires_at,pr.used_at,
            u.id AS joined_user_id,u.status,u.deleted_at,u.purged_at
     FROM password_reset_tokens pr
     LEFT JOIN users u ON u.id=pr.user_id
     ORDER BY pr.id'
)->fetchAll(PDO::FETCH_ASSOC);

$audited = [];
foreach ($rows as $row) {
    $expired = PasswordResetModel::isTokenExpired($row);
    $unused = $row['used_at'] === null;
    $userValid = $row['joined_user_id'] !== null
        && (string) $row['status'] === 'active'
        && $row['deleted_at'] === null
        && $row['purged_at'] === null;
    $audited[] = [
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
}

$eligible = array_values(array_filter($audited, static fn(array $row): bool => $row['cleanup_eligible']));
$expiredUnusedValidUsers = array_values(array_filter($eligible, static fn(array $row): bool => $row['user_exists'] && !$row['user_deleted'] && !$row['user_purged'] && $row['user_status'] === 'active'));
echo json_encode([
    'checked_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'ttl_minutes' => $ttlMinutes,
    'token_count' => count($audited),
    'expired_unused_count' => count($eligible),
    'expired_unused_valid_user_count' => count($expiredUnusedValidUsers),
    'currently_usable_count' => count(array_filter($audited, static fn(array $row): bool => $row['currently_usable'])),
    'tokens' => $audited,
    'read_only' => true,
    'database_changes' => 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
