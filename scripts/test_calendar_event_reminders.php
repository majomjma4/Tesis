<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$db = Database::connection();
$owners = $db->query("SELECT id FROM users WHERE status='active' AND deleted_at IS NULL AND purged_at IS NULL ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
if (count($owners) < 2) throw new RuntimeException('Se requieren dos usuarios activos para la prueba.');
[$owner, $otherOwner] = array_map('intval', $owners);
$today = new DateTimeImmutable('2026-08-08');
$service = new CalendarEventReminderService($db, $today);
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); echo "OK  $message\n"; };

$db->beginTransaction();
try {
    $insertEvent = $db->prepare('INSERT INTO project_events(project_id,title,event_type,priority,event_date,event_time,description,is_completed,created_by) VALUES(NULL,:title,\'personal\',\'medium\',:date,:time,NULL,:completed,:owner)');
    $create = static function (string $title, string $date, ?string $time, bool $completed = false) use ($insertEvent, $owner, $db): int { $insertEvent->execute(['title' => $title, 'date' => $date, 'time' => $time, 'completed' => (int)$completed, 'owner' => $owner]); return (int)$db->lastInsertId(); };
    $todayEvent = $create('Reunión personal', '2026-08-08', '14:30');
    $threeDayEvent = $create('Comprar materiales', '2026-08-11', null);
    $futureEvent = $create('Evento futuro', '2026-08-12', null);
    $pastEvent = $create('Evento pasado', '2026-08-07', null);
    $completedEvent = $create('Evento completado', '2026-08-08', null, true);

    $assert($service->syncForOwner($owner) >= 2, 'se ejecuta la generación de recordatorios de hoy y anticipados');
    $todayKey = 'calendar-event:' . $todayEvent . ':today:2026-08-08';
    $advanceKey = 'calendar-event:' . $threeDayEvent . ':advance:2026-08-11';
    $query = $db->prepare('SELECT title,message,deduplication_key FROM notifications WHERE user_id=:owner AND deduplication_key IN (:today_key,:advance_key) ORDER BY id');
    $query->execute(['owner' => $owner, 'today_key' => $todayKey, 'advance_key' => $advanceKey]); $notifications = $query->fetchAll(PDO::FETCH_ASSOC);
    $assert(count($notifications) === 2, 'eventos fuera de rango, pasados y completados no generan recordatorio');
    $assert(str_contains($notifications[0]['message'] . $notifications[1]['message'], '14:30'), 'el recordatorio con hora muestra la hora');
    $advanceNotification = array_values(array_filter($notifications, static fn(array $notification): bool => $notification['deduplication_key'] === $advanceKey))[0] ?? [];
    $assert(!str_contains((string)($advanceNotification['message'] ?? ''), 'a las'), 'el recordatorio sin hora no muestra información vacía');
    $assert($service->syncForOwner($owner) === 0, 'una segunda ejecución no duplica recordatorios');
    $other = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:owner AND deduplication_key IN (:today_key,:advance_key)');
    $other->execute(['owner' => $otherOwner, 'today_key' => $todayKey, 'advance_key' => $advanceKey]); $assert((int)$other->fetchColumn() === 0, 'no se crean recordatorios para otro usuario');

    $db->prepare('UPDATE project_events SET event_date=:date WHERE id=:id AND created_by=:owner')->execute(['date' => '2026-08-08', 'id' => $futureEvent, 'owner' => $owner]);
    $service->syncForOwner($owner);
    $movedKey = 'calendar-event:' . $futureEvent . ':today:2026-08-08';
    $moved = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:owner AND deduplication_key=:key');
    $moved->execute(['owner' => $owner, 'key' => $movedKey]);
    $assert((int)$moved->fetchColumn() === 1, 'un evento reprogramado genera el recordatorio al cumplir la nueva fecha');
    $db->prepare('DELETE FROM project_events WHERE id=:id AND created_by=:owner')->execute(['id' => $futureEvent, 'owner' => $owner]);
    $assert($service->syncForOwner($owner) === 0, 'un evento eliminado no genera nuevos recordatorios');
    $assert($todayEvent > 0 && $threeDayEvent > 0 && $pastEvent > 0 && $completedEvent > 0, 'fixtures de recordatorios válidos');
    $db->rollBack();
    echo "Pruebas de recordatorios del calendario finalizadas sin residuos.\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
}
