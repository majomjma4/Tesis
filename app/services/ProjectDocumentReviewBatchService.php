<?php

declare(strict_types=1);

/** Confirma una revisión documental completa como una sola unidad atómica. */
final class ProjectDocumentReviewBatchService
{
    private const INPUT_STATUSES = ['under_review', 'approved', 'corrections_requested'];
    private const MIN_OBSERVATION_LENGTH = 5;
    private const MAX_OBSERVATION_LENGTH = 2000;
    private const STALE_MESSAGE = 'Uno o más documentos fueron actualizados mientras realizabas la revisión. Recarga el expediente antes de continuar.';

    public function confirm(int $projectId, string $expectedProjectStatus, array $decisions, int $actor, string $context = 'academic'): array
    {
        return Database::transaction(fn(PDO $db): array => $this->confirmInTransaction(
            $db, $projectId, $expectedProjectStatus, $decisions, $actor, $context
        ));
    }

    /** Disponible para pruebas de integración que revierten su transacción exterior. */
    public function confirmInTransaction(PDO $db, int $projectId, string $expectedProjectStatus, array $decisions, int $actor, string $context = 'academic'): array
    {
        if ($projectId < 1 || $actor < 1 || $expectedProjectStatus === '') {
            throw new ProjectStatusTransitionException('La solicitud de revisión documental no es válida.');
        }
        if ($context !== 'academic') {
            throw new ProjectStatusTransitionException('La revisión documental no está disponible en este contexto.', 403);
        }

        $projectQuery = $db->prepare(
            'SELECT id,code,title,status,tutor_id,deleted_at FROM projects WHERE id=:id FOR UPDATE'
        );
        $projectQuery->execute(['id'=>$projectId]);
        $project = $projectQuery->fetch();
        if (!$project || !empty($project['deleted_at'])) throw new ProjectStatusTransitionException('El proyecto no existe o fue eliminado.', 404);
        if ((string)$project['status'] !== $expectedProjectStatus) {
            throw new ProjectStatusTransitionException('El proyecto cambió de estado mientras realizabas la revisión. Recarga el expediente antes de continuar.', 409);
        }
        if ($expectedProjectStatus !== 'under_review') {
            throw new ProjectStatusTransitionException('El proyecto no se encuentra disponible para revisión documental.');
        }
        if (!(new ProjectCapabilityService())->canReviewDocumentsInTransaction($db, $project, $actor, $context)) {
            throw new ProjectStatusTransitionException('No tienes autorización para revisar los documentos de este proyecto.', 403);
        }

        $normalized = $this->normalizeDecisions($decisions);
        $files = (new ProjectDocumentReviewService($db))->loadFilesInReviewScope($db, $projectId, true);
        if ($files === []) throw new ProjectStatusTransitionException('El proyecto no puede revisarse porque no contiene documentos activos.');
        $byId = [];
        foreach ($files as $file) $byId[(int)$file['id']] = $file;

        foreach ($normalized as $decision) {
            $file = $byId[$decision['file_id']] ?? null;
            if ($file === null) throw new ProjectStatusTransitionException('Uno de los documentos no pertenece al proyecto o ya no está activo.');
            if (!hash_equals(strtolower((string)$file['checksum_sha256']), $decision['expected_checksum'])) {
                throw new ProjectStatusTransitionException(self::STALE_MESSAGE, 409);
            }
        }

        $reviewService = new ProjectDocumentReviewService($db);
        $before = $reviewService->describeCurrentFiles($projectId, $files, true);
        $previousById = [];
        foreach ($before['files'] as $file) $previousById[(int)$file['id']] = (string)$file['document_status'];
        foreach ($files as $file) {
            $fileId = (int)$file['id'];
            if (($previousById[$fileId] ?? 'development') !== 'approved' && !isset($normalized[$fileId])) {
                throw new ProjectStatusTransitionException('Incluye una decisión para cada documento vigente que aún no esté aprobado.');
            }
        }

        $observationInsert = $db->prepare(
            "INSERT INTO project_observations
             (project_id,delivery_id,file_id,file_checksum_sha256,author_id,category,location_reference,selection_anchor,body,status)
             VALUES (:project,NULL,:file,:checksum,:actor,:category,:location,:anchor,:body,'pending')"
        );
        $observationCount = 0;
        $auditDocuments = [];
        foreach ($normalized as $decision) {
            $decisionFile = $byId[$decision['file_id']];
            $decisionChecksum = strtolower((string)$decisionFile['checksum_sha256']);
            foreach ($decision['observations'] as $observation) {
                $observationInsert->execute([
                    'project'=>$projectId, 'file'=>$decision['file_id'], 'checksum'=>$decisionChecksum, 'actor'=>$actor,
                    'category'=>$observation['category'], 'location'=>$observation['location_reference'],
                    'anchor'=>$observation['anchor'] === null ? null : json_encode($observation['anchor'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    'body'=>$observation['body'],
                ]);
                $observationCount++;
            }
            $reviewService->recordCurrentStatus(
                $projectId, $decision['file_id'], $decision['expected_checksum'], $decision['status'], $actor
            );
            $auditDocuments[] = [
                'file_id'=>$decision['file_id'], 'checksum'=>$decision['expected_checksum'],
                'previous_status'=>$previousById[$decision['file_id']] ?? 'development', 'status'=>$decision['status'],
                'observation_count'=>count($decision['observations']),
            ];
        }

        $after = $reviewService->describeCurrentFiles($projectId, $files, true);
        $summary = $after['summary'];
        $finalProjectStatus = $summary['corrections_requested'] > 0
            ? 'development'
            : (!empty($summary['all_active_documents_approved']) ? 'approved' : 'under_review');
        if ($finalProjectStatus !== (string)$project['status']) {
            $update = $db->prepare(
                'UPDATE projects SET status=:status,
                 approved_at=CASE WHEN :completion=1 AND approved_at IS NULL THEN CURRENT_TIMESTAMP ELSE approved_at END
                 WHERE id=:project AND status=:expected'
            );
            $update->execute([
                'status'=>$finalProjectStatus,
                'completion'=>$finalProjectStatus === 'approved' ? 1 : 0,
                'project'=>$projectId,
                'expected'=>$expectedProjectStatus,
            ]);
            if ($update->rowCount() !== 1) throw new ProjectStatusTransitionException('El proyecto cambió de estado mientras realizabas la revisión. Recarga el expediente antes de continuar.', 409);
        }

        $auditId = (new ProjectAuditService($db))->record(
            $projectId, $actor, 'project_document_review_completed', 'project_review', $projectId,
            ['project_status'=>(string)$project['status']],
            [
                'project_status'=>$finalProjectStatus, 'reviewed_documents'=>count($normalized),
                'approved'=>(int)$summary['approved'], 'under_review'=>(int)$summary['under_review'],
                'corrections_requested'=>(int)$summary['corrections_requested'],
                'observation_count'=>$observationCount, 'documents'=>$auditDocuments,
            ],
            'Revisión documental confirmada.'
        );
        if ((int)$summary['corrections_requested'] > 0) {
            $this->notifyStudents($db, $projectId, (int)$summary['corrections_requested'], $auditId);
        } elseif ($finalProjectStatus === 'approved') {
            $labels = project_academic_labels('approved');
            (new ProjectAcademicNotificationService())->finalApproval(
                $db, $projectId, (string)$project['code'], (string)$project['title'],
                'approved', (string)$labels['status'], $auditId
            );
        }

        return [
            'project_id'=>$projectId,
            'project_status'=>$finalProjectStatus,
            'files'=>array_values(array_map(static fn(array $file): array => [
                'file_id'=>(int)$file['id'], 'checksum'=>(string)$file['checksum_sha256'],
                'status'=>(string)$file['document_status'], 'status_label'=>(string)$file['document_status_label'],
            ], $after['files'])),
            'summary'=>$summary,
            'all_active_documents_approved'=>(bool)$summary['all_active_documents_approved'],
            'observations_created'=>$observationCount,
            'message'=>$finalProjectStatus === 'development'
                ? 'La revisión documental fue confirmada y se solicitaron correcciones.'
                : 'La revisión documental fue confirmada correctamente.',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function normalizeDecisions(array $decisions): array
    {
        if ($decisions === []) throw new ProjectStatusTransitionException('Incluye al menos una decisión documental.');
        $normalized = [];
        $seenObservations = [];
        foreach ($decisions as $item) {
            if (!is_array($item)) throw new ProjectStatusTransitionException('Una de las decisiones documentales no es válida.');
            $fileId = (int)($item['file_id'] ?? 0);
            if ($fileId < 1 || isset($normalized[$fileId])) throw new ProjectStatusTransitionException('No incluyas documentos inválidos o repetidos en la revisión.');
            $checksum = strtolower(trim((string)($item['expected_checksum'] ?? '')));
            if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) throw new ProjectStatusTransitionException('La versión esperada de uno de los documentos no es válida.');
            $status = (string)($item['status'] ?? '');
            if (!in_array($status, self::INPUT_STATUSES, true)) throw new ProjectStatusTransitionException('Una de las decisiones documentales contiene un estado no permitido.');
            $observations = [];
            foreach ((array)($item['observations'] ?? []) as $raw) {
                if (!is_array($raw)) $raw = ['body'=>$raw];
                $body = trim((string)($raw['body'] ?? $raw['content'] ?? ''));
                if (mb_strlen($body) < self::MIN_OBSERVATION_LENGTH || mb_strlen($body) > self::MAX_OBSERVATION_LENGTH) {
                    throw new ProjectStatusTransitionException('Cada observación debe contener entre 5 y 2000 caracteres.');
                }
                if (isset($seenObservations[$body])) throw new ProjectStatusTransitionException('No incluyas observaciones duplicadas en la misma revisión.');
                $seenObservations[$body] = true;
                $category = trim((string)($raw['category'] ?? 'General'));
                $location = trim((string)($raw['location_reference'] ?? ''));
                if ($category === '' || mb_strlen($category) > 60 || mb_strlen($location) > 180) {
                    throw new ProjectStatusTransitionException('La categoría o referencia de una observación no es válida.');
                }
                $observations[] = [
                    'body'=>$body, 'category'=>$category, 'location_reference'=>$location !== '' ? $location : null,
                    'anchor'=>$this->normalizeAnchor($raw['anchor'] ?? null),
                ];
            }
            if ($status === 'corrections_requested' && $observations === []) {
                throw new ProjectStatusTransitionException('Debes agregar al menos una observación para solicitar correcciones en este documento.');
            }
            $normalized[$fileId] = [
                'file_id'=>$fileId, 'expected_checksum'=>$checksum, 'status'=>$status, 'observations'=>$observations,
            ];
        }
        return $normalized;
    }

    private function normalizeAnchor(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') return null;
        if (!is_array($raw)) throw new ProjectStatusTransitionException('El ancla de la observación no es válida.');
        $page = filter_var($raw['page_number'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>10000]]);
        $text = trim((string)($raw['selected_text'] ?? ''));
        $rects = $raw['relative_rects'] ?? null;
        if ($page === false || $text === '' || mb_strlen($text) > 500 || !is_array($rects) || count($rects) < 1 || count($rects) > 50) {
            throw new ProjectStatusTransitionException('El ancla de la observación no es válida.');
        }
        $entry = $raw['internal_entry'] ?? $raw['entry_name'] ?? null;
        if ($entry !== null) {
            $entry = trim((string)$entry);
            if ($entry === '') {
                $entry = null;
            } else {
                $cleanEntry = preg_replace('#^[/\\\\]+#', '', str_replace('\\', '/', $entry));
                if ($cleanEntry === null || $cleanEntry === '' || mb_strlen($cleanEntry) > 500 || str_contains($cleanEntry, '<') || preg_match('#^(?:[A-Za-z]:|/|\\\\)#', $cleanEntry)) {
                    throw new ProjectStatusTransitionException('El ancla de la observación no es válida.');
                }
                $entry = $cleanEntry;
            }
        }
        $normalizedRects = [];
        $epsilon = 0.002;
        foreach ($rects as $rect) {
            if (!is_array($rect)) throw new ProjectStatusTransitionException('El ancla de la observación no es válida.');
            $rawValues = [];
            foreach (['left','top','width','height'] as $key) {
                if (!isset($rect[$key]) || !is_numeric($rect[$key])) throw new ProjectStatusTransitionException('El ancla de la observación no es válida.');
                $rawValues[$key] = (float)$rect[$key];
            }
            $rawLeft = $rawValues['left'];
            $rawTop = $rawValues['top'];
            $rawWidth = $rawValues['width'];
            $rawHeight = $rawValues['height'];

            if ($rawLeft < -$epsilon || $rawTop < -$epsilon || $rawWidth <= 0 || $rawHeight <= 0 || ($rawLeft + $rawWidth) > (1.0 + $epsilon) || ($rawTop + $rawHeight) > (1.0 + $epsilon)) {
                throw new ProjectStatusTransitionException('El ancla de la observación no es válida.');
            }

            $left = max(0.0, min(1.0, $rawLeft));
            $top = max(0.0, min(1.0, $rawTop));
            $width = min($rawWidth, 1.0 - $left);
            $height = min($rawHeight, 1.0 - $top);

            if ($width <= 0 || $height <= 0) {
                throw new ProjectStatusTransitionException('El ancla de la observación no es válida.');
            }

            $normalizedRects[] = ['left' => $left, 'top' => $top, 'width' => $width, 'height' => $height];
        }
        return ['selected_text'=>$text, 'page_number'=>$page, 'relative_rects'=>$normalizedRects, 'internal_entry'=>$entry];
    }

    private function notifyStudents(PDO $db, int $projectId, int $documentCount, int $auditId): void
    {
        $message = $documentCount === 1
            ? 'Un documento requiere correcciones. Por favor, revisa las observaciones antes de enviar una nueva versión.'
            : $documentCount . ' documentos requieren correcciones. Por favor, revisa las observaciones antes de enviar nuevas versiones.';
        $metadata = json_encode([
            'purpose'=>'project_document_review', 'context'=>'academic', 'documents_requiring_attention'=>$documentCount,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $insert = $db->prepare(
            "INSERT IGNORE INTO notifications
             (user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key)
             SELECT DISTINCT pp.user_id,:project,'observation','Correcciones solicitadas',:message,:url,'Abrir documentos',:metadata,:dedup
             FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id
             WHERE pp.project_id=:participants AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL
               AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL"
        );
        $insert->execute([
            'project'=>$projectId, 'message'=>$message,
            'url'=>route('project-detail').'&id='.$projectId.'&tab=files', 'metadata'=>$metadata,
            'dedup'=>'project-document-review-'.$auditId, 'participants'=>$projectId,
        ]);
    }
}
