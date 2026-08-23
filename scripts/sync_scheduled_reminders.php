<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse desde CLI.\n");
    exit(1);
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$failures = 0;

try {
    $db = Database::connection();
    $owners = $db->query(
        "SELECT DISTINCT u.id
         FROM users u
         INNER JOIN project_events e ON e.created_by=u.id
         WHERE u.status='active'
           AND u.deleted_at IS NULL
           AND u.purged_at IS NULL
         ORDER BY u.id"
    )->fetchAll(PDO::FETCH_COLUMN);

    $calendar = new CalendarEventReminderService($db);
    foreach ($owners as $owner) {
        try {
            $created = $calendar->syncForOwner((int) $owner);
            echo 'calendar owner=' . (int) $owner . ' created=' . $created . "\n";
        } catch (Throwable $error) {
            $failures++;
            fwrite(STDERR, 'calendar owner=' . (int) $owner . ' error=' . $error->getMessage() . "\n");
        }
    }

    try {
        $created = (new AcademicPeriodReminderService($db))->sync();
        echo 'academic_period created=' . $created . "\n";
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, 'academic_period error=' . $error->getMessage() . "\n");
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'scheduler error=' . $error->getMessage() . "\n");
    exit(1);
}

echo 'calendar owners processed=' . count($owners) . "\n";
exit($failures === 0 ? 0 : 1);
