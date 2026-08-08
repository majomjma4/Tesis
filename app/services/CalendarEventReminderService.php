<?php

declare(strict_types=1);

/** Genera avisos internos idempotentes para los eventos personales de un usuario. */
final class CalendarEventReminderService
{
    public function __construct(private readonly ?PDO $db = null, private readonly ?DateTimeImmutable $today = null)
    {
    }

    public function syncForOwner(int $ownerId): int
    {
        if ($ownerId < 1) return 0;
        $db = $this->db ?? Database::connection();
        $today = $this->today ?? new DateTimeImmutable('today');
        $todayKey = $today->format('Y-m-d');
        $threeDaysKey = $today->modify('+3 days')->format('Y-m-d');

        $events = $db->prepare(
            'SELECT id, project_id, title, event_type, event_date, event_time
             FROM project_events
             WHERE created_by = :owner AND is_completed = 0 AND event_date IN (:today, :three_days)'
        );
        $events->execute(['owner' => $ownerId, 'today' => $todayKey, 'three_days' => $threeDaysKey]);

        $insert = $db->prepare(
            "INSERT IGNORE INTO notifications
             (user_id, project_id, type, title, message, action_url, action_label, metadata, deduplication_key)
             VALUES (:user, :project, 'reminder', :title, :message, :url, 'Abrir calendario', :metadata, :deduplication)"
        );
        $created = 0;
        foreach ($events->fetchAll(PDO::FETCH_ASSOC) as $event) {
            $eventDate = (string)$event['event_date'];
            $kind = $eventDate === $todayKey ? 'today' : 'three_days';
            $eventTitle = (string)$event['title'];
            $time = empty($event['event_time']) ? null : substr((string)$event['event_time'], 0, 5);
            $when = $time === null ? '' : ' a las ' . $time;
            $title = $kind === 'today' ? 'Hoy: ' . $eventTitle : 'Recordatorio: ' . $eventTitle . ' en 3 días';
            $message = $kind === 'today'
                ? 'Tienes pendiente “' . $eventTitle . '” para hoy' . $when . '.'
                : 'Tienes pendiente “' . $eventTitle . '” para el ' . $this->formatDate($eventDate) . $when . '.';
            $deduplication = 'calendar-event:' . (int)$event['id'] . ':' . $kind . ':' . $eventDate;
            $insert->execute([
                'user' => $ownerId,
                'project' => $event['project_id'] === null ? null : (int)$event['project_id'],
                'title' => $title,
                'message' => $message,
                'url' => route('calendar') . '&event_id=' . (int)$event['id'],
                'metadata' => json_encode([
                    'source' => 'calendar_event_reminder',
                    'event_id' => (int)$event['id'],
                    'event_date' => $eventDate,
                    'event_time' => $time,
                    'event_type' => (string)$event['event_type'],
                    'reminder' => $kind,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'deduplication' => $deduplication,
            ]);
            $created += $insert->rowCount();
        }
        return $created;
    }

    private function formatDate(string $date): string
    {
        return (new DateTimeImmutable($date))->format('d/m/Y');
    }
}
