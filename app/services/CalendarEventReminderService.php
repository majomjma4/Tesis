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
        $settings = (new SystemSettingModel())->all();
        $reminderDays = max(0, (int)($settings['calendar_reminder_days'] ?? 3));
        $todayKey = $today->format('Y-m-d');
        $advanceKey = $today->modify("+{$reminderDays} days")->format('Y-m-d');

        $events = $db->prepare(
            'SELECT DISTINCT e.id, e.project_id, e.title, e.event_type, e.event_date, e.event_time
             FROM project_events e
             LEFT JOIN projects p ON p.id=e.project_id
             WHERE e.is_completed = 0 AND e.event_date IN (:today, :advance_date)
               AND (
                   (e.project_id IS NULL AND e.created_by=:personal_owner)
                   OR
                   (e.project_id IS NOT NULL AND p.deleted_at IS NULL AND p.withdrawn_at IS NULL
                    AND EXISTS (
                        SELECT 1 FROM project_participants pp
                        WHERE pp.project_id=e.project_id AND pp.user_id=:project_owner
                          AND LOWER(pp.role_code)=\'student\' AND pp.status=\'active\' AND pp.removed_at IS NULL
                    ))
               )'
        );
        $events->execute(['personal_owner' => $ownerId, 'project_owner' => $ownerId, 'today' => $todayKey, 'advance_date' => $advanceKey]);

        $insert = $db->prepare(
            "INSERT IGNORE INTO notifications
             (user_id, project_id, type, title, message, action_url, action_label, metadata, deduplication_key)
             VALUES (:user, :project, 'reminder', :title, :message, :url, 'Abrir calendario', :metadata, :deduplication)"
        );
        $created = 0;
        foreach ($events->fetchAll(PDO::FETCH_ASSOC) as $event) {
            $eventDate = (string)$event['event_date'];
            $isToday = $eventDate === $todayKey;
            $kind = $isToday ? 'today' : 'advance';
            $eventTitle = (string)$event['title'];
            $time = empty($event['event_time']) ? null : substr((string)$event['event_time'], 0, 5);
            $when = $time === null ? '' : ' a las ' . $time;
            $title = $isToday ? 'Hoy: ' . $eventTitle : 'Recordatorio: ' . $eventTitle . ($reminderDays > 0 ? ' en ' . $reminderDays . ' ' . ($reminderDays === 1 ? 'día' : 'días') : '');
            $message = $isToday
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
