<?php

declare(strict_types=1);

/** Registra una entrega global sin reenviar revisiones ya vigentes ni documentos aprobados. */
final class StudentProjectSubmissionService
{
    public function submitForReview(int $projectId, int $actorId): array
    {
        if ($projectId < 1 || $actorId < 1) throw new StudentProjectSubmissionException('La solicitud no es válida.');
        return Database::transaction(fn (PDO $db): array => $this->submitForReviewInTransaction($db, $projectId, $actorId));
    }

    /** Disponible para pruebas transaccionales; $failAfterStateUpdates permite verificar rollback sin exponer una vía HTTP. */
    public function submitForReviewInTransaction(PDO $db, int $projectId, int $actorId, bool $failAfterStateUpdates = false): array
    {
        if ($projectId < 1 || $actorId < 1) throw new StudentProjectSubmissionException('La solicitud no es válida.');
        $project = $this->lockProject($db, $projectId, $actorId);
        $this->assertActor($db, $projectId, $actorId);
        $this->assertAcademicInformation($db, $project);

        $readiness = (new ProjectReviewReadinessService())->check($projectId, true);
        if (empty($readiness['ready'])) {
            $message = !empty($readiness['message'])
                ? (string) $readiness['message']
                : 'No se puede enviar todavía. ' . count((array) ($readiness['pending_review_representations'] ?? [])) . ' archivo(s) necesitan preparar su representación para revisión.';
            throw new StudentProjectSubmissionException($message, 422, $readiness);
        }

        $files = $this->lockPendingFiles($db, $projectId);
        if ($files === []) throw new StudentProjectSubmissionException('No hay documentos pendientes por enviar a revisión.', 422);

        $deliveryNumber = $this->nextDeliveryNumber($db, $projectId);
        $delivery = $db->prepare("INSERT INTO project_deliveries(project_id,stage_id,version_number,title,comment,status,submitted_by,submitted_at)
            VALUES(:project,NULL,:number,:title,:comment,'under_review',:actor,UTC_TIMESTAMP())");
        $delivery->execute([
            'project' => $projectId, 'number' => $deliveryNumber,
            'title' => 'Entrega documental v' . $deliveryNumber,
            'comment' => 'Documentos enviados a revisión por el estudiante.', 'actor' => $actorId,
        ]);
        $deliveryId = (int) $db->lastInsertId();

        $assignDelivery = $db->prepare('UPDATE project_files SET delivery_id=:delivery WHERE id=:file AND project_id=:project AND checksum_sha256=:checksum AND deleted_at IS NULL AND purged_at IS NULL');
        $review = new ProjectDocumentReviewService($db);
        foreach ($files as $file) {
            $assignDelivery->execute(['delivery' => $deliveryId, 'file' => $file['id'], 'project' => $projectId, 'checksum' => $file['checksum_sha256']]);
            if ($assignDelivery->rowCount() !== 1) throw new StudentProjectSubmissionException('Un documento cambió mientras se preparaba la entrega.', 409);
            $review->recordCurrentStatus($projectId, (int) $file['id'], (string) $file['checksum_sha256'], 'under_review', $actorId);
        }
        if ($failAfterStateUpdates) throw new RuntimeException('Fallo de prueba posterior a los estados documentales.');

        $update = $db->prepare("UPDATE projects SET status='under_review',updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='development' AND deleted_at IS NULL AND withdrawn_at IS NULL");
        $update->execute(['id' => $projectId]);
        if ($update->rowCount() !== 1) throw new StudentProjectSubmissionException('El estado del proyecto cambió mientras realizabas esta operación.', 409);

        $fileSummary = array_map(static fn (array $file): array => ['id'=>(int)$file['id'], 'name'=>(string)$file['original_name']], $files);
        (new ProjectAuditService($db))->record($projectId, $actorId, 'project_submitted_for_review', 'project_delivery', $deliveryId,
            ['status'=>'development'],
            ['status'=>'under_review','delivery_id'=>$deliveryId,'delivery_number'=>$deliveryNumber,'submitted_file_count'=>count($files),'files'=>$fileSummary]
        );
        (new ProjectAcademicNotificationService())->projectSubmittedForReview($db, $projectId, (string)$project['code'], (string)$project['title'], $deliveryNumber, count($files));

        return [
            'project_id'=>$projectId, 'project_status'=>'under_review', 'delivery_id'=>$deliveryId,
            'delivery_number'=>$deliveryNumber, 'submitted_file_count'=>count($files),
            'submitted_files'=>$fileSummary,
        ];
    }

    private function lockProject(PDO $db, int $projectId, int $actorId): array
    {
        $query = $db->prepare('SELECT id,code,title,summary,tutor_id,status,publication_origin,academic_period_id,published_at,is_available,deleted_at,withdrawn_at FROM projects WHERE id=:id FOR UPDATE');
        $query->execute(['id'=>$projectId]);
        $project = $query->fetch();
        if (!$project || !empty($project['deleted_at']) || !empty($project['withdrawn_at'])) throw new StudentProjectSubmissionException('El proyecto solicitado no está disponible.', 404);
        if (empty((new ProjectCapabilityService())->studentEditSituation($db, $project, $actorId)['can_edit_ordinary'])) throw new StudentProjectSubmissionException('El proyecto ya no está disponible para envío ordinario a revisión.', 409);
        return $project;
    }

    private function assertActor(PDO $db, int $projectId, int $actorId): void
    {
        $query = $db->prepare("SELECT 1 FROM users u
            INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='student'
            INNER JOIN project_participants pp ON pp.user_id=u.id
            WHERE u.id=:actor AND pp.project_id=:project AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
              AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL LIMIT 1 FOR UPDATE");
        $query->execute(['actor'=>$actorId,'project'=>$projectId]);
        if (!$query->fetchColumn()) throw new StudentProjectSubmissionException('No tienes autorización para enviar este proyecto a revisión.', 403);
    }

    private function assertAcademicInformation(PDO $db, array $project): void
    {
        $title = trim((string)$project['title']);
        $summary = trim((string)($project['summary'] ?? ''));
        if (mb_strlen($title) < 5 || mb_strlen($title) > 240 || mb_strlen($summary) < 30) {
            throw new StudentProjectSubmissionException('Completa el título y la descripción del proyecto antes de enviarlo a revisión.');
        }
        $tutors = $db->prepare("SELECT pp.user_id,pp.role_code FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id
            INNER JOIN teacher_profiles tp ON tp.user_id=u.id
            WHERE pp.project_id=:project AND LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor')
              AND pp.status='active' AND pp.removed_at IS NULL AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL AND tp.can_tutor=1 FOR UPDATE");
        $tutors->execute(['project'=>$project['id']]);
        $tutorRows = $tutors->fetchAll();
        if ($tutorRows === [] || !in_array((int)$project['tutor_id'], array_map(static fn(array $row): int => (int)$row['user_id'], $tutorRows), true)) {
            throw new StudentProjectSubmissionException('El proyecto debe conservar un tutor principal activo antes de enviarse a revisión.');
        }
        $authors = $db->prepare("SELECT user_id,is_leader FROM project_participants WHERE project_id=:project AND role_code='student' AND status='active' AND removed_at IS NULL FOR UPDATE");
        $authors->execute(['project'=>$project['id']]);
        $authorRows = $authors->fetchAll();
        if ($authorRows === [] || count(array_filter($authorRows, static fn(array $row): bool => !empty($row['is_leader']))) !== 1) {
            throw new StudentProjectSubmissionException('El proyecto debe conservar integrantes activos y exactamente un líder antes de enviarse a revisión.');
        }
    }

    private function lockPendingFiles(PDO $db, int $projectId): array
    {
        $hasDeliveries = (int)$db->query("SELECT COUNT(*) FROM project_deliveries WHERE project_id={$projectId}")->fetchColumn() > 0;
        if ($hasDeliveries) {
            $missingDelivered = $db->prepare("SELECT DISTINCT s.file_id
                FROM project_file_review_states s
                LEFT JOIN project_files pf ON pf.id=s.file_id AND pf.project_id=s.project_id AND pf.deleted_at IS NULL AND pf.purged_at IS NULL
                WHERE s.project_id=:project AND pf.id IS NULL");
            $missingDelivered->execute(['project' => $projectId]);
            if ($missingDelivered->fetchColumn()) {
                throw new StudentProjectSubmissionException('Falta un archivo requerido de la entrega anterior. Todos los documentos entregados previamente deben conservarse y reemplazarse si requieren cambios.', 422);
            }

            $missingObserved = $db->prepare("SELECT DISTINCT o.file_id
                FROM project_observations o
                LEFT JOIN project_files pf ON pf.id=o.file_id AND pf.project_id=o.project_id AND pf.deleted_at IS NULL AND pf.purged_at IS NULL
                WHERE o.project_id=:project AND o.file_id IS NOT NULL AND pf.id IS NULL");
            $missingObserved->execute(['project' => $projectId]);
            if ($missingObserved->fetchColumn()) {
                throw new StudentProjectSubmissionException('Falta un archivo requerido con observaciones. Debes conservar el archivo y reemplazarlo antes de reenviar el proyecto.', 422);
            }
        }

        $query = $db->prepare("SELECT f.id,f.original_name,f.checksum_sha256,COALESCE(s.status,'development') review_status
            FROM project_files f LEFT JOIN project_file_review_states s ON s.project_id=f.project_id AND s.file_id=f.id AND s.checksum_sha256=f.checksum_sha256
              AND EXISTS (SELECT 1 FROM project_deliveries d WHERE d.project_id=f.project_id)
            WHERE f.project_id=:project AND f.deleted_at IS NULL AND f.purged_at IS NULL
              AND COALESCE(s.status,'development') IN ('development','corrections_requested') ORDER BY f.sort_order,f.id FOR UPDATE");
        $query->execute(['project'=>$projectId]);
        $rows = $query->fetchAll();

        if ($hasDeliveries) {
            $correctionReadiness = (new ProjectCorrectionReadinessService($db))->forProject($projectId);
            if (!$correctionReadiness['eligible']) {
                throw new StudentProjectSubmissionException('Debes corregir todos los documentos observados antes de reenviar el proyecto.', 422);
            }
        }

        if ($rows === []) throw new StudentProjectSubmissionException('No hay documentos pendientes por enviar a revisión.', 422);
        return $rows;
    }

    private function nextDeliveryNumber(PDO $db, int $projectId): int
    {
        $query = $db->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM project_deliveries WHERE project_id=:project FOR UPDATE');
        $query->execute(['project'=>$projectId]);
        return max(1, (int)$query->fetchColumn());
    }
}
