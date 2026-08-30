<?php

declare(strict_types=1);

/** Deriva la situación de revisión sin duplicarla en la tabla projects. */
final class ProjectReviewSituationService
{
    public static function normalizeFilter(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['pending', 'none', 'addressed'], true) ? $value : '';
    }

    /** Agregación exclusiva por proyecto; nunca cuenta una vez por observación. */
    public function aggregate(?PDO $connection = null, bool $activeOnly = false): array
    {
        $db = $connection ?? Database::connection();
        $activeCondition = $activeOnly ? " AND p.status<>'published'" : '';
        $pendingCondition = $this->actionablePendingObservationCondition('review_observation');
        $row = $db->query(
            "SELECT
             COALESCE(SUM(COALESCE(review.pending_count,0)>0),0) pending,
             COALESCE(SUM(COALESCE(review.pending_count,0)=0 AND COALESCE(review.addressed_count,0)>0),0) addressed,
             COALESCE(SUM(COALESCE(review.observation_count,0)=0),0) none
             FROM projects p
             LEFT JOIN (
               SELECT project_id,COUNT(*) observation_count,
                 SUM(CASE WHEN {$pendingCondition} THEN 1 ELSE 0 END) pending_count,
                 SUM(status IN ('addressed','resolved')) addressed_count
               FROM project_observations review_observation GROUP BY project_id
             ) review ON review.project_id=p.id
             WHERE p.deleted_at IS NULL{$activeCondition}"
        )->fetch() ?: [];
        return ['pending'=>(int)($row['pending']??0),'addressed'=>(int)($row['addressed']??0),'none'=>(int)($row['none']??0)];
    }

    /** @param list<int> $projectIds @return array<int,array<string,mixed>> */
    public function forProjects(array $projectIds, ?PDO $connection = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $db = $connection ?? Database::connection();
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $pendingCondition = $this->actionablePendingObservationCondition('o');
        $statement = $db->prepare(
            "SELECT p.id,
             COUNT(DISTINCT CASE WHEN {$pendingCondition} THEN o.id END) pending_count,
             COUNT(DISTINCT CASE WHEN o.status IN ('addressed','resolved') THEN o.id END)>0 has_addressed,
             MAX(CASE WHEN a.action='project_corrections_requested' THEN a.created_at END) corrections_at,
             MAX(d.submitted_at) latest_delivery_at
             FROM projects p
             LEFT JOIN project_observations o ON o.project_id=p.id
             LEFT JOIN project_audit_log a ON a.project_id=p.id AND a.action='project_corrections_requested'
             LEFT JOIN project_deliveries d ON d.project_id=p.id
             WHERE p.id IN ($marks) GROUP BY p.id"
        );
        $statement->execute($ids);
        $result = [];
        foreach ($statement->fetchAll() as $row) $result[(int) $row['id']] = $this->normalizeRow($row);
        foreach ($ids as $id) $result[$id] ??= $this->emptySituation();
        return $result;
    }

    /**
     * Fuente única de la situación de seguimiento docente.
     * La entrada ya contiene asignaciones reales; tribunal/jury nunca adquiere
     * capacidad de revisión documental por el mero hecho de estar asignado.
     * @param list<array<string,mixed>> $projects
     * @param array<int,list<string>> $relationsByProject
     * @return array<int,array<string,mixed>>
     */
    public function teacherSituations(array $projects, array $relationsByProject, ?PDO $connection = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $projects), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $db = $connection ?? Database::connection();
        $marks = implode(',', array_fill(0, count($ids), '?'));

        $deliveryQuery = $db->prepare("SELECT d.project_id,d.id,d.version_number,d.status,d.submitted_at
            FROM project_deliveries d
            WHERE d.project_id IN ($marks)
              AND NOT EXISTS (SELECT 1 FROM project_deliveries newer WHERE newer.project_id=d.project_id
                AND (newer.submitted_at>d.submitted_at OR (newer.submitted_at=d.submitted_at AND newer.id>d.id)))");
        $deliveryQuery->execute($ids);
        $latestDeliveries = [];
        foreach ($deliveryQuery->fetchAll(PDO::FETCH_ASSOC) as $row) $latestDeliveries[(int)$row['project_id']] = $row;

        $observationQuery = $db->prepare("SELECT o.id,o.project_id,o.file_id,o.status,o.created_at,
                EXISTS(SELECT 1 FROM observation_responses r
                 INNER JOIN project_participants student ON student.project_id=o.project_id AND student.user_id=r.author_id
                   AND student.role_code='student' AND student.status='active' AND student.removed_at IS NULL
                 WHERE r.observation_id=o.id AND r.created_at>=o.created_at) has_student_response,
                EXISTS(SELECT 1 FROM project_file_version_addressed_observations link
                  INNER JOIN project_file_version_changes change_log ON change_log.id=link.change_id
                  WHERE link.observation_id=o.id AND change_log.project_id=o.project_id
                    AND change_log.file_id=o.file_id AND change_log.changed_at>=o.created_at) has_linked_version,
                (SELECT MAX(change_log.changed_at) FROM project_file_version_changes change_log
                  WHERE change_log.project_id=o.project_id AND change_log.file_id=o.file_id) latest_file_change_at
            FROM project_observations o WHERE o.project_id IN ($marks)
            ORDER BY o.project_id,o.created_at,o.id");
        $observationQuery->execute($ids);
        $observations = [];
        foreach ($observationQuery->fetchAll(PDO::FETCH_ASSOC) as $row) $observations[(int)$row['project_id']][] = $row;

        $situations = [];
        foreach ($projects as $project) {
            $id = (int)($project['id'] ?? 0);
            if ($id < 1) continue;
            $status = strtolower(trim((string)($project['status'] ?? '')));
            $relations = array_map('strtolower', array_map('strval', $relationsByProject[$id] ?? []));
            $canReview = in_array('tutor', $relations, true) || in_array('cotutor', $relations, true);
            $latestDelivery = $latestDeliveries[$id] ?? null;
            $pendingStudent = false;
            // Cada observación pendiente se resuelve contra su propia fecha y,
            // cuando existe, contra el mismo archivo. Una entrega posterior es
            // evidencia de reenvío del expediente completo: el caso de uso de
            // envío bloquea las correcciones documentales incompletas.
            foreach ($observations[$id] ?? [] as $observation) {
                if ((string)$observation['status'] !== 'pending') continue;
                $fileVersionAfterObservation = !empty($observation['has_linked_version'])
                    || (!empty($observation['latest_file_change_at'])
                        && strtotime((string)$observation['latest_file_change_at']) > strtotime((string)$observation['created_at']));
                $deliveryAfterObservation = $latestDelivery !== null
                    && strtotime((string)$latestDelivery['submitted_at']) > strtotime((string)$observation['created_at']);
                // La respuesta textual quita la espera del estudiante, pero no
                // genera por sí sola una validación docente: no hay capacidad ni
                // transición de workflow que permita aprobarla aisladamente.
                $acted = !empty($observation['has_student_response']) || $fileVersionAfterObservation || $deliveryAfterObservation;
                if (!$acted) $pendingStudent = true;
            }

            // tribunal_approved no es terminal: para tesis aún queda el paso de
            // publicación. La defensa tampoco expone evaluaciones individuales;
            // sólo un gestor de titulacion puede registrar el resultado global.
            $terminal = in_array($status, ['completed','published','closed','withdrawn'], true);
            $situation = match (true) {
                $terminal => $this->teacherSituation('completed', 'Proceso finalizado.', false, 'none'),
                $status === 'defense' => $this->teacherSituation('waiting_process', 'La defensa está en curso, pero el sistema no registra una evaluación individual pendiente para este docente.', false, 'process'),
                $status === 'tribunal_approved' => $this->teacherSituation('waiting_process', 'El tribunal ya registró el resultado; el proyecto espera el paso de publicación.', false, 'process'),
                !$canReview => $this->teacherSituation('waiting_process', 'No existe capacidad docente de revisión documental para esta asignación.', false, 'none'),
                $pendingStudent => $this->teacherSituation('waiting_student', 'El estudiante tiene una observación pendiente sin respuesta o nueva evidencia.', false, 'student'),
                $latestDelivery !== null && in_array((string)$latestDelivery['status'], ['submitted','under_review'], true) => $this->teacherSituation('review_required', 'La entrega vigente está pendiente de revisión docente.', true, 'teacher'),
                $status === 'under_review' => $this->teacherSituation('waiting_process', 'El proyecto figura en revisión, pero no existe una entrega vigente que demuestre una unidad docente pendiente.', false, 'process'),
                in_array($status, ['approved'], true) => $this->teacherSituation('waiting_process', 'La revisión académica fue completada; el proyecto espera el siguiente paso del proceso.', false, 'process'),
                default => $this->teacherSituation('waiting_process', 'No hay una acción docente demostrable en este momento.', false, 'none'),
            };
            $situation['review_units'] = $situation['key'] === 'review_required' ? 1 : 0;
            $situations[$id] = $situation;
        }
        return $situations;
    }

    private function teacherSituation(string $key, string $description, bool $attention, string $actor): array
    {
        $labels = [
            'review_required' => 'Revisión docente pendiente',
            'waiting_student' => 'Esperando correcciones del estudiante',
            'waiting_process' => 'En seguimiento',
            'defense_or_tribunal' => 'En tribunal / defensa',
            'completed' => 'Proceso finalizado',
        ];
        return ['key'=>$key,'label'=>$labels[$key] ?? $labels['waiting_process'],'description'=>$description,'requires_attention'=>$attention,'actor'=>$actor];
    }

    /** @return array{has_pending_observations:bool,pending_observations_count:int,has_addressed_observations:bool,latest_corrections_requested_at:?string,has_new_delivery_after_corrections:bool} */
    public function forProject(int $projectId, ?PDO $connection = null): array
    {
        if ($projectId < 1) return $this->emptySituation();
        $db = $connection ?? Database::connection();
        $pendingCondition = $this->actionablePendingObservationCondition('o');
        $statement = $db->prepare(
            "SELECT
             (SELECT COUNT(*) FROM project_observations o WHERE o.project_id=:pending_project AND {$pendingCondition}) pending_count,
             EXISTS(SELECT 1 FROM project_observations WHERE project_id=:addressed_project AND status IN ('addressed','resolved')) has_addressed,
             (SELECT MAX(created_at) FROM project_audit_log WHERE project_id=:audit_project AND action='project_corrections_requested') corrections_at,
             (SELECT MAX(submitted_at) FROM project_deliveries WHERE project_id=:delivery_project) latest_delivery_at"
        );
        $statement->execute([
            'pending_project' => $projectId,
            'addressed_project' => $projectId,
            'audit_project' => $projectId,
            'delivery_project' => $projectId,
        ]);
        $row = $statement->fetch() ?: [];
        return $this->normalizeRow($row);
    }

    public function filterCondition(string $situation, string $projectAlias = 'p'): ?string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $projectAlias)) throw new InvalidArgumentException('Alias de proyecto no válido.');
        $pendingCondition = $this->actionablePendingObservationCondition('review_observation');
        return match ($situation) {
            'pending' => "EXISTS(SELECT 1 FROM project_observations review_observation WHERE review_observation.project_id={$projectAlias}.id AND {$pendingCondition})",
            'none' => "NOT EXISTS(SELECT 1 FROM project_observations review_observation WHERE review_observation.project_id={$projectAlias}.id AND {$pendingCondition})",
            'addressed' => "NOT EXISTS(SELECT 1 FROM project_observations pending_observation WHERE pending_observation.project_id={$projectAlias}.id AND ".$this->actionablePendingObservationCondition('pending_observation').") AND EXISTS(SELECT 1 FROM project_observations addressed_observation WHERE addressed_observation.project_id={$projectAlias}.id AND addressed_observation.status IN ('addressed','resolved'))",
            default => null,
        };
    }

    private function actionablePendingObservationCondition(string $alias): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) throw new InvalidArgumentException('Alias de observaciÃ³n no vÃ¡lido.');
        return "{$alias}.status='pending' AND (
            {$alias}.file_id IS NULL
            OR EXISTS (
                SELECT 1
                FROM project_files current_file
                LEFT JOIN project_file_review_states current_state
                  ON current_state.project_id=current_file.project_id
                 AND current_state.file_id=current_file.id
                 AND current_state.checksum_sha256=current_file.checksum_sha256
                WHERE current_file.project_id={$alias}.project_id
                  AND current_file.id={$alias}.file_id
                  AND current_file.deleted_at IS NULL
                  AND current_file.purged_at IS NULL
                  AND current_file.checksum_sha256={$alias}.file_checksum_sha256
                  AND COALESCE(current_state.status,'development') IN ('development','corrections_requested')
            )
        )";
    }

    private function normalizeRow(array $row): array
    {
        $correctionsAt = !empty($row['corrections_at']) ? (string) $row['corrections_at'] : null;
        $latestDeliveryAt = !empty($row['latest_delivery_at']) ? (string) $row['latest_delivery_at'] : null;
        $pending = (int) ($row['pending_count'] ?? 0);
        return [
            'has_pending_observations' => $pending > 0,
            'pending_observations_count' => $pending,
            'has_addressed_observations' => !empty($row['has_addressed']),
            'latest_corrections_requested_at' => $correctionsAt,
            'has_new_delivery_after_corrections' => $correctionsAt !== null && $latestDeliveryAt !== null
                && strtotime($latestDeliveryAt) > strtotime($correctionsAt),
        ];
    }

    public static function emptySituation(): array
    {
        return [
            'has_pending_observations' => false,
            'pending_observations_count' => 0,
            'has_addressed_observations' => false,
            'latest_corrections_requested_at' => null,
            'has_new_delivery_after_corrections' => false,
        ];
    }
}
