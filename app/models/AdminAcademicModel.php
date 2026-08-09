<?php
declare(strict_types=1);

final class AdminAcademicModel
{
    private const PROJECT_STATUS_LABELS = [
        'development' => 'En desarrollo',
        'under_review' => 'En revisión',
        'approved' => 'Aprobado',
        'defense' => 'En tribunal',
        'tribunal_approved' => 'Aprobado por el Tribunal',
        'published' => 'Publicado',
    ];

    public function dashboard(?int $actor = null): array
    {
        $db = Database::connection();
        // El período vigente siempre proviene de MariaDB; nunca se deduce del mes calendario.
        $periods = $db->query(
            "SELECT ap.*,
                    (
                        SELECT COUNT(DISTINCT p.id)
                        FROM projects p
                        WHERE p.academic_period_id=ap.id
                          AND p.deleted_at IS NULL
                          AND EXISTS (
                              SELECT 1
                              FROM project_types pt
                              JOIN project_participants pp
                                ON pp.project_id=p.id
                               AND pp.role_code='student'
                               AND pp.status='active'
                              JOIN student_profiles sp ON sp.user_id=pp.user_id
                              WHERE pt.id=p.project_type_id
                          )
                    ) academic_projects,
                    (
                        SELECT COUNT(DISTINCT p.id)
                        FROM projects p
                        WHERE p.academic_period_id=ap.id
                          AND p.deleted_at IS NULL
                          AND p.status='published'
                          AND EXISTS (
                              SELECT 1 FROM project_files pf
                              WHERE pf.project_id=p.id AND pf.deleted_at IS NULL
                          )
                          AND EXISTS (
                              SELECT 1
                              FROM project_types pt
                              JOIN project_participants pp
                                ON pp.project_id=p.id
                               AND pp.role_code='student'
                               AND pp.status='active'
                              JOIN student_profiles sp ON sp.user_id=pp.user_id
                              WHERE pt.id=p.project_type_id
                          )
                    ) published_projects
             FROM academic_periods ap
             ORDER BY ap.starts_on DESC"
        )->fetchAll();
        $projectRows = $db->query(
            "SELECT academic_period_id,id,title
             FROM (
                 SELECT p.academic_period_id,p.id,p.title,
                        ROW_NUMBER() OVER (
                            PARTITION BY p.academic_period_id
                            ORDER BY p.created_at DESC,p.id DESC
                        ) position
                 FROM projects p
                 WHERE p.deleted_at IS NULL
                   AND p.status='published'
                   AND EXISTS (
                       SELECT 1 FROM project_files pf
                       WHERE pf.project_id=p.id AND pf.deleted_at IS NULL
                   )
                   AND EXISTS (
                       SELECT 1
                       FROM project_types pt
                       JOIN project_participants pp
                         ON pp.project_id=p.id
                        AND pp.role_code='student'
                        AND pp.status='active'
                       JOIN student_profiles sp ON sp.user_id=pp.user_id
                       WHERE pt.id=p.project_type_id
                   )
             ) period_projects
             WHERE position<=5
             ORDER BY academic_period_id,position"
        )->fetchAll();
        $projectsByPeriod = [];
        foreach ($projectRows as $project) {
            $projectsByPeriod[(int) $project['academic_period_id']][] = [
                'id' => (int) $project['id'],
                'title' => (string) $project['title'],
            ];
        }
        foreach ($periods as &$period) {
            $period['projects'] = $period['status'] === 'closed'
                ? (int) $period['published_projects']
                : (int) $period['academic_projects'];
            $period['project_preview'] = $projectsByPeriod[(int) $period['id']] ?? [];
        }
        unset($period);

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
                        (
                            SELECT COUNT(DISTINCT p.id)
                            FROM projects p
                            WHERE p.project_type_id=pt.id
                              AND p.deleted_at IS NULL
                              AND EXISTS (
                                  SELECT 1
                                  FROM project_participants pp
                                  JOIN student_profiles sp ON sp.user_id=pp.user_id
                                  WHERE pp.project_id=p.id
                                    AND pp.role_code='student'
                                    AND pp.status='active'
                              )
                        ) projects
                        ,(SELECT COUNT(*) FROM projects reference_project WHERE reference_project.project_type_id=pt.id) references_count
                 FROM project_types pt
                 ORDER BY pt.is_active DESC, pt.name"
            )->fetchAll(),
            'material_types' => (new SupportMaterialModel())->administrativeCatalog('material_type', $db),
            'keywords' => (new SupportMaterialModel())->administrativeCatalog('keyword', $db),
            'promotion' => [
                'source' => $active,
                'target' => $planned,
                'projects' => $active ? (int) $active['projects'] : 0,
                'suggested' => $this->nextPeriod($active),
            ],
            'reversal' => $actor ? $this->reversalAvailability($db, $actor) : null,
        ];
    }

    public function save(string $entity, array $values, int $actor): void
    {
        if (!in_array($entity, ['period', 'type', 'material_type', 'keyword'], true)) {
            throw new InvalidArgumentException('La opción académica seleccionada no es válida.');
        }

        Database::transaction(function (PDO $db) use ($entity, $values, $actor): void {
            if ($entity === 'period') {
                $this->savePeriod($db, $values, $actor);
                return;
            }
            if ($entity === 'type') {
                $this->saveType($db, $values, $actor);
                return;
            }
            $this->saveMaterialCatalog($db, $entity, $values, $actor);
        });
    }

    public function promote(int $target, int $actor, bool $confirmEarlyClose = false): array
    {
        if ($target < 1) throw new InvalidArgumentException('Primero planifica el siguiente período.');

        return Database::transaction(function (PDO $db) use ($target, $actor, $confirmEarlyClose): array {
            $active = $db->query("SELECT * FROM academic_periods WHERE status='active' LIMIT 1 FOR UPDATE")->fetch();
            if (!$active) throw new InvalidArgumentException('No existe un período activo.');
            if ((string) $active['ends_on'] > date('Y-m-d') && !$confirmEarlyClose) {
                throw new InvalidArgumentException('El período aún no alcanza su fecha final. Confirma expresamente el cierre anticipado.');
            }

            $statement = $db->prepare("SELECT * FROM academic_periods WHERE id=:id AND status='planned' FOR UPDATE");
            $statement->execute(['id' => $target]);
            $planned = $statement->fetch();
            if (!$planned) throw new InvalidArgumentException('El siguiente período planificado ya no está disponible.');

            $expected = $this->nextPeriod($active);
            if (!$expected || $planned['name'] !== $expected['name']) {
                throw new InvalidArgumentException('El período planificado no corresponde al siguiente período académico.');
            }

            $projects = $db->prepare(
                "SELECT DISTINCT p.id,p.code,p.title,p.status
                 FROM projects p
                 WHERE p.academic_period_id=:id
                   AND p.deleted_at IS NULL
                   AND EXISTS (
                       SELECT 1
                       FROM project_types pt
                       JOIN project_participants pp
                         ON pp.project_id=p.id
                        AND pp.role_code='student'
                        AND pp.status='active'
                       JOIN student_profiles sp ON sp.user_id=pp.user_id
                       WHERE pt.id=p.project_type_id
                   )
                 ORDER BY p.status,p.code"
            );
            $projects->execute(['id' => $active['id']]);
            $periodProjects = $projects->fetchAll();
            $projectCount = count($periodProjects);
            $pendingProjects = array_values(array_map(
                static fn(array $project): array => [
                    'id' => (int) $project['id'],
                    'code' => (string) $project['code'],
                    'title' => (string) $project['title'],
                    'status' => (string) $project['status'],
                    'status_label' => self::PROJECT_STATUS_LABELS[(string) $project['status']] ?? (string) $project['status'],
                ],
                array_filter(
                    $periodProjects,
                    static fn(array $project): bool => (string) $project['status'] !== 'published'
                )
            ));
            if ($pendingProjects !== []) {
                return [
                    'blocked' => true,
                    'reason' => 'unfinished_projects',
                    'pending_projects' => $pendingProjects,
                    'projects' => $projectCount,
                ];
            }

            $db->prepare("UPDATE academic_periods SET status='closed' WHERE id=:id")->execute(['id' => $active['id']]);
            $db->prepare("UPDATE academic_periods SET status='active' WHERE id=:id")->execute(['id' => $planned['id']]);
            $transition = $db->prepare(
                'INSERT INTO academic_period_transitions
                 (closed_period_id,activated_period_id,performed_by,performed_at)
                 VALUES(:closed,:activated,:actor,UTC_TIMESTAMP())'
            );
            $transition->execute(['closed' => $active['id'], 'activated' => $planned['id'], 'actor' => $actor]);
            $transitionId = (int) $db->lastInsertId();
            $this->audit($db, $actor, 'academic_period_closed', 'period', (int) $active['id'], [
                'activated_period_id' => (int) $planned['id'],
                'projects_preserved' => $projectCount,
                'transition_id' => $transitionId,
            ]);
            $this->audit($db, $actor, 'academic_period_activated', 'period', (int) $planned['id'], [
                'closed_period_id' => (int) $active['id'],
                'manual_transition' => true,
                'transition_id' => $transitionId,
            ]);

            return ['closed' => $active['name'], 'activated' => $planned['name'], 'projects' => $projectCount, 'transition_id' => $transitionId];
        });
    }

    public function reverseTransition(int $transitionId, int $actor): array
    {
        if ($transitionId < 1) throw new InvalidArgumentException('La transición académica seleccionada no es válida.');

        return Database::transaction(function (PDO $db) use ($transitionId, $actor): array {
            $statement = $db->prepare('SELECT * FROM academic_period_transitions WHERE id=:id FOR UPDATE');
            $statement->execute(['id' => $transitionId]);
            $transition = $statement->fetch();
            if (!$transition || $transition['reverted_at'] !== null) {
                throw new InvalidArgumentException('No es posible revertir esta transición porque el estado de los períodos cambió.');
            }
            if ((int) $transition['performed_by'] !== $actor) {
                throw new InvalidArgumentException('Solo el administrador que realizó el cierre puede revertirlo.');
            }
            $settings = (new SystemSettingModel())->all();
            $hours = max(1, (int)($settings['academic_period_reversal_hours'] ?? 24));
            $validWindow = $db->prepare('SELECT performed_at>=UTC_TIMESTAMP()-INTERVAL ' . $hours . ' HOUR FROM academic_period_transitions WHERE id=:id');
            $validWindow->execute(['id' => $transitionId]);
            if (!(bool) $validWindow->fetchColumn()) {
                throw new InvalidArgumentException('El plazo de ' . $hours . ' horas para revertir el cierre ha finalizado.');
            }
            $latestId = (int) $db->query('SELECT id FROM academic_period_transitions ORDER BY performed_at DESC,id DESC LIMIT 1 FOR UPDATE')->fetchColumn();
            if ($latestId !== $transitionId) {
                throw new InvalidArgumentException('No es posible revertir esta transición porque existe una promoción posterior.');
            }

            $periods = $db->prepare(
                'SELECT id,name,status FROM academic_periods
                 WHERE id IN (:closed,:activated) ORDER BY id FOR UPDATE'
            );
            $periods->execute(['closed' => $transition['closed_period_id'], 'activated' => $transition['activated_period_id']]);
            $periodRows = [];
            foreach ($periods->fetchAll() as $period) $periodRows[(int) $period['id']] = $period;
            $closed = $periodRows[(int) $transition['closed_period_id']] ?? null;
            $activated = $periodRows[(int) $transition['activated_period_id']] ?? null;
            if (!$closed || !$activated || $closed['status'] !== 'closed' || $activated['status'] !== 'active') {
                throw new InvalidArgumentException('No es posible revertir esta transición porque el estado de los períodos cambió.');
            }
            $activeIds = $db->query("SELECT id FROM academic_periods WHERE status='active' FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            if (count($activeIds) !== 1 || (int) $activeIds[0] !== (int) $activated['id']) {
                throw new InvalidArgumentException('No es posible revertir esta transición porque el estado de los períodos cambió.');
            }

            $activity = $this->academicActivityAfter($db, (int) $activated['id'], (string) $transition['performed_at']);
            if ($activity !== null) {
                throw new InvalidArgumentException('No es posible revertir el cierre porque ya existe actividad académica en el período actual. ' . $activity);
            }

            $reopen = $db->prepare("UPDATE academic_periods SET status='active' WHERE id=:id AND status='closed'");
            $reopen->execute(['id' => $closed['id']]);
            $replan = $db->prepare("UPDATE academic_periods SET status='planned' WHERE id=:id AND status='active'");
            $replan->execute(['id' => $activated['id']]);
            if ($reopen->rowCount() !== 1 || $replan->rowCount() !== 1) {
                throw new RuntimeException('No fue posible restaurar de forma íntegra los estados de los períodos.');
            }
            $mark = $db->prepare(
                'UPDATE academic_period_transitions
                 SET reverted_by=:actor,reverted_at=UTC_TIMESTAMP()
                 WHERE id=:id AND reverted_at IS NULL'
            );
            $mark->execute(['actor' => $actor, 'id' => $transitionId]);
            if ($mark->rowCount() !== 1) {
                throw new InvalidArgumentException('Esta transición ya fue revertida.');
            }
            $this->audit($db, $actor, 'academic_period_closure_reverted', 'period', (int) $closed['id'], [
                'name' => (string) $closed['name'],
                'transition_id' => $transitionId,
                'reopened_period_id' => (int) $closed['id'],
                'planned_period_id' => (int) $activated['id'],
                'planned_period_name' => (string) $activated['name'],
                'technical_reason' => 'Reversión administrativa dentro de la ventana autorizada de ' . (new SystemSettingModel())->retentionDays('academic_period_reversal_hours') . ' horas y sin actividad académica posterior.',
            ]);

            return ['reopened' => $closed['name'], 'planned' => $activated['name'], 'transition_id' => $transitionId];
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
            $this->audit($db, $actor, 'academic_period_plan_deleted', 'period', $id, ['name' => $planned['name'], 'starts_on' => $planned['starts_on'] ?? null, 'ends_on' => $planned['ends_on'] ?? null]);
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
        $overlap = $db->prepare("SELECT name FROM academic_periods WHERE id<>:id AND starts_on<=:end AND ends_on>=:start LIMIT 1 FOR UPDATE");
        $overlap->execute(['id' => $id, 'start' => $start, 'end' => $end]);
        $overlappingPeriod = $overlap->fetchColumn();
        if ($overlappingPeriod !== false) {
            throw new InvalidArgumentException('Las fechas se superponen con ' . $overlappingPeriod . '.');
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
            $this->audit($db, $actor, 'academic_period_plan_updated', 'period', $id, ['name' => $name, 'previous_starts_on' => $planned['starts_on'], 'previous_ends_on' => $planned['ends_on'], 'starts_on' => $start, 'ends_on' => $end]);
            return;
        }
        $statement = $db->prepare(
            "INSERT INTO academic_periods(code,name,starts_on,ends_on,status)
             VALUES(:code,:name,:start,:end,'planned')"
        );
        $statement->execute(['code' => $code, 'name' => $name, 'start' => $start, 'end' => $end]);
        $this->audit($db, $actor, 'academic_period_planned', 'period', (int) $db->lastInsertId(), ['name' => $name, 'starts_on' => $start, 'ends_on' => $end]);
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
        $current = $this->catalogRecord($db, 'project_types', $id, 'El tipo de proyecto no es válido.');
        if ($action !== 'save' && !$current) throw new InvalidArgumentException('El tipo de proyecto no es válido.');
        if ($action === 'delete') {
            $count = $db->prepare('SELECT COUNT(*) FROM projects WHERE project_type_id=:id');
            $count->execute(['id' => $id]);
            if ((int) $count->fetchColumn() > 0) throw new InvalidArgumentException('No se puede eliminar un tipo de proyecto que tiene proyectos asociados.');
            $db->prepare('DELETE FROM project_types WHERE id=:id')->execute(['id' => $id]);
            $this->audit($db, $actor, 'academic_type_deleted', 'type', $id, ['name' => (string) $current['name']]);
            return;
        }
        if (in_array($action, ['activate', 'deactivate'], true)) {
            if ($id < 1) throw new InvalidArgumentException('El tipo de proyecto no es válido.');
            $active = $action === 'activate' ? 1 : 0;
            $db->prepare('UPDATE project_types SET is_active=:active WHERE id=:id')->execute(['active' => $active, 'id' => $id]);
            $this->audit($db, $actor, $active ? 'academic_type_activated' : 'academic_type_deactivated', 'type', $id);
            return;
        }

        $name = trim((string) ($values['name'] ?? ''));
        if (mb_strlen($name) < 3) throw new InvalidArgumentException('Ingresa un nombre válido.');
        $created = $id < 1;
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
        $this->audit($db, $actor, $created ? 'academic_type_created' : 'academic_type_updated', 'type', $id, ['name' => $name]);
    }

    private function saveMaterialCatalog(PDO $db, string $entity, array $values, int $actor): void
    {
        $action = (string) ($values['action'] ?? 'save');
        $created = (int) ($values['id'] ?? 0) < 1;
        $result = (new SupportMaterialModel())->mutateCatalog($db, $entity, $values, $actor);
        $prefix = $entity === 'material_type' ? 'academic_material_type_' : 'academic_keyword_';
        $event = $action === 'save' ? ($created ? 'created' : 'updated') : $action . 'd';
        if ($action === 'activate') $event = 'activated';
        if ($action === 'deactivate') $event = 'deactivated';
        $this->audit($db, $actor, $prefix . $event, $entity, (int) $result['id'], [
            'catalog_id' => (int) $result['id'],
            'name' => (string) $result['name'],
            'previous' => $result['previous'],
        ]);
    }

    private function catalogRecord(PDO $db, string $table, int $id, string $message): ?array
    {
        if ($id < 1) return null;
        $statement = $db->prepare("SELECT * FROM $table WHERE id=:id FOR UPDATE");
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();
        if (!$record) throw new InvalidArgumentException($message);
        return $record;
    }

    private function reversalAvailability(PDO $db, int $actor): ?array
    {
        $settings = (new SystemSettingModel())->all();
        $hours = max(1, (int)($settings['academic_period_reversal_hours'] ?? 24));
        $statement = $db->prepare(
            "SELECT transition.*,closed_period.name closed_period_name,active_period.name activated_period_name,
                    DATE_ADD(transition.performed_at,INTERVAL " . $hours . " HOUR) expires_at
             FROM academic_period_transitions transition
             JOIN academic_periods closed_period ON closed_period.id=transition.closed_period_id
             JOIN academic_periods active_period ON active_period.id=transition.activated_period_id
             ORDER BY transition.performed_at DESC,transition.id DESC
             LIMIT 1"
        );
        $statement->execute();
        $transition = $statement->fetch();
        if (!$transition || $transition['reverted_at'] !== null || (int) $transition['performed_by'] !== $actor) return null;
        if (strtotime((string) $transition['expires_at'] . ' UTC') < time()) return null;

        $available = $transition['closed_period_id'] !== $transition['activated_period_id'];
        $reason = null;
        $periodState = $db->prepare(
            "SELECT COUNT(*) FROM academic_periods
             WHERE (id=:closed AND status='closed') OR (id=:active AND status='active')"
        );
        $periodState->execute(['closed' => $transition['closed_period_id'], 'active' => $transition['activated_period_id']]);
        if ((int) $periodState->fetchColumn() !== 2) {
            $available = false;
            $reason = 'Reversión no disponible: el estado de los períodos cambió.';
        } else {
            $activity = $this->academicActivityAfter($db, (int) $transition['activated_period_id'], (string) $transition['performed_at']);
            if ($activity !== null) {
                $available = false;
                $reason = 'Reversión no disponible: ya existe actividad académica en el período activo. ' . $activity;
            }
        }
        $expires = (new DateTimeImmutable((string) $transition['expires_at'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('America/Guayaquil'));

        return [
            'id' => (int) $transition['id'],
            'closed_period_id' => (int) $transition['closed_period_id'],
            'closed_period_name' => (string) $transition['closed_period_name'],
            'activated_period_id' => (int) $transition['activated_period_id'],
            'activated_period_name' => (string) $transition['activated_period_name'],
            'available' => $available,
            'reason' => $reason,
            'expires_at' => (string) $transition['expires_at'],
            'expires_label' => $expires->format('d/m/Y H:i'),
        ];
    }

    private function academicActivityAfter(PDO $db, int $periodId, string $performedAt): ?string
    {
        $checks = [
            ['SELECT 1 FROM projects p WHERE p.academic_period_id=? AND p.created_at>? LIMIT 1', 'Se encontraron proyectos creados después del cierre.', 1],
            ["SELECT 1 FROM project_participants item JOIN projects p ON p.id=item.project_id WHERE p.academic_period_id=? AND (item.assigned_at>? OR item.removed_at>?) LIMIT 1", 'Se encontraron asignaciones de participantes posteriores al cierre.', 2],
            ['SELECT 1 FROM project_deliveries item JOIN projects p ON p.id=item.project_id WHERE p.academic_period_id=? AND item.submitted_at>? LIMIT 1', 'Se encontraron entregas posteriores al cierre.', 1],
            ['SELECT 1 FROM project_files item JOIN projects p ON p.id=item.project_id WHERE p.academic_period_id=? AND (item.created_at>? OR item.deleted_at>?) LIMIT 1', 'Se encontró actividad documental posterior al cierre.', 2],
            ['SELECT 1 FROM project_observations item JOIN projects p ON p.id=item.project_id WHERE p.academic_period_id=? AND (item.created_at>? OR item.resolved_at>?) LIMIT 1', 'Se encontraron observaciones o revisiones posteriores al cierre.', 2],
            ['SELECT 1 FROM observation_responses item JOIN project_observations observation ON observation.id=item.observation_id JOIN projects p ON p.id=observation.project_id WHERE p.academic_period_id=? AND item.created_at>? LIMIT 1', 'Se encontraron respuestas a observaciones posteriores al cierre.', 1],
            ['SELECT 1 FROM project_comments item JOIN projects p ON p.id=item.project_id WHERE p.academic_period_id=? AND (item.created_at>? OR item.updated_at>? OR item.deleted_at>?) LIMIT 1', 'Se encontraron comentarios académicos posteriores al cierre.', 3],
            ['SELECT 1 FROM project_stages item JOIN projects p ON p.id=item.project_id WHERE p.academic_period_id=? AND item.completed_at>? LIMIT 1', 'Se encontraron avances de etapa posteriores al cierre.', 1],
            ['SELECT 1 FROM project_events item JOIN projects p ON p.id=item.project_id WHERE p.academic_period_id=? AND item.created_at>? LIMIT 1', 'Se encontraron eventos académicos posteriores al cierre.', 1],
            ['SELECT 1 FROM project_audit_log item JOIN projects p ON p.id=item.project_id WHERE p.academic_period_id=? AND item.created_at>? LIMIT 1', 'Se encontraron cambios académicos posteriores al cierre.', 1],
            ['SELECT 1 FROM projects p WHERE p.academic_period_id=? AND (p.updated_at>? OR p.published_at>?) LIMIT 1', 'Se encontraron cambios o publicaciones de proyectos posteriores al cierre.', 2],
        ];
        foreach ($checks as [$sql, $reason, $dateParameterCount]) {
            $statement = $db->prepare($sql);
            $statement->execute(array_merge([$periodId], array_fill(0, $dateParameterCount, $performedAt)));
            if ($statement->fetchColumn() !== false) return $reason;
        }
        return null;
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
        $element = (string) ($details['name'] ?? '');
        if ($element === '' && $id) {
            $tables = ['period' => 'academic_periods', 'type' => 'project_types'];
            $table = $tables[$type] ?? 'project_types';
            $statement = $db->prepare("SELECT name FROM $table WHERE id=:id");
            $statement->execute(['id' => $id]);
            $element = (string) ($statement->fetchColumn() ?: ($type === 'period' ? 'Período académico' : 'Tipo de proyecto'));
        }
        $labels = [
            'academic_period_started' => 'Creó ' . $element,
            'academic_period_planned' => 'Planificó ' . $element,
            'academic_period_plan_updated' => 'Editó la planificación de ' . $element,
            'academic_period_plan_deleted' => 'Eliminó la planificación de ' . $element,
            'academic_period_closed' => 'Cerró ' . $element,
            'academic_period_activated' => 'Activó ' . $element,
            'academic_period_closure_reverted' => 'Revirtió el cierre de ' . $element,
            'academic_type_created' => 'Creó el tipo de proyecto ' . $element,
            'academic_type_updated' => 'Editó el tipo de proyecto ' . $element,
            'academic_type_activated' => 'Activó el tipo de proyecto ' . $element,
            'academic_type_deactivated' => 'Desactivó el tipo de proyecto ' . $element,
            'academic_type_deleted' => 'Eliminó el tipo de proyecto ' . $element,
            'academic_material_type_created' => 'Creó el tipo de material ' . $element,
            'academic_material_type_updated' => 'Editó el tipo de material ' . $element,
            'academic_material_type_activated' => 'Activó el tipo de material ' . $element,
            'academic_material_type_deactivated' => 'Desactivó el tipo de material ' . $element,
            'academic_material_type_deleted' => 'Eliminó el tipo de material ' . $element,
            'academic_keyword_created' => 'Creó la palabra clave ' . $element,
            'academic_keyword_updated' => 'Editó la palabra clave ' . $element,
            'academic_keyword_activated' => 'Activó la palabra clave ' . $element,
            'academic_keyword_deactivated' => 'Desactivó la palabra clave ' . $element,
            'academic_keyword_deleted' => 'Eliminó la palabra clave ' . $element,
        ];
        (new AdminActivityService($db))->record($actor,$action,$labels[$action]??$action,'Gestión académica',$type,$id,$element,'correct',$details);
    }
}
