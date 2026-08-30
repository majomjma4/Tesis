<?php

declare(strict_types=1);

/** Caso de uso transaccional para devolver una revisión a desarrollo con observaciones reales. */
final class ProjectReviewService
{
    private const MIN_OBSERVATION_LENGTH = 5;
    private const MAX_OBSERVATION_LENGTH = 2000;

    /** Contrato visual de la acción excepcional de revisión; no duplica la política de estados. */
    public function availableCorrectionAction(array $project): ?array
    {
        if ((string) ($project['status'] ?? '') !== 'under_review') return null;
        $labels = project_academic_labels('development');
        $deliveries = (array) ($project['deliveries'] ?? []);
        usort($deliveries, static fn (array $left, array $right): int => ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0)));
        return [
            'action' => 'request_corrections',
            'target' => 'development',
            'label' => 'Solicitar correcciones',
            'dialog_title' => 'Solicitar correcciones',
            'icon' => 'fa-comment-dots',
            'effect' => 'Se registrarán observaciones académicas y el proyecto volverá a En desarrollo.',
            'warning' => 'Este cambio modifica el flujo académico del proyecto y quedará registrado en el historial.',
            'current_label' => project_academic_labels('under_review')['status'],
            'target_label' => $labels['status'],
            'target_stage' => $labels['stage'],
            'reason_required' => false,
            'structured_observations' => true,
            'requirements_met' => true,
            'requirements' => [],
            'delivery_id' => isset($deliveries[0]['id']) ? (int) $deliveries[0]['id'] : null,
            'files' => array_values(array_map(static fn (array $file): array => [
                'id' => (int) ($file['id'] ?? 0),
                'name' => (string) ($file['original_name'] ?? $file['name'] ?? 'Archivo'),
            ], array_filter((array) ($project['files'] ?? $project['presentation_files'] ?? []), static fn (array $file): bool => (int) ($file['id'] ?? 0) > 0))),
        ];
    }

    /** @param list<string|array<string,mixed>> $observations */
    public function requestCorrections(
        int $projectId,
        string $expectedStatus,
        ?int $deliveryId,
        array $observations,
        int $actor,
        string $context = 'academic_management'
    ): array {
        if ($projectId < 1 || $actor < 1) {
            throw new ProjectStatusTransitionException('La solicitud de correcciones no es válida.');
        }
        if ($expectedStatus !== 'under_review') {
            throw new ProjectStatusTransitionException('El estado esperado para solicitar correcciones debe ser En revisión.');
        }
        if ($context !== 'academic_management') {
            throw new ProjectStatusTransitionException('La solicitud de correcciones no está disponible en este contexto.', 403);
        }
        return Database::transaction(fn (PDO $db): array => $this->requestCorrectionsInTransaction(
            $db, $projectId, $expectedStatus, $deliveryId, $observations, $actor, $context
        ));
    }

    /** Ejecuta el caso de uso dentro de una transacción ya abierta; permite pruebas aisladas con rollback. */
    public function requestCorrectionsInTransaction(
        PDO $db,
        int $projectId,
        string $expectedStatus,
        ?int $deliveryId,
        array $observations,
        int $actor,
        string $context = 'academic_management'
    ): array {
        if ($projectId < 1 || $actor < 1) throw new ProjectStatusTransitionException('La solicitud de correcciones no es válida.');
        if ($expectedStatus !== 'under_review') throw new ProjectStatusTransitionException('El estado esperado para solicitar correcciones debe ser En revisión.');
        if ($context !== 'academic_management') throw new ProjectStatusTransitionException('La solicitud de correcciones no está disponible en este contexto.', 403);
        $normalized = $this->normalizeObservations($observations);

            $projectQuery = $db->prepare(
                'SELECT id,code,title,status,deleted_at FROM projects WHERE id=:id FOR UPDATE'
            );
            $projectQuery->execute(['id' => $projectId]);
            $project = $projectQuery->fetch();
            if (!$project || !empty($project['deleted_at'])) {
                throw new ProjectStatusTransitionException('El proyecto no existe o fue eliminado.', 404);
            }
            if ((string) $project['status'] !== $expectedStatus) {
                throw new ProjectStatusTransitionException(
                    'El proyecto cambió de estado mientras realizabas la revisión. Actualiza la pantalla e inténtalo nuevamente.',
                    409
                );
            }

            $delivery = $this->lockDelivery($db, $projectId, $deliveryId);
            $insert = $db->prepare(
                "INSERT INTO project_observations
                 (project_id,delivery_id,file_id,file_checksum_sha256,author_id,category,location_reference,body,status)
                 VALUES (:project_id,:delivery_id,:file_id,:checksum,:author_id,:category,:location_reference,:body,'pending')"
            );
            $observationIds = [];
            foreach ($normalized as $observation) {
                $this->assertFileBelongsToProject($db, $projectId, $observation['file_id']);
                $checksum = null;
                if ($observation['file_id'] !== null) {
                    $fileQuery = $db->prepare('SELECT checksum_sha256 FROM project_files WHERE id=:file AND project_id=:project AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');
                    $fileQuery->execute(['file'=>(int)$observation['file_id'], 'project'=>$projectId]);
                    $checksum = $fileQuery->fetchColumn() ?: null;
                }
                $insert->execute([
                    'project_id' => $projectId,
                    'delivery_id' => $delivery['id'] ?? null,
                    'file_id' => $observation['file_id'],
                    'checksum' => $checksum,
                    'author_id' => $actor,
                    'category' => $observation['category'],
                    'location_reference' => $observation['location_reference'],
                    'body' => $observation['body'],
                ]);
                $observationIds[] = (int) $db->lastInsertId();
            }

            if ($delivery !== null) $this->markDeliveryCorrectionsRequested($db, (int) $delivery['id']);

            $update = $db->prepare("UPDATE projects SET status='development' WHERE id=:id AND status=:expected");
            $update->execute(['id' => $projectId, 'expected' => $expectedStatus]);
            if ($update->rowCount() !== 1) {
                throw new ProjectStatusTransitionException(
                    'El proyecto cambió de estado mientras realizabas la revisión. Actualiza la pantalla e inténtalo nuevamente.',
                    409
                );
            }

            $count = count($observationIds);
            $auditId = (new ProjectAuditService($db))->record(
                $projectId,
                $actor,
                'project_corrections_requested',
                'project_review',
                $delivery !== null ? (int) $delivery['id'] : $projectId,
                ['status' => 'under_review'],
                [
                    'status' => 'development',
                    'delivery_id' => $delivery !== null ? (int) $delivery['id'] : null,
                    'observation_count' => $count,
                    'observation_ids' => $observationIds,
                    'context' => $context,
                ],
                'Correcciones académicas solicitadas.'
            );
            $this->notifyAuthors($db, $projectId, $count, $auditId);

            $labels = project_academic_labels('development');
            return [
                'id' => $projectId,
                'previous_status' => 'under_review',
                'status' => 'development',
                'status_label' => $labels['status'],
                'stage_label' => $labels['stage'],
                'delivery_id' => $delivery !== null ? (int) $delivery['id'] : null,
                'observation_ids' => $observationIds,
                'observation_count' => $count,
                'review_situation' => (new ProjectReviewSituationService())->forProject($projectId, $db),
            ];
    }

    /** @param list<string|array<string,mixed>> $observations */
    private function normalizeObservations(array $observations): array
    {
        if ($observations === []) throw new ProjectStatusTransitionException('Registra al menos una observación académica.');
        $normalized = [];
        $seen = [];
        foreach ($observations as $item) {
            $item = is_array($item) ? $item : ['body' => $item];
            $body = trim((string) ($item['body'] ?? $item['content'] ?? ''));
            $length = mb_strlen($body);
            if ($length < self::MIN_OBSERVATION_LENGTH || $length > self::MAX_OBSERVATION_LENGTH) {
                throw new ProjectStatusTransitionException('Cada observación debe contener entre 5 y 2000 caracteres.');
            }
            if (isset($seen[$body])) throw new ProjectStatusTransitionException('No incluyas observaciones duplicadas en la misma revisión.');
            $seen[$body] = true;
            $category = trim((string) ($item['category'] ?? 'General'));
            $location = trim((string) ($item['location_reference'] ?? ''));
            if ($category === '' || mb_strlen($category) > 60 || mb_strlen($location) > 180) {
                throw new ProjectStatusTransitionException('La categoría o referencia de una observación no es válida.');
            }
            $normalized[] = [
                'body' => $body,
                'category' => $category,
                'location_reference' => $location !== '' ? $location : null,
                'file_id' => (int) ($item['file_id'] ?? 0) ?: null,
            ];
        }
        return $normalized;
    }

    private function lockDelivery(PDO $db, int $projectId, ?int $deliveryId): ?array
    {
        if ($deliveryId === null || $deliveryId < 1) return null;
        $query = $db->prepare('SELECT id,project_id,status FROM project_deliveries WHERE id=:id FOR UPDATE');
        $query->execute(['id' => $deliveryId]);
        $delivery = $query->fetch();
        if (!$delivery || (int) $delivery['project_id'] !== $projectId) {
            throw new ProjectStatusTransitionException('La entrega seleccionada no pertenece al proyecto.');
        }
        return $delivery;
    }

    private function assertFileBelongsToProject(PDO $db, int $projectId, ?int $fileId): void
    {
        if ($fileId === null) return;
        $query = $db->prepare('SELECT 1 FROM project_files WHERE id=:id AND project_id=:project_id AND deleted_at IS NULL AND purged_at IS NULL');
        $query->execute(['id' => $fileId, 'project_id' => $projectId]);
        if (!$query->fetchColumn()) throw new ProjectStatusTransitionException('El archivo asociado a una observación no pertenece al proyecto.');
    }

    private function markDeliveryCorrectionsRequested(PDO $db, int $deliveryId): void
    {
        $statement = $db->prepare('UPDATE project_deliveries SET status=:status WHERE id=:id');
        $statement->execute(['status' => ProjectDeliveryStatusService::correctionsRequested($db), 'id' => $deliveryId]);
    }

    private function notifyAuthors(PDO $db, int $projectId, int $count, int $auditId): void
    {
        $metadata = json_encode([
            'purpose' => 'project_corrections_requested',
            'context' => 'academic_management',
            'observation_count' => $count,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $message = $count === 1
            ? 'Se ha registrado una observación en la revisión. Por favor, revísala antes de subir una nueva versión.'
            : "Se han registrado {$count} observaciones en la revisión. Por favor, revísalas antes de subir una nueva versión.";
        $insert = $db->prepare(
            "INSERT IGNORE INTO notifications
             (user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key)
             SELECT DISTINCT pp.user_id,:project_id,'observation','Nuevas observaciones de revisión',:message,:url,'Revisar observaciones',:metadata,:deduplication_key
             FROM project_participants pp INNER JOIN student_profiles sp ON sp.user_id=pp.user_id
             INNER JOIN users u ON u.id=pp.user_id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
             WHERE pp.project_id=:participant_project AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL"
        );
        $insert->execute([
            'project_id' => $projectId,
            'message' => $message,
            'url' => route('project-detail') . '&id=' . $projectId . '&tab=information',
            'metadata' => $metadata,
            'deduplication_key' => 'project-corrections-requested-' . $auditId,
            'participant_project' => $projectId,
        ]);
    }
}
