<?php

declare(strict_types=1);

/** Fuente única de verdad para el flujo de estados de proyectos académicos. */
final class ProjectStatusTransitionService
{
    private const WARNING = 'Este cambio modifica el flujo académico del proyecto, quedará registrado en el historial y puede habilitar o cerrar etapas posteriores.';

    /** @var array<string,array<string,array<string,mixed>>> */
    private const TRANSITIONS = [
        'development' => [
            'under_review' => [
                'label' => 'Enviar a revisión', 'icon' => 'fa-paper-plane',
                'effect' => 'El proyecto ingresará a revisión académica.',
                'requirements' => ['review_representations'],
            ],
        ],
        'under_review' => [
            'approved' => [
                'label' => 'Aprobar', 'icon' => 'fa-circle-check',
                'requirements' => ['documents_approved'],
                'effect' => 'La revisión académica quedará aprobada.',
            ],
        ],
        'approved' => [
            'defense' => [
                'label' => 'Enviar a Tribunal', 'icon' => 'fa-users', 'thesis_only' => true,
                'effect' => 'El proyecto pasará a la etapa de evaluación por Tribunal.',
                'requirements' => ['tribunal'],
            ],
            'published' => [
                'label' => 'Publicar', 'icon' => 'fa-building-columns', 'non_thesis_only' => true,
                'effect' => 'El proyecto se publicará y quedará disponible en el Repositorio.',
                'requirements' => ['authors', 'files'], 'publishes' => true,
            ],
        ],
        'defense' => [
            'tribunal_approved' => [
                'label' => 'Registrar aprobación del Tribunal', 'icon' => 'fa-award', 'thesis_only' => true,
                'effect' => 'La aprobación del Tribunal quedará registrada.',
                'requirements' => ['tribunal'],
            ],
        ],
        'tribunal_approved' => [
            'published' => [
                'label' => 'Publicar', 'icon' => 'fa-building-columns', 'thesis_only' => true,
                'effect' => 'El proyecto se publicará y quedará disponible en el Repositorio.',
                'requirements' => ['authors', 'files'], 'publishes' => true,
            ],
        ],
        'published' => [],
    ];

    /** @return list<array<string,mixed>> */
    public function availableTransitions(array $project): array
    {
        $current = (string) ($project['status'] ?? '');
        $type = (string) ($project['type_code'] ?? '');
        $result = [];
        foreach (self::TRANSITIONS[$current] ?? [] as $target => $definition) {
            if (!$this->appliesToType($definition, $type)) continue;
            if ($target === 'under_review' && !array_key_exists('review_representation_summary', $project)) $project['review_representation_summary']=(new ProjectReviewReadinessService())->check((int)($project['id']??0),false);
            $requirements = $this->requirements($definition, $project);
            $fromLabels = project_academic_labels($current);
            $toLabels = project_academic_labels($target);
            $result[] = [
                'target' => $target,
                'label' => (string) $definition['label'],
                'icon' => (string) $definition['icon'],
                'effect' => (string) $definition['effect'],
                'warning' => self::WARNING,
                'current_label' => $fromLabels['status'],
                'target_label' => $toLabels['status'],
                'target_stage' => $toLabels['stage'],
                'reason_required' => !empty($definition['reason_required']),
                'publishes' => !empty($definition['publishes']),
                'requirements' => $requirements,
                'requirements_met' => !in_array(false, array_column($requirements, 'met'), true),
            ];
        }
        return $result;
    }

    /** @return array<string,mixed> */
    public function transition(int $projectId, string $expectedStatus, string $targetStatus, string $reason, int $actor, string $context = 'academic_management'): array
    {
        if ($projectId < 1 || $actor < 1) throw new ProjectStatusTransitionException('La solicitud de cambio de estado no es válida.');
        if ($expectedStatus === 'development' && $targetStatus === 'under_review') {
            $readiness=(new ProjectReviewReadinessService())->check($projectId,true);
            if (empty($readiness['ready'])) throw new ProjectStatusTransitionException('Hay documentos que aún no están listos para revisión.',422,$readiness);
        }
        $reason = trim($reason);
        return Database::transaction(fn (PDO $db): array => $this->transitionInTransaction($db, $projectId, $expectedStatus, $targetStatus, $reason, $actor, $context));
    }

    /** Ejecuta la transición sobre una transacción ya abierta; útil para composición y pruebas con rollback. */
    public function transitionInTransaction(PDO $db, int $projectId, string $expectedStatus, string $targetStatus, string $reason, int $actor, string $context = 'academic_management'): array
    {
        if ($projectId < 1 || $actor < 1) throw new ProjectStatusTransitionException('La solicitud de cambio de estado no es válida.');
        $reason = trim($reason);
            $query = $db->prepare(
                "SELECT p.id,p.code,p.title,p.status,p.project_type_id,p.presentation_file_id,p.published_at,p.is_available,pt.code type_code
                 FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id
                 WHERE p.id=:id AND p.deleted_at IS NULL FOR UPDATE"
            );
            $query->execute(['id' => $projectId]);
            $project = $query->fetch();
            if (!$project) throw new ProjectStatusTransitionException('El proyecto no existe o fue eliminado.', 404);
            if ((string) $project['status'] !== $expectedStatus) {
                throw new ProjectStatusTransitionException('El estado del proyecto cambió mientras realizabas esta operación. Actualiza la pantalla e inténtalo nuevamente.', 409);
            }

            $definition = self::TRANSITIONS[$expectedStatus][$targetStatus] ?? null;
            if (!is_array($definition) || !$this->appliesToType($definition, (string) $project['type_code'])) {
                throw new ProjectStatusTransitionException('La transición solicitada no está permitida para este proyecto.');
            }
            if (!empty($definition['reason_required']) && (mb_strlen($reason) < 5 || mb_strlen($reason) > 500)) {
                throw new ProjectStatusTransitionException('Indica un motivo de entre 5 y 500 caracteres.');
            }

            $project += $this->requirementData($db, $projectId);
            $requirements = $this->requirements($definition, $project);
            $missing = array_values(array_filter($requirements, static fn (array $item): bool => !$item['met']));
            if ($missing !== []) throw new ProjectStatusTransitionException((string) $missing[0]['message']);

            $publishes = !empty($definition['publishes']);
            $finalStatus = (string) $project['type_code'] === 'thesis' ? 'tribunal_approved' : 'approved';
            $recordCompletion = $targetStatus === $finalStatus;
            $update = $db->prepare(
                'UPDATE projects SET status=:status,
                 approved_at=CASE WHEN :completion=1 AND approved_at IS NULL THEN CURRENT_TIMESTAMP ELSE approved_at END,
                 published_at=CASE WHEN :publishes=1 THEN CURRENT_TIMESTAMP ELSE published_at END,
                 is_available=CASE WHEN :publishes_available=1 THEN 1 ELSE is_available END
                 WHERE id=:id AND status=:expected'
            );
            $update->execute([
                'status' => $targetStatus, 'completion' => $recordCompletion ? 1 : 0,
                'publishes' => $publishes ? 1 : 0, 'publishes_available' => $publishes ? 1 : 0,
                'id' => $projectId, 'expected' => $expectedStatus,
            ]);
            if ($update->rowCount() !== 1) {
                throw new ProjectStatusTransitionException('El estado del proyecto cambió mientras realizabas esta operación. Actualiza la pantalla e inténtalo nuevamente.', 409);
            }

            $action = match ($targetStatus) {
                'approved' => 'project_approved',
                'tribunal_approved' => 'project_tribunal_approved',
                'published' => 'project_published',
                default => 'project_updated',
            };
            $auditId = (new ProjectAuditService($db))->record(
                $projectId, $actor, $action, 'project', $projectId,
                ['status' => $expectedStatus],
                ['status' => $targetStatus, 'context' => in_array($context, ['repository','student_publication'], true) ? $context : 'academic_management'],
                $reason !== '' ? $reason : null
            );
            if ($publishes) (new ProjectDocumentArchiveService())->archiveHistoricalVersionsForProjectInTransaction($db,$projectId,$actor,'Publicación institucional del proyecto.');
            if ($publishes) (new ProjectRepositoryPackageService())->buildForProject($projectId);
            (new ProjectDescriptionService($db))->registerStatusReminder($projectId, $auditId);

            $labels = project_academic_labels($targetStatus);
            if ($targetStatus === 'defense' && $context !== 'thesis_tribunal_assignment') (new ProjectAcademicNotificationService())->defenseStarted($db,$projectId,(string)$project['code'],(string)$project['title'],$actor);
            if ($recordCompletion) (new ProjectAcademicNotificationService())->finalApproval($db,$projectId,(string)$project['code'],(string)$project['title'],$targetStatus,(string)$labels['status'],$auditId);
            if ($publishes) (new ProjectAcademicNotificationService())->projectPublished($db,$projectId,(string)$project['code'],(string)$project['title'],$auditId);
            return [
                'id' => $projectId, 'previous_status' => $expectedStatus, 'status' => $targetStatus,
                'status_label' => $labels['status'], 'stage_label' => $labels['stage'],
                'published' => $publishes,
            ];
    }

    private function appliesToType(array $definition, string $type): bool
    {
        if (!empty($definition['thesis_only']) && $type !== 'thesis') return false;
        if (!empty($definition['non_thesis_only']) && $type === 'thesis') return false;
        return true;
    }

    /** @return list<array{key:string,label:string,met:bool,message:string}> */
    private function requirements(array $definition, array $project): array
    {
        $result = [];
        foreach ((array) ($definition['requirements'] ?? []) as $requirement) {
            if ($requirement === 'tribunal') {
                $count = (int) ($project['tribunal_count'] ?? $this->participantCount($project, ['tribunal', 'jury']));
                $defenseStart = (string)($project['status']??'') === 'approved' && (string)($project['type_code']??'') === 'thesis';
                $complete = (int)($project['id']??0)>0 ? ThesisTribunalService::hasCompleteTribunal(Database::connection(),(int)$project['id']) : false;
                $met = $defenseStart ? $complete : ($count > 0 && ($project['type_code']??'') !== 'thesis' || $complete);
                $result[] = ['key' => 'tribunal', 'label' => 'Tribunal asignado', 'met' => $met, 'message' => $defenseStart ? 'El Tribunal debe tener exactamente Presidente, Miembro 1 y Miembro 2 activos antes de iniciar la defensa.' : 'El Tribunal debe conservar sus tres cargos activos antes de continuar.'];
            } elseif ($requirement === 'authors') {
                $met = (int) ($project['author_count'] ?? $this->participantCount($project, ['student'])) > 0;
                $result[] = ['key' => 'authors', 'label' => 'Autor o autores activos', 'met' => $met, 'message' => 'El proyecto debe conservar al menos un autor activo antes de publicarse.'];
            } elseif ($requirement === 'files') {
                $met = (int) ($project['active_file_count'] ?? count((array) ($project['files'] ?? []))) > 0;
                $result[] = ['key' => 'files', 'label' => 'Al menos un archivo activo', 'met' => $met, 'message' => 'Agrega al menos un archivo activo antes de publicar el proyecto.'];
            } elseif ($requirement === 'documents_approved') {
                $summary = (array) ($project['document_review_summary'] ?? []);
                if ($summary === [] && (int) ($project['id'] ?? 0) > 0) {
                    $summary = (new ProjectDocumentReviewService())->approvalSummaryForProject((int) $project['id']);
                }
                $total = (int) ($summary['total'] ?? 0);
                $met = $total > 0 && !empty($summary['all_active_documents_approved']);
                $message = $total === 0
                    ? 'El proyecto no puede aprobarse porque no contiene documentos activos.'
                    : 'El proyecto no puede aprobarse hasta que todos sus documentos vigentes estén aprobados.';
                $result[] = ['key'=>'documents_approved', 'label'=>'Documentos vigentes aprobados', 'met'=>$met, 'message'=>$message];
            } elseif ($requirement === 'review_representations') {
                $summary=(array)($project['review_representation_summary']??[]);$met=!empty($summary['ready']);
                $result[]=['key'=>'review_representations','label'=>'Representaciones PDF de revisión disponibles','met'=>$met,'message'=>'Todos los documentos que requieren revisión deben tener una representación PDF válida.'];
            }
        }
        return $result;
    }

    /** @return array{author_count:int,tribunal_count:int,active_file_count:int,document_review_summary:array<string,mixed>} */
    private function requirementData(PDO $db, int $projectId): array
    {
        $query = $db->prepare(
            "SELECT
             (SELECT COUNT(*) FROM project_participants WHERE project_id=:authors_id AND role_code='student' AND status='active' AND removed_at IS NULL) author_count,
             (SELECT COUNT(*) FROM project_participants WHERE project_id=:tribunal_id AND role_code IN ('tribunal','jury') AND status='active' AND removed_at IS NULL) tribunal_count,
             (SELECT COUNT(*) FROM project_files WHERE project_id=:files_id AND deleted_at IS NULL AND purged_at IS NULL) active_file_count"
        );
        $query->execute(['authors_id' => $projectId, 'tribunal_id' => $projectId, 'files_id' => $projectId]);
        $row = $query->fetch() ?: [];
        return [
            'author_count'=>(int)($row['author_count'] ?? 0),
            'tribunal_count'=>(int)($row['tribunal_count'] ?? 0),
            'active_file_count'=>(int)($row['active_file_count'] ?? 0),
            'document_review_summary'=>(new ProjectDocumentReviewService($db))->approvalSummaryForProject($projectId),
            'review_representation_summary'=>(new ProjectReviewReadinessService())->check($projectId,false),
        ];
    }

    private function participantCount(array $project, array $roles): int
    {
        return count(array_filter((array) ($project['participants'] ?? []), static fn (array $participant): bool =>
            in_array((string) ($participant['role_code'] ?? ''), $roles, true)
            && (string) ($participant['status'] ?? 'active') === 'active'
            && empty($participant['removed_at'])
        ));
    }
}
