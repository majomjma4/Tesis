<?php

declare(strict_types=1);

/** Agenda personal persistida; los eventos se aíslan por su propietario. */
final class CalendarModel
{
    private const TYPES = ['delivery', 'meeting', 'review', 'deadline', 'personal'];
    private const PRIORITIES = ['low', 'medium', 'high'];

    public function getEventsForOwner(int $ownerId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT e.id,CASE WHEN p.id IS NULL THEN NULL ELSE e.project_id END project_id,
                    e.title,e.event_type,e.priority,e.event_date,e.event_time,e.description,e.is_completed,e.created_at,e.updated_at
             FROM project_events e
             LEFT JOIN projects p ON p.id=e.project_id
             WHERE e.created_by=:owner ORDER BY e.event_date,e.event_time IS NULL,e.event_time,e.id'
        );
        $statement->execute(['owner'=>$this->owner($ownerId)]);
        return array_map(fn(array $event): array => $this->present($event), $statement->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function getEventsForTeacher(int $teacherId): array
    {
        $events = $this->getEventsForOwner($teacherId);
        $db = Database::connection();
        $projects = $db->prepare("SELECT p.id FROM projects p WHERE p.deleted_at IS NULL AND (p.tutor_id=:tutor OR EXISTS (
            SELECT 1 FROM project_participants pp WHERE pp.project_id=p.id AND pp.user_id=:participant
              AND pp.status='active' AND pp.removed_at IS NULL AND LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor','tribunal','jury')
        ))");
        $projects->execute(['tutor'=>$teacherId, 'participant'=>$teacherId]);
        $projectIds = array_map('intval', $projects->fetchAll(PDO::FETCH_COLUMN));
        $knownIds = array_fill_keys(array_map(static fn(array $event): string => (string)$event['id'], $events), true);
        foreach ((new TeacherCalendarVisibilityService())->upcoming($db, $teacherId, $projectIds, false) as $item) {
            if (isset($knownIds[(string)$item['id']])) continue;
            $events[] = [
                'id'=>(string)$item['id'], 'projectId'=>(int)($item['project_id'] ?? 0), 'title'=>(string)$item['title'],
                'date'=>(string)$item['date'], 'time'=>$item['time'], 'type'=>(string)$item['type'],
                'priority'=>'medium', 'description'=>(string)($item['context'] ?? ''), 'completed'=>false,
                'updatedAt'=>'', 'readOnly'=>true,
            ];
            $knownIds[(string)$item['id']] = true;
        }
        usort($events, static fn(array $a, array $b): int => [($a['date'] ?? ''), empty($a['time']) ? '23:59:59' : $a['time'], (string)($a['id'] ?? '')] <=> [($b['date'] ?? ''), empty($b['time']) ? '23:59:59' : $b['time'], (string)($b['id'] ?? '')]);
        return $events;
    }

    public function saveForOwner(array $data, int $ownerId): array
    {
        $owner = $this->owner($ownerId);
        $event = $this->normalize($data);
        $id = $this->id($data['id'] ?? null);
        $db = Database::connection();
        if ($id === null) {
            $insert = $db->prepare(
                'INSERT INTO project_events(project_id,title,event_type,priority,event_date,event_time,description,is_completed,created_by)
                 VALUES(NULL,:title,:type,:priority,:date,:time,:description,:completed,:owner)'
            );
            $insert->execute($event + ['owner'=>$owner]);
            return $this->findOwned($db, (int)$db->lastInsertId(), $owner);
        }
        $this->findOwned($db, $id, $owner);
        $update = $db->prepare(
            'UPDATE project_events SET title=:title,event_type=:type,priority=:priority,event_date=:date,event_time=:time,description=:description,is_completed=:completed
             WHERE id=:id AND created_by=:owner'
        );
        $update->execute($event + ['id'=>$id,'owner'=>$owner]);
        return $this->findOwned($db, $id, $owner);
    }

    public function deleteForOwner(mixed $value, int $ownerId): void
    {
        $id = $this->id($value);
        if ($id === null) throw new CalendarEventException('El evento solicitado no es válido.', 422);
        $owner = $this->owner($ownerId);
        $db = Database::connection();
        $this->findOwned($db, $id, $owner);
        $delete = $db->prepare('DELETE FROM project_events WHERE id=:id AND created_by=:owner');
        $delete->execute(['id'=>$id,'owner'=>$owner]);
        if ($delete->rowCount() !== 1) throw new CalendarEventException('El evento ya no está disponible.', 404);
    }

    private function normalize(array $data): array
    {
        $type = (string)($data['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) throw new CalendarEventException('La categoría no es válida.', 422);
        $priority = (string)($data['priority'] ?? '');
        if (!in_array($priority, self::PRIORITIES, true)) throw new CalendarEventException('La prioridad no es válida.', 422);
        $title = trim(strip_tags((string)($data['title'] ?? '')));
        if ($title === '') throw new CalendarEventException('El título es obligatorio.', 422);
        if (mb_strlen($title) > 100) throw new CalendarEventException('El título no puede superar 100 caracteres.', 422);
        $description = trim(strip_tags((string)($data['description'] ?? '')));
        if (mb_strlen($description) > 300) throw new CalendarEventException('La descripción no puede superar 300 caracteres.', 422);
        return [
            'title'=>$title,
            'date'=>$this->date((string)($data['date'] ?? '')),
            'time'=>$this->time($data['time'] ?? null),
            'type'=>$type,
            'priority'=>$priority,
            'description'=>$description === '' ? null : $description,
            'completed'=>filter_var($data['completed'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function findOwned(PDO $db, int $id, int $owner): array
    {
        $exists = $db->prepare('SELECT created_by FROM project_events WHERE id=:id');
        $exists->execute(['id'=>$id]);
        $creator = $exists->fetchColumn();
        if ($creator === false) throw new CalendarEventException('El evento no existe.', 404);
        if ((int)$creator !== $owner) throw new CalendarEventException('No tienes permiso para modificar este evento.', 403);
        $query = $db->prepare('SELECT e.id,CASE WHEN p.id IS NULL THEN NULL ELSE e.project_id END project_id,
                                      e.title,e.event_type,e.priority,e.event_date,e.event_time,e.description,e.is_completed,e.created_at,e.updated_at
                               FROM project_events e LEFT JOIN projects p ON p.id=e.project_id
                               WHERE e.id=:id AND e.created_by=:owner');
        $query->execute(['id'=>$id,'owner'=>$owner]);
        return $this->present((array)$query->fetch());
    }

    private function present(array $event): array
    {
        return ['id'=>(int)$event['id'],'projectId'=>$event['project_id'] === null ? 0 : (int)$event['project_id'],'title'=>(string)$event['title'],'date'=>(string)$event['event_date'],'time'=>empty($event['event_time']) ? null : substr((string)$event['event_time'],0,5),'type'=>(string)$event['event_type'],'priority'=>(string)$event['priority'],'description'=>(string)($event['description'] ?? ''),'completed'=>(bool)$event['is_completed'],'updatedAt'=>(string)($event['updated_at'] ?? $event['created_at'] ?? '')];
    }

    private function owner(int $value): int { if ($value < 1) throw new CalendarEventException('La sesión no está activa.', 403); return $value; }
    private function id(mixed $value): ?int { if ($value === null || $value === '') return null; if (filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false) throw new CalendarEventException('El evento solicitado no es válido.', 422); return (int)$value; }
    private function date(string $value): string { $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);$errors=DateTimeImmutable::getLastErrors();if(!$date||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0))||$date->format('Y-m-d')!==$value)throw new CalendarEventException('La fecha no es válida.',422);return $value; }
    private function time(mixed $value): ?string { $value=trim((string)$value);if($value==='')return null;$time=DateTimeImmutable::createFromFormat('!H:i',$value);$errors=DateTimeImmutable::getLastErrors();if(!$time||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0))||$time->format('H:i')!==$value)throw new CalendarEventException('La hora no es válida.',422);return $value.':00'; }
}

final class CalendarEventException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus) { parent::__construct($message); }
    public function httpStatus(): int { return $this->httpStatus; }
}
