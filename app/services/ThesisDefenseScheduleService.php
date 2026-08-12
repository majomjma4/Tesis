<?php

declare(strict_types=1);

final class ThesisDefenseScheduleException extends InvalidArgumentException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->status;
    }
}

/** Gestiona la jornada global de defensas por período académico. */
final class ThesisDefenseScheduleService
{
    /** @return array{academic_period_id:int,defense_date:?string,defense_time:?string}|null */
    public function forPeriod(int $periodId): ?array
    {
        if ($periodId < 1) return null;
        $statement = Database::connection()->prepare(
            'SELECT academic_period_id, defense_date, defense_time
             FROM academic_defense_schedules WHERE academic_period_id=:period'
        );
        $statement->execute(['period' => $periodId]);
        return $statement->fetch() ?: null;
    }

    /** @param list<int> $periodIds @return array<int,array{academic_period_id:int,defense_date:?string,defense_time:?string}> */
    public function forPeriods(array $periodIds): array
    {
        $periodIds = array_values(array_unique(array_filter(array_map('intval', $periodIds), static fn (int $id): bool => $id > 0)));
        if ($periodIds === []) return [];
        $marks = implode(',', array_fill(0, count($periodIds), '?'));
        $statement = Database::connection()->prepare(
            "SELECT academic_period_id, defense_date, defense_time
             FROM academic_defense_schedules WHERE academic_period_id IN ($marks)"
        );
        $statement->execute($periodIds);
        $schedules = [];
        foreach ($statement->fetchAll() as $row) $schedules[(int) $row['academic_period_id']] = $row;
        return $schedules;
    }

    /** @return array{action:string,schedule:?array{academic_period_id:int,defense_date:?string,defense_time:?string},recipients:int} */
    public function save(int $periodId, array $input, int $actorId): array
    {
        return Database::transaction(fn (PDO $db): array => $this->saveTx($db, $periodId, $input, $actorId));
    }

    /** @return array{action:string,schedule:?array{academic_period_id:int,defense_date:?string,defense_time:?string},recipients:int} */
    private function saveTx(PDO $db, int $periodId, array $input, int $actorId): array
    {
        if ($periodId < 1) throw new ThesisDefenseScheduleException('Selecciona un período académico válido.');
        $periodStatement = $db->prepare('SELECT id, name FROM academic_periods WHERE id=:id FOR UPDATE');
        $periodStatement->execute(['id' => $periodId]);
        $period = $periodStatement->fetch();
        if (!$period) throw new ThesisDefenseScheduleException('El período académico seleccionado no está disponible.', 404);

        $currentStatement = $db->prepare(
            'SELECT id, academic_period_id, defense_date, defense_time
             FROM academic_defense_schedules WHERE academic_period_id=:period FOR UPDATE'
        );
        $currentStatement->execute(['period' => $periodId]);
        $current = $currentStatement->fetch() ?: null;
        $state = $this->state($input);
        $previous = $current ? $this->auditState($current) : null;

        if ($state['defense_date'] === null && $state['defense_time'] === null) {
            if (!$current) return ['action' => 'unchanged', 'schedule' => null, 'recipients' => 0];
            $delete = $db->prepare('DELETE FROM academic_defense_schedules WHERE id=:id');
            $delete->execute(['id' => $current['id']]);
            (new AdminActivityService($db))->record(
                $actorId,
                'thesis_defense_schedule_cleared',
                'Limpió la programación global de defensas',
                'Gestión de Titulación',
                'academic_defense_schedule',
                (int) $current['id'],
                (string) $period['name'],
                'correct',
                ['academic_period_id' => $periodId, 'period_name' => (string) $period['name'], 'previous' => $previous, 'current' => null]
            );
            return ['action' => 'cleared', 'schedule' => null, 'recipients' => 0];
        }

        if ($current && $previous === $state) {
            return ['action' => 'unchanged', 'schedule' => $state + ['academic_period_id' => $periodId], 'recipients' => 0];
        }

        if ($current) {
            $update = $db->prepare(
                'UPDATE academic_defense_schedules
                 SET defense_date=:defense_date, defense_time=:defense_time, updated_by=:actor, updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id'
            );
            $update->execute($state + ['actor' => $actorId, 'id' => $current['id']]);
            $scheduleId = (int) $current['id'];
            $action = 'updated';
        } else {
            $insert = $db->prepare(
                'INSERT INTO academic_defense_schedules(academic_period_id, defense_date, defense_time, updated_by)
                 VALUES(:academic_period_id, :defense_date, :defense_time, :actor)'
            );
            $insert->execute($state + ['academic_period_id' => $periodId, 'actor' => $actorId]);
            $scheduleId = (int) $db->lastInsertId();
            $action = 'created';
        }

        $recipients = $this->notifyStudents($db, $periodId, $state, $action, $scheduleId);
        (new AdminActivityService($db))->record(
            $actorId,
            'thesis_defense_schedule_' . $action,
            $action === 'created' ? 'Programó la jornada global de defensas' : 'Actualizó la jornada global de defensas',
            'Gestión de Titulación',
            'academic_defense_schedule',
            $scheduleId,
            (string) $period['name'],
            'correct',
            [
                'academic_period_id' => $periodId,
                'period_name' => (string) $period['name'],
                'previous' => $previous,
                'current' => $state,
                'notified_students' => $recipients,
            ]
        );
        return ['action' => $action, 'schedule' => $state + ['academic_period_id' => $periodId], 'recipients' => $recipients];
    }

    /** @return array{defense_date:?string,defense_time:?string} */
    private function state(array $input): array
    {
        $date = trim((string) ($input['defense_date'] ?? ''));
        $time = trim((string) ($input['defense_time'] ?? ''));
        if ($date !== '') {
            $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$value || $value->format('Y-m-d') !== $date) throw new ThesisDefenseScheduleException('La fecha de defensa no es válida.');
        }
        if ($time !== '') {
            $value = DateTimeImmutable::createFromFormat('!H:i', $time);
            if (!$value || $value->format('H:i') !== $time) throw new ThesisDefenseScheduleException('La hora de defensa no es válida.');
        }
        return ['defense_date' => $date ?: null, 'defense_time' => $time === '' ? null : $time . ':00'];
    }

    /** @return array{defense_date:?string,defense_time:?string} */
    private function auditState(array $state): array
    {
        return ['defense_date' => $state['defense_date'] ?: null, 'defense_time' => $state['defense_time'] ?: null];
    }

    /** @param array{defense_date:?string,defense_time:?string} $state */
    private function notifyStudents(PDO $db, int $periodId, array $state, string $action, int $scheduleId): int
    {
        $message = $this->notificationMessage($state, $action === 'updated');
        $metadata = json_encode([
            'source' => 'thesis_defense_schedule',
            'academic_period_id' => $periodId,
            'defense_date' => $state['defense_date'],
            'defense_time' => $state['defense_time'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $key = 'thesis-defense-schedule:' . $scheduleId . ':' . bin2hex(random_bytes(8));
        $statement = $db->prepare(
            "INSERT INTO notifications(user_id, project_id, type, title, message, action_url, action_label, metadata, deduplication_key)
             SELECT DISTINCT pp.user_id, NULL, 'tribunal', :title, :message, :url, :label, :metadata, :deduplication
             FROM projects p
             INNER JOIN project_types pt ON pt.id=p.project_type_id AND pt.code='thesis'
             INNER JOIN project_participants pp ON pp.project_id=p.id
             INNER JOIN users u ON u.id=pp.user_id
             WHERE p.academic_period_id=:period
               AND p.status='defense' AND p.deleted_at IS NULL
               AND LOWER(pp.role_code)='student' AND pp.status='active' AND pp.removed_at IS NULL
               AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL"
        );
        $statement->execute([
            'title' => $action === 'updated' ? 'Programación de defensa actualizada' : 'Defensa programada',
            'message' => $message,
            'url' => route('projects'),
            'label' => 'Ver proyectos',
            'metadata' => $metadata,
            'deduplication' => $key,
            'period' => $periodId,
        ]);
        return $statement->rowCount();
    }

    /** @param array{defense_date:?string,defense_time:?string} $state */
    private function notificationMessage(array $state, bool $updated): string
    {
        $prefix = $updated ? 'Se ha actualizado ' : 'Se ha programado ';
        $date = $state['defense_date'] ? (new DateTimeImmutable((string) $state['defense_date']))->format('d/m/Y') : null;
        $time = $state['defense_time'] ? substr((string) $state['defense_time'], 0, 5) : null;
        if ($date && $time) return $prefix . 'la defensa de los proyectos de Titulación para el ' . $date . ' a las ' . $time . '.';
        if ($date) return $prefix . 'la fecha de defensa de los proyectos de Titulación para el ' . $date . '.';
        return $prefix . 'la hora prevista para las defensas de Titulación a las ' . $time . '.';
    }
}
