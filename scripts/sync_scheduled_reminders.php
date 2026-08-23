<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse desde CLI.\n");
    exit(1);
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$config = require APP_PATH . '/config/app.php';
$timezone = (string) ($config['timezone'] ?? 'America/Guayaquil');
if (!in_array($timezone, timezone_identifiers_list(), true)) $timezone = 'America/Guayaquil';
date_default_timezone_set($timezone);
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$failures = 0;

try {
    $db = Database::connection();
    $owners = $db->query(
        "SELECT DISTINCT u.id
         FROM users u
         WHERE u.status='active'
           AND u.deleted_at IS NULL
           AND u.purged_at IS NULL
           AND (
               EXISTS (SELECT 1 FROM project_events personal_event
                      WHERE personal_event.project_id IS NULL AND personal_event.created_by=u.id)
               OR EXISTS (SELECT 1 FROM project_events project_event
                          INNER JOIN projects project_record ON project_record.id=project_event.project_id
                          INNER JOIN project_participants project_participant
                                  ON project_participant.project_id=project_event.project_id
                                 AND project_participant.user_id=u.id
                          WHERE project_event.project_id IS NOT NULL
                            AND project_record.deleted_at IS NULL
                            AND project_record.withdrawn_at IS NULL
                            AND LOWER(project_participant.role_code)='student'
                            AND project_participant.status='active'
                            AND project_participant.removed_at IS NULL)
           )
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
