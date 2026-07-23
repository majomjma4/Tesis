<?php
declare(strict_types=1);

final class AdminAcademicModel
{
    public function dashboard(): array
    {
        $db = Database::connection();
        // El período vigente siempre proviene de MariaDB; nunca se deduce del mes calendario.
        $periods = $db->query(
            "SELECT ap.*,
                    (SELECT COUNT(*) FROM projects p WHERE p.academic_period_id=ap.id AND p.deleted_at IS NULL) projects
             FROM academic_periods ap
             ORDER BY ap.starts_on DESC"
        )->fetchAll();

        $active = null;
        $planned = null;
        foreach ($periods as $period) {
            if ($period['status'] === 'active' && $active === null) $active = $period;
            if ($period['status'] === 'planned' && $planned === null) $planned = $period;
        }

        return [
            'periods' => $periods,
            'types' => $db->query(
                "SELECT pt.*,
                        (SELECT COUNT(*) FROM projects p WHERE p.project_type_id=pt.id) projects
                 FROM project_types pt
                 ORDER BY pt.is_active DESC, pt.name"
            )->fetchAll(),
            'promotion' => [
                'source' => $active,
                'target' => $planned,
                'projects' => $active ? (int) $active['projects'] : 0,
                'suggested' => $this->nextPeriod($active),
            ],
        ];
    }

    public function save(string $entity, array $values, int $actor): void
    {
        if (!in_array($entity, ['period', 'type'], true)) {
            throw new InvalidArgumentException('La opción académica seleccionada no es válida.');
        }

        Database::transaction(function (PDO $db) use ($entity, $values, $actor): void {
            if ($entity === 'period') {
                $this->savePeriod($db, $values, $actor);
                return;
            }
            $this->saveType($db, $values, $actor);
        });
    }

    public function promote(int $target, int $actor): array
    {
        if ($target < 1) throw new InvalidArgumentException('Primero planifica el siguiente período.');

        return Database::transaction(function (PDO $db) use ($target, $actor): array {
            $active = $db->query("SELECT * FROM academic_periods WHERE status='active' LIMIT 1 FOR UPDATE")->fetch();
            if (!$active) throw new InvalidArgumentException('No existe un período activo.');

            $statement = $db->prepare("SELECT * FROM academic_periods WHERE id=:id AND status='planned' FOR UPDATE");
            $statement->execute(['id' => $target]);
            $planned = $statement->fetch();
            if (!$planned) throw new InvalidArgumentException('El siguiente período planificado ya no está disponible.');

            $expected = $this->nextPeriod($active);
            if (!$expected || $planned['name'] !== $expected['name']) {
                throw new InvalidArgumentException('El período planificado no corresponde al siguiente período académico.');
            }

            $projects = $db->prepare("SELECT COUNT(*) FROM projects WHERE academic_period_id=:id AND deleted_at IS NULL");
            $projects->execute(['id' => $active['id']]);
            $projectCount = (int) $projects->fetchColumn();

            $db->prepare("UPDATE academic_periods SET status='closed' WHERE id=:id")->execute(['id' => $active['id']]);
            $db->prepare("UPDATE academic_periods SET status='active' WHERE id=:id")->execute(['id' => $planned['id']]);
            $this->audit($db, $actor, 'academic_period_closed', 'period', (int) $active['id'], [
                'activated_period_id' => (int) $planned['id'],
                'projects_preserved' => $projectCount,
            ]);

            return ['closed' => $active['name'], 'activated' => $planned['name'], 'projects' => $projectCount];
        });
    }

    private function savePeriod(PDO $db, array $values, int $actor): void
    {
        $id = (int) ($values['id'] ?? 0);
        $action = (string) ($values['action'] ?? 'save');
        if ($action === 'delete') {
            if ($id < 1) throw new InvalidArgumentException('La planificación seleccionada no es válida.');
            $statement = $db->prepare("SELECT id,name FROM academic_periods WHERE id=:id AND status='planned' FOR UPDATE");
            $statement->execute(['id' => $id]);
            $planned = $statement->fetch();
            if (!$planned) throw new InvalidArgumentException('La planificación ya no está disponible.');
            $references = $db->prepare(
                'SELECT
                    (SELECT COUNT(*) FROM projects WHERE academic_period_id=:projects) +
                    (SELECT COUNT(*) FROM student_enrollments WHERE academic_period_id=:enrollments) +
                    (SELECT COUNT(*) FROM academic_subjects WHERE academic_period_id=:subjects)'
            );
            $references->execute(['projects' => $id, 'enrollments' => $id, 'subjects' => $id]);
            if ((int) $references->fetchColumn() > 0) {
                throw new InvalidArgumentException('No se puede eliminar una planificación que ya tiene información asociada.');
            }
            $db->prepare("DELETE FROM academic_periods WHERE id=:id AND status='planned'")->execute(['id' => $id]);
            $this->audit($db, $actor, 'academic_period_plan_deleted', 'period', $id, ['name' => $planned['name']]);
            return;
        }

        $start = (string) ($values['starts_on'] ?? '');
        $end = (string) ($values['ends_on'] ?? '');
        foreach (['period_term', 'period_year', 'name', 'code'] as $forbiddenField) {
            if (array_key_exists($forbiddenField, $values)) {
                throw new InvalidArgumentException('El siguiente período se calcula automáticamente y no puede modificarse.');
            }
        }

        $active = $db->query("SELECT * FROM academic_periods WHERE status='active' LIMIT 1 FOR UPDATE")->fetch();
        if (!$active) {
            throw new InvalidArgumentException('No existe un período activo desde el cual calcular la planificación.');
        }
        if (!$this->validDate($start) || !$this->validDate($end)) {
            throw new InvalidArgumentException('Ingresa fechas válidas para la planificación.');
        }
        if ($start <= (string) $active['ends_on']) {
            throw new InvalidArgumentException('La fecha de inicio debe ser posterior a la finalización del período actual.');
        }
        if ($end <= $start) {
            throw new InvalidArgumentException('La fecha de finalización debe ser posterior a la fecha de inicio.');
        }
        $planned = $db->query("SELECT * FROM academic_periods WHERE status='planned' LIMIT 1 FOR UPDATE")->fetch();
        if ($planned && (int) $planned['id'] !== $id) {
            throw new InvalidArgumentException('Ya existe un siguiente período planificado.');
        }

        $expected = $this->nextPeriod($active);
        if (!$expected) throw new InvalidArgumentException('No fue posible calcular el siguiente período académico.');
        $name = $expected['name'];
        $code = $expected['year'] . '-' . $expected['term'];
        if ($id > 0) {
            if (!$planned || (int) $planned['id'] !== $id) {
                throw new InvalidArgumentException('La planificación que intentas editar ya no está disponible.');
            }
            $statement = $db->prepare(
                "UPDATE academic_periods
                 SET code=:code,name=:name,starts_on=:start,ends_on=:end
                 WHERE id=:id AND status='planned'"
            );
            $statement->execute(['code' => $code, 'name' => $name, 'start' => $start, 'end' => $end, 'id' => $id]);
            $this->audit($db, $actor, 'academic_period_plan_updated', 'period', $id, ['name' => $name]);
            return;
        }
        $statement = $db->prepare(
            "INSERT INTO academic_periods(code,name,starts_on,ends_on,status)
             VALUES(:code,:name,:start,:end,'planned')"
        );
        $statement->execute(['code' => $code, 'name' => $name, 'start' => $start, 'end' => $end]);
        $this->audit($db, $actor, 'academic_period_planned', 'period', (int) $db->lastInsertId(), ['name' => $name]);
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function saveType(PDO $db, array $values, int $actor): void
    {
        $id = (int) ($values['id'] ?? 0);
        $action = (string) ($values['action'] ?? 'save');
        if ($action === 'deactivate') {
            if ($id < 1) throw new InvalidArgumentException('El tipo de proyecto no es válido.');
            $db->prepare('UPDATE project_types SET is_active=0 WHERE id=:id')->execute(['id' => $id]);
            $this->audit($db, $actor, 'academic_type_deactivated', 'type', $id);
            return;
        }

        $name = trim((string) ($values['name'] ?? ''));
        if (mb_strlen($name) < 3) throw new InvalidArgumentException('Ingresa un nombre válido.');
        if ($id > 0) {
            $db->prepare('UPDATE project_types SET name=:name WHERE id=:id')->execute(['name' => $name, 'id' => $id]);
        } else {
            $base = mb_strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name), 'UTF-8');
            $base = trim($base, '_') ?: 'project_type';
            $code = $base;
            $suffix = 2;
            $check = $db->prepare('SELECT COUNT(*) FROM project_types WHERE code=:code');
            do {
                $check->execute(['code' => $code]);
                if (!(int) $check->fetchColumn()) break;
                $code = $base . '_' . $suffix++;
            } while ($suffix < 100);
            $db->prepare('INSERT INTO project_types(code,name,is_active) VALUES(:code,:name,1)')->execute(['code' => $code, 'name' => $name]);
            $id = (int) $db->lastInsertId();
        }
        $this->audit($db, $actor, 'academic_type_saved', 'type', $id, ['name' => $name]);
    }

    private function nextPeriod(?array $period): ?array
    {
        if (!$period || !preg_match('/^(I|II) PAO (\d{4})$/', (string) $period['name'], $match)) return null;
        $term = $match[1] === 'I' ? 'II' : 'I';
        $year = (int) $match[2] + ($match[1] === 'II' ? 1 : 0);
        return ['term' => $term, 'year' => $year, 'name' => $term . ' PAO ' . $year];
    }

    private function audit(PDO $db, int $actor, string $action, string $type, ?int $id, array $details = []): void
    {
        $statement = $db->prepare(
            'INSERT INTO admin_audit_log(actor_user_id,action,entity_type,entity_id,details)
             VALUES(:actor,:action,:type,:id,:details)'
        );
        $statement->execute([
            'actor' => $actor,
            'action' => $action,
            'type' => $type,
            'id' => $id,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
