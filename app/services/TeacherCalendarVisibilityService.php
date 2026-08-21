<?php

declare(strict_types=1);

/** Fuente compartida de eventos futuros visibles para un docente. */
final class TeacherCalendarVisibilityService
{
    /** @return list<array<string,mixed>> */
    public function upcoming(PDO $db, int $teacherId, array $projectIds, bool $includePersonal = true): array
    {
        $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn(int $id): bool => $id > 0)));
        $now = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        $today = $now->format('Y-m-d');
        $time = $now->format('H:i:s');
        $items = [];
        $projectClause = $projectIds ? ' OR e.project_id IN (' . implode(',', array_fill(0, count($projectIds), '?')) . ')' : '';
        $creatorClause = $includePersonal ? 'e.created_by=?' : '0=1';
        $events = $db->prepare("SELECT e.id,e.title,e.event_type,e.event_date,e.event_time,e.project_id,e.is_completed,p.title project_title
            FROM project_events e LEFT JOIN projects p ON p.id=e.project_id
            WHERE ({$creatorClause}{$projectClause}) AND e.is_completed=0
              AND (e.event_date>? OR (e.event_date=? AND (e.event_time IS NULL OR e.event_time>=?)))
            ORDER BY e.event_date,e.event_time IS NULL,e.event_time,e.id LIMIT 12");
        $params = $includePersonal ? [$teacherId] : [];
        $events->execute(array_merge($params, $projectIds, [$today, $today, $time]));
        foreach ($events->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = $this->eventItem((int)$row['id'], (string)$row['title'], (string)$row['event_type'], (string)$row['event_date'], $row['event_time'] ?: null,
                $row['project_id'] === null ? null : (int)$row['project_id'], $row['project_title'] === null ? null : (string)$row['project_title']);
        }
        if ($projectIds === []) return array_slice($items, 0, 3);
        $marks = implode(',', array_fill(0, count($projectIds), '?'));
        $defenses = $db->prepare("SELECT d.id,p.id project_id,p.title,d.defense_date,d.defense_time FROM project_defenses d INNER JOIN projects p ON p.id=d.project_id
            WHERE p.id IN ($marks) AND p.deleted_at IS NULL AND d.result IS NULL AND d.defense_date IS NOT NULL
              AND (d.defense_date>? OR (d.defense_date=? AND (d.defense_time IS NULL OR d.defense_time>=?)))
              AND d.attempt_number=(SELECT MAX(current_attempt.attempt_number) FROM project_defenses current_attempt WHERE current_attempt.project_id=d.project_id)
            ORDER BY d.defense_date,d.defense_time IS NULL,d.defense_time,d.id LIMIT 12");
        $defenses->execute(array_merge($projectIds, [$today, $today, $time]));
        foreach ($defenses->fetchAll(PDO::FETCH_ASSOC) as $row) $items[] = $this->eventItem('defense:' . (int)$row['id'], 'Defensa programada', 'defense', (string)$row['defense_date'], $row['defense_time'] ?: null, (int)$row['project_id'], (string)$row['title']);
        $schedules = $db->prepare("SELECT DISTINCT s.id,s.defense_date,s.defense_time,ap.name period_name FROM academic_defense_schedules s INNER JOIN academic_periods ap ON ap.id=s.academic_period_id
            INNER JOIN projects p ON p.academic_period_id=s.academic_period_id INNER JOIN project_types pt ON pt.id=p.project_type_id AND pt.code='thesis'
            WHERE p.id IN ($marks) AND p.status='defense' AND p.deleted_at IS NULL AND s.defense_date IS NOT NULL
              AND (s.defense_date>? OR (s.defense_date=? AND (s.defense_time IS NULL OR s.defense_time>=?)))
            ORDER BY s.defense_date,s.defense_time IS NULL,s.defense_time,s.id LIMIT 12");
        $schedules->execute(array_merge($projectIds, [$today, $today, $time]));
        foreach ($schedules->fetchAll(PDO::FETCH_ASSOC) as $row) $items[] = $this->eventItem('defense-schedule:' . (int)$row['id'], 'Jornada de defensa', 'defense_schedule', (string)$row['defense_date'], $row['defense_time'] ?: null, null, (string)$row['period_name']);
        usort($items, static fn(array $a, array $b): int => [($a['date'] ?? ''), empty($a['time']) ? '23:59:59' : $a['time'], (string)($a['id'] ?? '')] <=> [($b['date'] ?? ''), empty($b['time']) ? '23:59:59' : $b['time'], (string)($b['id'] ?? '')]);
        return array_slice($items, 0, 3);
    }

    private function eventItem(int|string $id, string $title, string $type, string $date, ?string $time, ?int $projectId, ?string $projectTitle): array
    {
        return ['id'=>$id,'title'=>$title,'type'=>$type,'date'=>$date,'time'=>$time ? substr($time, 0, 5) : null,
            'context'=>$projectTitle !== null && trim($projectTitle) !== '' ? $projectTitle : ($type === 'defense_schedule' ? 'Jornada académica' : ($projectId === null ? 'Evento personal' : '')),
            'project_id'=>$projectId,'project_title'=>$projectTitle,'completed'=>false,
            'route'=>$projectId === null ? route('calendar') : route('project-detail').'&id='.$projectId,
            'action_label'=>$projectId === null ? 'Abrir calendario' : 'Abrir proyecto'];
    }
}
