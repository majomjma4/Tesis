<?php

declare(strict_types=1);

/** Datos reales y normalizados para el expediente digital de un proyecto. */
final class ProjectRecordModel
{
    public function academicHistoryPage(int $projectId, int $offset = 0, int $limit = 15): array
    {
        $offset = max(0, $offset); $limit = max(1, min(15, $limit));
        $window = $offset + $limit + 1;
        $db = Database::connection();
        $base = $db->prepare("SELECT p.id,p.created_by,p.created_at,p.status,p.publication_origin,p.published_at,pt.code type_code FROM projects p JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id AND p.deleted_at IS NULL AND p.publication_origin='workflow'");
        $base->execute(['id' => $projectId]);
        $project = $base->fetch();
        if (!$project) return ['events'=>[],'total'=>0,'loaded'=>0,'has_more'=>false,'next_offset'=>$offset];
        $project['participants'] = $this->rows($db, "SELECT pp.user_id,pp.role_code,pp.assigned_at,u.full_name FROM project_participants pp JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:id AND pp.status='active' AND pp.removed_at IS NULL ORDER BY pp.assigned_at,pp.user_id", $projectId);
        $project['deliveries'] = $this->windowRows($db, "SELECT pd.*,u.full_name author_name FROM project_deliveries pd JOIN users u ON u.id=pd.submitted_by WHERE pd.project_id=:id AND pd.version_number>1 ORDER BY pd.submitted_at,pd.id LIMIT {$window}", $projectId);
        $project['observations'] = $this->windowRows($db, "SELECT po.*,u.full_name author_name,pd.version_number FROM project_observations po JOIN users u ON u.id=po.author_id LEFT JOIN project_deliveries pd ON pd.id=po.delivery_id WHERE po.project_id=:id ORDER BY po.created_at,po.id LIMIT {$window}", $projectId);
        $project['responses'] = $this->windowRows($db, "SELECT response.*,u.full_name author_name,po.id observation_id FROM observation_responses response JOIN project_observations po ON po.id=response.observation_id JOIN users u ON u.id=response.author_id WHERE po.project_id=:id ORDER BY response.created_at,response.id LIMIT {$window}", $projectId);
        $activitySql = "SELECT pal.id,pal.action,pal.previous_state,pal.new_state,pal.created_at,u.full_name actor_name FROM project_audit_log pal LEFT JOIN users u ON u.id=pal.user_id WHERE pal.project_id=:id AND pal.action IN ('project_updated','project_approved','project_tribunal_approved','tribunal_approved','project_published','project_unpublished','project_republished','project_publication_reverted','project_corrections_requested') ORDER BY pal.created_at,pal.id";
        $project['activity'] = $this->windowRows($db, $activitySql . " LIMIT {$window}", $projectId);
        $events = $this->academicHistory($project);

        $deliveryTotal = $this->scalar($db, 'SELECT COUNT(*) FROM project_deliveries WHERE project_id=:id AND version_number>1', $projectId);
        $observationTotal = $this->scalar($db, 'SELECT COUNT(*) FROM project_observations WHERE project_id=:id', $projectId);
        $responseTotal = $this->scalar($db, 'SELECT COUNT(*) FROM observation_responses response JOIN project_observations observation ON observation.id=response.observation_id WHERE observation.project_id=:id', $projectId);
        $allActivity = $this->windowRows($db, $activitySql, $projectId);
        $countProject = $project; $countProject['deliveries']=[]; $countProject['observations']=[]; $countProject['responses']=[]; $countProject['activity']=$allActivity;
        $baseAcademicTotal = count($this->academicHistory($countProject));
        $total = $baseAcademicTotal + $deliveryTotal + $observationTotal + $responseTotal;
        $page = array_slice($events, $offset, $limit);
        return ['events'=>$page,'total'=>$total,'loaded'=>count($page),'has_more'=>$offset+count($page)<$total,'next_offset'=>$offset+count($page)];
    }

    public function find(int $projectId, ?int $userId, bool $administrator, bool $publishedOnly = false, bool $institutionalTeacherReadOnly = false, bool $studentOwnershipOnly = false): ?array
    {
        $db = Database::connection();
        $institutionalStatuses = "'" . implode("','", ProjectCapabilityService::INSTITUTIONAL_ACTIVE_STATUSES) . "'";
        $sql = "SELECT p.*, pt.code AS type_code, pt.name AS type_name, c.name AS career_name,
                       ap.name AS period_name, s.code AS subject_code, s.name AS subject_name,
                       rl.name AS research_line_name, tutor.id AS tutor_user_id,
                       tutor.username AS tutor_username, tutor.full_name AS tutor_name,
                       tutor.email AS tutor_email
                FROM projects p
                INNER JOIN project_types pt ON pt.id=p.project_type_id
                INNER JOIN careers c ON c.id=p.career_id
                INNER JOIN academic_periods ap ON ap.id=p.academic_period_id
                LEFT JOIN academic_subjects s ON s.id=p.academic_subject_id
                LEFT JOIN research_lines rl ON rl.id=p.research_line_id
                LEFT JOIN users tutor ON tutor.id=p.tutor_id
                WHERE p.id=:id AND p.deleted_at IS NULL";
        if ($publishedOnly) $sql .= " AND p.status='published' AND p.withdrawn_at IS NULL" . ($administrator ? '' : ' AND p.is_available=1') . "
            AND EXISTS (SELECT 1 FROM project_files visible_file WHERE visible_file.project_id=p.id AND visible_file.deleted_at IS NULL AND visible_file.purged_at IS NULL)
            AND EXISTS (SELECT 1 FROM project_participants visible_student INNER JOIN student_profiles visible_profile ON visible_profile.user_id=visible_student.user_id WHERE visible_student.project_id=p.id AND visible_student.role_code='student' AND visible_student.status='active' AND visible_student.removed_at IS NULL)";
        if (!$administrator && !$publishedOnly) {
            $sql .= $institutionalTeacherReadOnly
                ? " AND p.withdrawn_at IS NULL AND p.status IN (" . $institutionalStatuses . ")"
                : ($studentOwnershipOnly
                    ? " AND p.withdrawn_at IS NULL AND EXISTS (SELECT 1 FROM project_participants access_student INNER JOIN user_roles access_roles ON access_roles.user_id=access_student.user_id INNER JOIN roles access_role ON access_role.id=access_roles.role_id AND access_role.code='student' WHERE access_student.project_id=p.id AND access_student.user_id=:viewer_participant AND access_student.role_code='student' AND access_student.status='active' AND access_student.removed_at IS NULL)"
                    : " AND (p.created_by=:viewer_creator OR p.tutor_id=:viewer_tutor OR EXISTS (SELECT 1 FROM project_participants access_participant WHERE access_participant.project_id=p.id AND access_participant.user_id=:viewer_participant AND access_participant.status='active' AND access_participant.removed_at IS NULL))");
        }
        $statement = $db->prepare($sql);
        $parameters = ['id' => $projectId];
        if (!$administrator && !$publishedOnly && !$institutionalTeacherReadOnly) {
            $parameters['viewer_participant'] = (int) $userId;
            if (!$studentOwnershipOnly) {
                $parameters['viewer_creator'] = (int) $userId;
                $parameters['viewer_tutor'] = (int) $userId;
            }
        }
        $statement->execute($parameters);
        $project = $statement->fetch();
        if (!$project) return null;
        if (!$publishedOnly && !$administrator
            && (string) ($project['publication_origin'] ?? ProjectPublicationOrigin::WORKFLOW) === ProjectPublicationOrigin::DIRECT_REPOSITORY) {
            return null;
        }

        $project['participants'] = $this->rows($db, "SELECT pp.user_id,pp.role_code,pp.permission_level,pp.is_leader,pp.assigned_at,
                    u.username,u.full_name,u.email,tp.academic_title,sp.institutional_code,(tp.user_id IS NOT NULL) AS is_teacher,(sp.user_id IS NOT NULL) AS is_student
            FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id
            LEFT JOIN teacher_profiles tp ON tp.user_id=u.id
            LEFT JOIN student_profiles sp ON sp.user_id=u.id
            WHERE pp.project_id=:id AND pp.status='active' AND pp.removed_at IS NULL
            ORDER BY FIELD(pp.role_code,'student','tutor','cotutor','tribunal','jury'),pp.is_leader DESC,u.full_name", $projectId);
        $project['student_authors'] = array_values(array_filter(
            $project['participants'],
            static fn (array $participant): bool => (string) $participant['role_code'] === 'student'
        ));
        $leaderId = null;
        foreach ($project['student_authors'] as $author) {
            if (!empty($author['is_leader'])) {
                $leaderId = (int) $author['user_id'];
                break;
            }
        }
        if ($leaderId === null) {
            foreach ($project['student_authors'] as $author) {
                if ((int) $author['user_id'] === (int) $project['created_by']) {
                    $leaderId = (int) $author['user_id'];
                    break;
                }
            }
        }
        foreach ($project['student_authors'] as &$author) {
            $author['is_display_leader'] = $leaderId !== null && (int) $author['user_id'] === $leaderId;
        }
        unset($author);
        $project['keywords'] = (new ProjectKeywordModel())->forProject($projectId, $db);
        $project['files'] = $this->rows($db, "SELECT pf.*,u.full_name AS uploaded_by_name,pd.version_number,pd.title AS delivery_title,pd.status AS delivery_status,pd.submitted_at
            FROM project_files pf LEFT JOIN users u ON u.id=pf.uploaded_by LEFT JOIN project_deliveries pd ON pd.id=pf.delivery_id
            WHERE pf.project_id=:id AND pf.deleted_at IS NULL AND pf.purged_at IS NULL ORDER BY pf.sort_order,pf.created_at DESC,pf.id DESC", $projectId);
        foreach ($project['files'] as &$projectFile) if (!empty($projectFile['delivery_status'])) {
            $projectFile['delivery_status_label'] = project_delivery_status_label((string) $projectFile['delivery_status']);
        }
        unset($projectFile);
        $project['deliveries'] = $this->rows($db, "SELECT pd.*,u.full_name AS author_name,ps.label AS stage_label
            FROM project_deliveries pd INNER JOIN users u ON u.id=pd.submitted_by LEFT JOIN project_stages ps ON ps.id=pd.stage_id
            WHERE pd.project_id=:id ORDER BY pd.submitted_at ASC,pd.id ASC", $projectId);
        foreach ($project['deliveries'] as &$projectDelivery) {
            $projectDelivery['status_label'] = project_delivery_status_label((string) $projectDelivery['status']);
        }
        unset($projectDelivery);
        $project['observations'] = $this->rows($db, "SELECT po.*,u.full_name AS author_name,pd.version_number
            FROM project_observations po INNER JOIN users u ON u.id=po.author_id LEFT JOIN project_deliveries pd ON pd.id=po.delivery_id
            WHERE po.project_id=:id ORDER BY po.created_at ASC,po.id ASC", $projectId);
        $project['responses'] = $this->rows($db, "SELECT response.*,u.full_name AS author_name,po.project_id FROM observation_responses response INNER JOIN project_observations po ON po.id=response.observation_id INNER JOIN users u ON u.id=response.author_id WHERE po.project_id=:id ORDER BY response.created_at DESC,response.id DESC", $projectId);
        $project['comments'] = $this->rows($db, "SELECT pc.*,u.full_name AS author_name FROM project_comments pc INNER JOIN users u ON u.id=pc.author_id WHERE pc.project_id=:id AND pc.deleted_at IS NULL ORDER BY pc.created_at DESC", $projectId);
        $project['stages'] = $this->rows($db, "SELECT stage_code,label,status,completed_at FROM project_stages WHERE project_id=:id ORDER BY position", $projectId);
        $project['activity'] = $this->rows($db, "SELECT pal.id,pal.action,pal.entity_type,pal.entity_id,pal.previous_state,pal.new_state,pal.reason,pal.created_at,u.full_name AS actor_name FROM project_audit_log pal LEFT JOIN users u ON u.id=pal.user_id WHERE pal.project_id=:id ORDER BY pal.created_at ASC,pal.id ASC", $projectId);
        $project['post_publication_modifications'] = $this->postPublicationModifications($project);
        $project['tribunal_approved_at'] = null;
        foreach ($project['activity'] as $audit) if (in_array((string)$audit['action'],['project_tribunal_approved','tribunal_approved'],true)) { $project['tribunal_approved_at']=$audit['created_at']; break; }
        $approvalStatus = (string) $project['type_code'] === 'thesis' ? 'tribunal_approved' : 'approved';
        $project['academic_approved_at'] = $approvalStatus === 'approved' && !empty($project['approved_at'])
            ? $project['approved_at'] : $this->statusTransitionDate($project['activity'], $approvalStatus);
        $project['academic_completed_at'] = $project['academic_approved_at'];
        $project['repository_published_at'] = $this->latestDate(
            $project['published_at'] ?? null,
            $this->statusTransitionDate($project['activity'], 'published')
        );
        return $project;
    }

    public function findFile(int $projectId, int $fileId): ?array
    {
        $statement = Database::connection()->prepare("SELECT * FROM project_files WHERE id=:file_id AND project_id=:project_id AND deleted_at IS NULL AND purged_at IS NULL");
        $statement->execute(['file_id' => $fileId, 'project_id' => $projectId]);
        return $statement->fetch() ?: null;
    }

    /** Resolves a current file and its real parent project from file_id. */
    public function findFileById(int $fileId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT * FROM project_files
             WHERE id=:file_id AND deleted_at IS NULL AND purged_at IS NULL LIMIT 1"
        );
        $statement->execute(['file_id' => $fileId]);
        return $statement->fetch() ?: null;
    }

    private function rows(PDO $db, string $sql, int $projectId): array
    {
        $statement = $db->prepare($sql);
        $statement->execute(['id' => $projectId]);
        return $statement->fetchAll();
    }

    private function windowRows(PDO $db, string $sql, int $projectId): array
    {
        return $this->rows($db, $sql, $projectId);
    }

    private function scalar(PDO $db, string $sql, int $projectId): int
    {
        $statement=$db->prepare($sql);$statement->execute(['id'=>$projectId]);return (int)$statement->fetchColumn();
    }

    /** Reconstruye la historia académica con datos existentes, sin mezclar la auditoría administrativa. */
    private function academicHistory(array $project): array
    {
        $registrationActor = 'Sistema académico';
        foreach ((array) $project['participants'] as $participant) {
            if ((int) $participant['user_id'] === (int) $project['created_by']) {
                $registrationActor = (string) $participant['full_name'];
                break;
            }
        }
        $events = [[
            'key' => 'registration', 'type' => 'registration', 'title' => 'Proyecto registrado',
            'detail' => 'Se registró el proyecto junto con su primera entrega documental.',
            'actor' => $registrationActor, 'date' => (string) $project['created_at'],
        ]];

        foreach ((array) $project['deliveries'] as $delivery) {
            if ((int) $delivery['version_number'] <= 1) continue;
            $fileCount = (int) ($delivery['file_count'] ?? 1);
            if ($fileCount <= 0) $fileCount = 1;
            $filesLabel = $fileCount === 1 ? '1 archivo corregido' : $fileCount . ' archivos corregidos';
            $events[] = [
                'key' => 'delivery:' . (int) $delivery['id'], 'type' => 'delivery', 'title' => 'Correcciones reenviadas',
                'detail' => $filesLabel,
                'actor' => (string) $delivery['author_name'], 'date' => (string) $delivery['submitted_at'],
                'meta' => ['Entrega ' . (int) $delivery['version_number'], project_delivery_status_label((string) $delivery['status'])],
            ];
        }

        $groupedObservations = [];
        foreach ((array) $project['observations'] as $observation) {
            $createdKey = date('Y-m-d H:i:s', strtotime((string) $observation['created_at']));
            $groupKey = $createdKey . '_' . (int) ($observation['author_id'] ?? 0);
            if (!isset($groupedObservations[$groupKey])) {
                $groupedObservations[$groupKey] = [
                    'id' => (int) $observation['id'],
                    'author_name' => (string) ($observation['author_name'] ?? 'Docente'),
                    'created_at' => (string) $observation['created_at'],
                    'version_number' => $observation['version_number'] ?? null,
                    'count' => 0,
                    'file_ids' => [],
                ];
            }
            $groupedObservations[$groupKey]['count']++;
            if (!empty($observation['file_id'])) {
                $groupedObservations[$groupKey]['file_ids'][(int) $observation['file_id']] = true;
            }
        }

        foreach ($groupedObservations as $group) {
            $obsCount = $group['count'];
            $fileCount = count($group['file_ids']);
            $obsText = $obsCount === 1 ? '1 observación' : $obsCount . ' observaciones';
            $detail = $fileCount > 0
                ? $obsText . ' · ' . ($fileCount === 1 ? '1 archivo' : $fileCount . ' archivos')
                : $obsText;

            $events[] = [
                'key' => 'observation-batch:' . $group['id'],
                'type' => 'observation',
                'title' => 'Enviado a correcciones',
                'detail' => $detail,
                'actor' => $group['author_name'],
                'date' => $group['created_at'],
                'meta' => array_values(array_filter([
                    !empty($group['version_number']) ? 'Entrega ' . (int) $group['version_number'] : null,
                ])),
            ];
        }

        foreach ((array) ($project['responses'] ?? []) as $response) {
            $events[] = [
                'key' => 'response:' . (int) $response['id'], 'type' => 'response', 'title' => 'Respuesta a observación registrada',
                'detail' => trim((string) ($response['message'] ?? $response['content'] ?? '')),
                'actor' => (string) $response['author_name'], 'date' => (string) $response['created_at'],
                'reference' => ['type' => 'observation', 'id' => (int) $response['observation_id']],
            ];
        }

        if ((string) $project['type_code'] === 'thesis') {
            $tribunal = array_values(array_filter((array) $project['participants'], static fn (array $participant): bool =>
                in_array((string) $participant['role_code'], ['tribunal', 'jury'], true)
            ));
            if ($tribunal) {
                $assignedAt = max(array_map(static fn (array $member): string => (string) $member['assigned_at'], $tribunal));
                $events[] = [
                    'key' => 'tribunal-assigned', 'type' => 'tribunal', 'title' => 'Tribunal asignado',
                    'detail' => 'Se completó la asignación del tribunal del proyecto.',
                    'actor' => 'Sistema académico', 'date' => $assignedAt,
                ];
            }
        }

        $statusLabels = [
            'development' => 'En desarrollo', 'under_review' => 'En revisión',
            'changes_required' => 'Correcciones solicitadas', 'approved' => 'Aprobado',
            'defense' => 'En tribunal', 'tribunal_approved' => 'Aprobado por el Tribunal',
            'published' => 'Publicado',
        ];
        $seenTransitions = [];
        $publicationRecorded = false;
        foreach ((array) $project['activity'] as $audit) {
            $action = (string) $audit['action'];
            if (in_array($action, ['project_unpublished', 'project_republished'], true)) continue;
            if ($action === 'project_corrections_requested') {
                // If observation batch already created event for this timestamp, skip duplicate audit card
                $auditTime = date('Y-m-d H:i:s', strtotime((string) $audit['created_at']));
                $hasObsBatch = false;
                foreach ($groupedObservations as $gKey => $gData) {
                    if (date('Y-m-d H:i:s', strtotime((string) $gData['created_at'])) === $auditTime) {
                        $hasObsBatch = true;
                        break;
                    }
                }
                if ($hasObsBatch) continue;

                $next = json_decode((string) ($audit['new_state'] ?? ''), true);
                $count = is_array($next) ? (int) ($next['observation_count'] ?? 0) : 0;
                $fileCount = is_array($next) ? (int) ($next['corrections_requested'] ?? 0) : 0;
                $obsText = $count === 1 ? '1 observación' : ($count > 0 ? $count . ' observaciones' : 'Correcciones solicitadas por el tutor.');
                $detail = ($fileCount > 0 && $count > 0)
                    ? $obsText . ' · ' . ($fileCount === 1 ? '1 archivo' : $fileCount . ' archivos')
                    : $obsText;

                $events[] = [
                    'key' => 'corrections-requested:' . (int) $audit['id'],
                    'type' => 'observation',
                    'title' => 'Enviado a correcciones',
                    'detail' => $detail,
                    'actor' => (string) ($audit['actor_name'] ?: 'Sistema académico'),
                    'date' => (string) $audit['created_at'],
                ];
                continue;
            }
            if ($action === 'project_publication_reverted') {
                [$from, $to] = $this->statusChange($audit, $statusLabels);
                if ($from !== null && $to !== null) $events[] = [
                    'key' => 'publication-reverted:' . (int) $audit['id'], 'type' => 'status', 'title' => 'Publicación revertida',
                    'detail' => ($statusLabels[$from] ?? $from) . "\n↓\n" . ($statusLabels[$to] ?? $to),
                    'actor' => (string) ($audit['actor_name'] ?: 'Sistema académico'), 'date' => (string) $audit['created_at'],
                ];
                continue;
            }
            [$from, $to] = $this->statusChange($audit, $statusLabels);
            if ($from === null || $to === null || $from === $to) continue;
            if ($to === 'published' && $publicationRecorded) continue;
            $transitionKey = $from . '>' . $to . '@' . (string) $audit['created_at'];
            if (isset($seenTransitions[$transitionKey])) continue;
            $seenTransitions[$transitionKey] = true;
            if ($to === 'published') {
                $type = 'publication';
                $title = 'Proyecto publicado';
                $detail = 'El expediente pasó a formar parte del Repositorio institucional.';
                $publicationRecorded = true;
            } elseif ($to === 'tribunal_approved' && (string) $project['type_code'] === 'thesis') {
                $type = 'tribunal-approval';
                $title = 'Proyecto aprobado por tribunal';
                $detail = '';
            } elseif ($to === 'under_review') {
                $type = 'status';
                $title = 'Proyecto enviado a revisión';
                $detail = ($statusLabels[$from] ?? $from) . "\n↓\nEn revisión";
            } elseif ($to === 'approved') {
                $type = 'status';
                $title = 'Proyecto aprobado';
                $detail = 'La revisión académica finalizó satisfactoriamente.';
            } else {
                $type = 'status';
                $title = 'Cambio de estado';
                $detail = ($statusLabels[$from] ?? $from) . "\n↓\n" . ($statusLabels[$to] ?? $to);
            }
            $events[] = [
                'key' => 'status:' . (int) $audit['id'], 'type' => $type, 'title' => $title, 'detail' => $detail,
                'actor' => (string) ($audit['actor_name'] ?: 'Sistema académico'), 'date' => (string) $audit['created_at'],
            ];
        }
        if (!$publicationRecorded && (string) $project['status'] === 'published' && !empty($project['published_at'])) {
            $events[] = [
                'key' => 'publication:legacy', 'type' => 'publication', 'title' => 'Proyecto publicado',
                'detail' => 'El expediente pasó a formar parte del Repositorio institucional.',
                'actor' => 'Sistema académico', 'date' => (string) $project['published_at'],
            ];
        }

        usort($events, static function (array $left, array $right): int {
            $dateOrder = strcmp((string) $left['date'], (string) $right['date']);
            if ($dateOrder !== 0) return $dateOrder;
            if ($left['key'] === 'registration') return -1;
            if ($right['key'] === 'registration') return 1;
            return strcmp((string) $left['key'], (string) $right['key']);
        });
        return $events;
    }

    private function observationStatusLabel(string $status): ?string
    {
        return match ($status) {
            'pending' => 'Pendientes', 'addressed' => 'Atendidas', 'resolved' => 'Resueltas',
            default => null,
        };
    }

    private function statusChange(array $audit, array $labels): array
    {
        $previous = json_decode((string) ($audit['previous_state'] ?? ''), true);
        $next = json_decode((string) ($audit['new_state'] ?? ''), true);
        $labelKeys = array_flip($labels);
        $from = is_array($previous) ? ($previous['status'] ?? ($labelKeys[$previous['Estado'] ?? ''] ?? null)) : null;
        $to = is_array($next) ? ($next['status'] ?? ($labelKeys[$next['Estado'] ?? ''] ?? null)) : null;
        if ($from !== null && $to !== null) return [(string) $from, (string) $to];
        foreach ((array) (is_array($next) ? ($next['_history_changes'] ?? []) : []) as $change) {
            if (($change['field'] ?? '') !== 'Estado') continue;
            return [$labelKeys[$change['from'] ?? ''] ?? null, $labelKeys[$change['to'] ?? ''] ?? null];
        }
        return [null, null];
    }

    /** Solo modificaciones administrativas posteriores a la publicación; un bloque vacío no se renderiza. */
    private function postPublicationModifications(array $project): array
    {
        $publicationDate = null;
        $publicationId = null;
        foreach ((array) $project['activity'] as $audit) {
            if (in_array((string) $audit['action'], ['project_published', 'project_republished'], true)) {
                $publicationDate = (string) $audit['created_at'];
                $publicationId = (int) $audit['id'];
                break;
            }
        }
        $publicationDate ??= !empty($project['published_at']) ? (string) $project['published_at'] : null;
        if ($publicationDate === null) return [];

        $labels = [
            'project_updated' => 'Información del proyecto actualizada',
            'project_description_updated' => 'Descripción del proyecto actualizada',
            'project_participants_updated' => 'Participantes del proyecto actualizados',
            'project_authors_updated' => 'Autores del proyecto actualizados',
            'project_keywords_updated' => 'Etiquetas del proyecto actualizadas',
            'project.presentation_selected' => 'Archivo principal seleccionado',
            'project.presentation_changed' => 'Archivo principal actualizado',
            'project.presentation_removed' => 'Archivo principal retirado',
            'project.file_added' => 'Archivo agregado', 'project.file_replaced' => 'Archivo reemplazado',
            'project.file_removed' => 'Archivo retirado', 'project.file_restored' => 'Archivo restaurado',
            'project.file_purged' => 'Archivo eliminado definitivamente',
            'project_file_replaced' => 'Archivo reemplazado', 'project_file_removed' => 'Archivo retirado',
        ];
        $events = [];
        foreach ((array) $project['activity'] as $audit) {
            $action = (string) $audit['action'];
            $dateOrder = strcmp((string) $audit['created_at'], $publicationDate);
            $isLater = $dateOrder > 0 || ($dateOrder === 0 && $publicationId !== null && (int) $audit['id'] > $publicationId);
            if (!isset($labels[$action]) || !$isLater || !$this->isExceptionalModification($audit)) continue;
            $events[] = [
                'key' => 'modification:' . (int) $audit['id'], 'title' => $labels[$action],
                'detail' => $this->administrativeChangeDetail($audit),
                'actor' => (string) ($audit['actor_name'] ?: 'Administración institucional'),
                'date' => (string) $audit['created_at'],
            ];
        }
        return $events;
    }

    private function isExceptionalModification(array $audit): bool
    {
        if ((string) $audit['action'] !== 'project_updated') return true;
        $previous = json_decode((string) ($audit['previous_state'] ?? ''), true);
        $next = json_decode((string) ($audit['new_state'] ?? ''), true);
        if (!is_array($next)) return false;
        $history = (array) ($next['_history_changes'] ?? []);
        if ($history !== []) {
            return count(array_filter($history, static fn (array $change): bool =>
                !in_array((string) ($change['field'] ?? ''), ['Estado', 'Disponibilidad'], true)
            )) > 0;
        }
        if (!is_array($previous)) return false;
        foreach (array_unique([...array_keys($previous), ...array_keys($next)]) as $field) {
            if (in_array($field, ['id', 'status', 'is_available', '_history_changes', 'published_at'], true)) continue;
            if (($previous[$field] ?? null) !== ($next[$field] ?? null)) return true;
        }
        return false;
    }

    private function administrativeChangeDetail(array $audit): string
    {
        $next = json_decode((string) ($audit['new_state'] ?? ''), true);
        $changes = is_array($next) ? (array) ($next['_history_changes'] ?? []) : [];
        $lines = array_values(array_filter(array_map(static function (array $change): string {
            $field = trim((string) ($change['field'] ?? 'Dato'));
            $from = trim((string) ($change['from'] ?? 'Sin asignar'));
            $to = trim((string) ($change['to'] ?? 'Sin asignar'));
            return $from !== $to ? $field . ': ' . $from . ' → ' . $to : '';
        }, $changes)));
        if ($lines !== []) return implode("\n", $lines);
        return trim((string) ($audit['reason'] ?? ''));
    }

    private function statusTransitionDate(array $activity, string $targetStatus): ?string
    {
        $labels = ['approved' => 'Aprobado', 'tribunal_approved' => 'Aprobado por el Tribunal', 'published' => 'Publicado'];
        foreach ($activity as $event) {
            $action = (string) ($event['action'] ?? '');
            if (($targetStatus === 'approved' && $action === 'project_approved')
                || ($targetStatus === 'tribunal_approved' && in_array($action, ['project_tribunal_approved', 'tribunal_approved'], true))
                || ($targetStatus === 'published' && in_array($action, ['project_published', 'project_republished'], true))) {
                return (string) $event['created_at'];
            }
            $state = json_decode((string) ($event['new_state'] ?? ''), true);
            if (!is_array($state)) continue;
            if (($state['status'] ?? null) === $targetStatus || ($state['Estado'] ?? null) === ($labels[$targetStatus] ?? null)) return (string) $event['created_at'];
            foreach ((array) ($state['_history_changes'] ?? []) as $change) {
                if (($change['field'] ?? '') === 'Estado' && ($change['to'] ?? '') === ($labels[$targetStatus] ?? '')) return (string) $event['created_at'];
            }
        }
        return null;
    }

    private function latestDate(?string $first, ?string $second): ?string
    {
        if (!$first) return $second ?: null;
        if (!$second) return $first;
        return strtotime($second) > strtotime($first) ? $second : $first;
    }

}
