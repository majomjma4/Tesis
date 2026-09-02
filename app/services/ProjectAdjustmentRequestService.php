<?php

declare(strict_types=1);

/** Única vía de escritura para las solicitudes administrativas de ajuste. */
final class ProjectAdjustmentRequestService
{
    private const TYPES = ['incomplete_information','incorrect_data','inconsistency','other','published_modification'];

    public function create(int $projectId, string $expectedStatus, int $actorId, string $context, array $data): array
    {
        return Database::transaction(fn(PDO $db): array => $this->createInTransaction($db, $projectId, $expectedStatus, $actorId, $context, $data));
    }

    public function respond(int $projectId, int $requestId, int $lockVersion, string $expectedStatus, int $actorId, string $context, string $message): array
    {
        return Database::transaction(fn(PDO $db): array => $this->respondInTransaction($db, $projectId, $requestId, $lockVersion, $expectedStatus, $actorId, $context, $message));
    }

    public function address(int $projectId, int $requestId, int $lockVersion, string $expectedStatus, int $actorId, string $context): array
    {
        return Database::transaction(fn(PDO $db): array => $this->transitionInTransaction($db, $projectId, $requestId, $lockVersion, $expectedStatus, $actorId, $context, 'addressed'));
    }

    public function close(int $projectId, int $requestId, int $lockVersion, string $expectedStatus, int $actorId, string $context): array
    {
        return Database::transaction(fn(PDO $db): array => $this->transitionInTransaction($db, $projectId, $requestId, $lockVersion, $expectedStatus, $actorId, $context, 'closed'));
    }

    public function approve(int $projectId, int $requestId, int $lockVersion, string $expectedStatus, int $actorId, string $context): array
    {
        return Database::transaction(fn(PDO $db): array => $this->approveInTransaction($db, $projectId, $requestId, $lockVersion, $expectedStatus, $actorId, $context));
    }

    public function reject(int $projectId, int $requestId, int $lockVersion, string $expectedStatus, int $actorId, string $context, string $rejectionReason = ''): array
    {
        return Database::transaction(fn(PDO $db): array => $this->rejectInTransaction($db, $projectId, $requestId, $lockVersion, $expectedStatus, $actorId, $context, $rejectionReason));
    }

    public function createInTransaction(PDO $db, int $projectId, string $expectedStatus, int $actorId, string $context, array $data): array
    {
        $project = $this->lockProject($db, $projectId, $expectedStatus);
        $this->requireCapability($db, $project, $actorId, $context, 'create_adjustment_request');
        $actorProfile = $this->actorProfile($db, $actorId);
        $actorRole = (string) ($actorProfile['role'] ?? '');
        $studentActor = $actorRole === 'student';
        $studentEditable = $this->studentsCanEditOrdinarily($db, $project);
        $type = strtolower(trim((string)($data['request_type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) throw new ProjectAdjustmentRequestException('El tipo de solicitud administrativa no es válido.');
        if ($context === 'repository' && $studentActor && $type !== 'published_modification') throw new ProjectAdjustmentRequestException('El estudiante debe utilizar la solicitud de modificación controlada.');
        if ($context === 'repository' && !$studentActor && $type === 'published_modification') throw new ProjectAdjustmentRequestException('El docente debe utilizar una solicitud académica de cambios.');
        if ($context === 'academic_management' && $type === 'published_modification') throw new ProjectAdjustmentRequestException('La modificación publicada debe originarse desde la solicitud controlada del estudiante.');
        if ($context === 'academic' && $studentActor && $type !== 'published_modification') throw new ProjectAdjustmentRequestException('El estudiante debe utilizar la solicitud de modificación controlada.');
        if ($context === 'academic' && !$studentActor && $type === 'published_modification') throw new ProjectAdjustmentRequestException('El tipo de solicitud no es válido para una solicitud académica docente.');
        if ($studentActor && $context !== 'repository' && $type === 'published_modification' && !$this->studentCanRequestControlledModification($db, $project, $actorId)) {
            throw new ProjectAdjustmentRequestException('El proyecto no requiere una solicitud administrativa de modificación en este momento.', 409);
        }
        $message = $this->message((string)($data['message'] ?? ''), $context === 'repository' || $type === 'published_modification' ? 10 : 1);
        $section = $this->optionalText($data['related_section'] ?? null, 100);
        $field = null;
        $fileId = empty($data['file_id']) ? null : (int)$data['file_id'];
        if ($fileId !== null) {
            $file = $db->prepare('SELECT id FROM project_files WHERE id=:file AND project_id=:project AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');
            $file->execute(['file'=>$fileId, 'project'=>$projectId]);
            if (!$file->fetchColumn()) throw new ProjectAdjustmentRequestException('El archivo relacionado no pertenece al proyecto o ya no está activo.');
        }
        // El proyecto queda bloqueado durante toda la transacción, por lo que
        // esta comprobación también evita carreras entre dos envíos iguales.
        $duplicate = $db->prepare(
            "SELECT id FROM project_adjustment_requests
             WHERE project_id=:project AND requested_by=:actor AND request_type=:type AND status='pending'
               AND related_section <=> :section AND file_id <=> :file
             ORDER BY id DESC LIMIT 1 FOR UPDATE"
        );
        $duplicate->execute(['project'=>$projectId,'actor'=>$actorId,'type'=>$type,'section'=>$section,'file'=>$fileId]);
        if ($duplicate->fetchColumn()) throw new ProjectAdjustmentRequestException('Ya existe una solicitud pendiente equivalente para este proyecto.', 409);
        $insert = $db->prepare(
            "INSERT INTO project_adjustment_requests(project_id,requested_by,request_type,message,related_section,related_field,file_id,status)
             VALUES(:project,:actor,:type,:message,:section,:field,:file,'pending')"
        );
        $insert->execute(['project'=>$projectId,'actor'=>$actorId,'type'=>$type,'message'=>$message,'section'=>$section,'field'=>$field,'file'=>$fileId]);
        $id = (int)$db->lastInsertId();
        (new ProjectAuditService($db))->record($projectId, $actorId, 'project_adjustment_request_created', 'project_adjustment_request', $id, null, ['status'=>'pending','request_type'=>$type,'file_id'=>$fileId]);
        $this->notifyStudentsOfCreation($db, $project, $id, $type, $context, $actorProfile, $studentActor);
        $notifyAdministrators = $context === 'repository'
            || $studentActor
            || ($context === 'academic' && !$studentEditable);
        if ($notifyAdministrators) {
            $this->notifyAdministratorsOfCreation(
                $db, $projectId, $id, $type, $context, $actorProfile,
                $studentEditable, $studentActor ? $actorId : 0
            );
        }
        return $this->result($db, $projectId, $id, 'Solicitud de ajuste creada correctamente.');
    }

    public function respondInTransaction(PDO $db, int $projectId, int $requestId, int $lockVersion, string $expectedStatus, int $actorId, string $context, string $message): array
    {
        $project = $this->lockProject($db, $projectId, $expectedStatus);
        $this->requireCapability($db, $project, $actorId, $context, 'respond_adjustment_request');
        $request = $this->lockRequest($db, $projectId, $requestId, $lockVersion);
        if ($request['status'] !== 'pending') throw new ProjectAdjustmentRequestException('Solo es posible responder solicitudes pendientes.', 409);
        $insert = $db->prepare('INSERT INTO project_adjustment_request_responses(request_id,author_id,message) VALUES(:request,:author,:message)');
        $insert->execute(['request'=>$requestId,'author'=>$actorId,'message'=>$this->message($message)]);
        $this->incrementVersion($db, $requestId, $lockVersion);
        (new ProjectAuditService($db))->record($projectId, $actorId, 'project_adjustment_request_responded', 'project_adjustment_request', $requestId, ['lock_version'=>$lockVersion], ['lock_version'=>$lockVersion+1]);
        return $this->result($db, $projectId, $requestId, 'Respuesta registrada correctamente.');
    }

    public function transitionInTransaction(PDO $db, int $projectId, int $requestId, int $lockVersion, string $expectedStatus, int $actorId, string $context, string $target): array
    {
        $project = $this->lockProject($db, $projectId, $expectedStatus);
        $capability = $target === 'closed' ? 'close_adjustment_request' : 'address_adjustment_request';
        $this->requireCapability($db, $project, $actorId, $context, $capability);
        $request = $this->lockRequest($db, $projectId, $requestId, $lockVersion);
        if ($target === 'closed' && $this->decisionAction($db, $requestId) === 'project_adjustment_request_approved') {
            throw new ProjectAdjustmentRequestException('Una solicitud aprobada ya no puede cerrarse como una solicitud genérica.', 409);
        }
        $allowed = $target === 'addressed' ? $request['status'] === 'pending' : $request['status'] === 'addressed';
        if (!$allowed) throw new ProjectAdjustmentRequestException($target === 'closed' ? 'La solicitud ya está cerrada o aún no fue atendida.' : 'La solicitud ya no está pendiente.', 409);
        $sql = $target === 'closed'
            ? "UPDATE project_adjustment_requests SET status='closed',closed_at=NOW(),closed_by=:actor,updated_at=NOW(),lock_version=lock_version+1 WHERE id=:id AND lock_version=:version"
            : "UPDATE project_adjustment_requests SET status='addressed',addressed_at=NOW(),updated_at=NOW(),lock_version=lock_version+1 WHERE id=:id AND lock_version=:version";
        $update = $db->prepare($sql);
        $parameters = ['id'=>$requestId,'version'=>$lockVersion]; if ($target === 'closed') $parameters['actor']=$actorId;
        $update->execute($parameters);
        if ($update->rowCount() !== 1) $this->conflict();
        (new ProjectAuditService($db))->record($projectId, $actorId, 'project_adjustment_request_'.$target, 'project_adjustment_request', $requestId, ['status'=>$request['status'],'lock_version'=>$lockVersion], ['status'=>$target,'lock_version'=>$lockVersion+1]);
        return $this->result($db, $projectId, $requestId, $target === 'closed' ? 'Solicitud cerrada correctamente.' : 'Solicitud marcada como atendida.');
    }

    public function approveInTransaction(PDO $db, int $projectId, int $requestId, int $lockVersion, string $expectedStatus, int $actorId, string $context): array
    {
        $project = $this->lockProject($db, $projectId, $expectedStatus);
        $this->requireCapability($db, $project, $actorId, $context, 'approve_adjustment_request');
        $request = $this->lockRequest($db, $projectId, $requestId, $lockVersion);
        $this->assertPendingModification($request);

        try {
            $reopened = (new ProjectStatusTransitionService())->reopenForAdjustmentInTransaction($db, $projectId, $actorId, $requestId);
        } catch (ProjectStatusTransitionException $exception) {
            throw new ProjectAdjustmentRequestException($exception->getMessage(), $exception->httpStatus());
        }
        $update = $db->prepare(
            "UPDATE project_adjustment_requests
             SET status='addressed',addressed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP,lock_version=lock_version+1
             WHERE id=:id AND lock_version=:version AND status='pending'"
        );
        $update->execute(['id' => $requestId, 'version' => $lockVersion]);
        if ($update->rowCount() !== 1) $this->conflict();

        (new ProjectAuditService($db))->record(
            $projectId, $actorId, 'project_adjustment_request_approved', 'project_adjustment_request', $requestId,
            ['status' => 'pending', 'lock_version' => $lockVersion],
            ['status' => 'addressed', 'decision' => 'approved', 'lock_version' => $lockVersion + 1, 'project_status' => $reopened['status']]
        );
        $this->notifyDecision($db, $projectId, $requestId, 'approved');
        $result = $this->result($db, $projectId, $requestId, 'Solicitud aprobada. La modificación quedó autorizada y el proyecto está disponible para edición.');
        $result['decision'] = 'approved';
        $result['project_status'] = $reopened['status'];
        return $result;
    }

    public function rejectInTransaction(PDO $db, int $projectId, int $requestId, int $lockVersion, string $expectedStatus, int $actorId, string $context, string $rejectionReason = ''): array
    {
        $project = $this->lockProject($db, $projectId, $expectedStatus);
        $this->requireCapability($db, $project, $actorId, $context, 'reject_adjustment_request');
        $request = $this->lockRequest($db, $projectId, $requestId, $lockVersion);
        $this->assertPendingModification($request);
        $rejectionReason = $this->rejectionReason($rejectionReason);
        $this->assertRejectionReasonColumn($db);

        $update = $db->prepare(
            "UPDATE project_adjustment_requests
             SET status='closed',closed_at=CURRENT_TIMESTAMP,closed_by=:actor,rejection_reason=:reason,updated_at=CURRENT_TIMESTAMP,lock_version=lock_version+1
             WHERE id=:id AND lock_version=:version AND status='pending'"
        );
        $update->execute(['id' => $requestId, 'version' => $lockVersion, 'actor' => $actorId, 'reason' => $rejectionReason]);
        if ($update->rowCount() !== 1) $this->conflict();

        (new ProjectAuditService($db))->record(
            $projectId, $actorId, 'project_adjustment_request_rejected', 'project_adjustment_request', $requestId,
            ['status' => 'pending', 'lock_version' => $lockVersion],
            ['status' => 'closed', 'decision' => 'rejected', 'lock_version' => $lockVersion + 1, 'project_status' => $project['status']],
            $rejectionReason
        );
        $this->notifyDecision($db, $projectId, $requestId, 'rejected', $rejectionReason);
        $result = $this->result($db, $projectId, $requestId, 'Solicitud rechazada. El proyecto conserva su estado actual.');
        $result['decision'] = 'rejected';
        $result['project_status'] = $project['status'];
        return $result;
    }

    public function listForProject(int $projectId, string $expectedStatus, int $actorId, string $context): array
    {
        $db = Database::connection();
        $project = $this->findProject($db, $projectId, $expectedStatus);
        $this->requireCapability($db, $project, $actorId, $context, 'view_adjustment_requests');
        $query = $db->prepare(
            "SELECT ar.*,u.full_name requested_by_name,c.full_name closed_by_name,
                    (SELECT l.action FROM project_audit_log l
                     WHERE l.entity_type='project_adjustment_request' AND l.entity_id=ar.id
                       AND l.action IN ('project_adjustment_request_approved','project_adjustment_request_rejected')
                     ORDER BY l.id DESC LIMIT 1) decision_action
             FROM project_adjustment_requests ar
             INNER JOIN users u ON u.id=ar.requested_by
             LEFT JOIN users c ON c.id=ar.closed_by
             WHERE ar.project_id=:project ORDER BY ar.created_at DESC,ar.id DESC"
        );
        $query->execute(['project'=>$projectId]);
        $items = $query->fetchAll();
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $items);
        $responses = [];
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0,count($ids),'?'));
            $reply = $db->prepare("SELECT r.*,u.full_name author_name FROM project_adjustment_request_responses r INNER JOIN users u ON u.id=r.author_id WHERE r.request_id IN ($placeholders) ORDER BY r.created_at,r.id");
            $reply->execute($ids);
            foreach ($reply->fetchAll() as $row) $responses[(int)$row['request_id']][] = $row;
        }
        foreach ($items as &$item) { $item['id']=(int)$item['id']; $item['lock_version']=(int)$item['lock_version']; $item['responses']=$responses[$item['id']] ?? []; $item['decision'] = match ((string)($item['decision_action'] ?? '')) { 'project_adjustment_request_approved' => 'approved', 'project_adjustment_request_rejected' => 'rejected', default => null }; }
        unset($item);
        return ['items'=>$items,'summary'=>(new ProjectAdjustmentSituationService($db))->forProject($projectId)];
    }

    public function summaryForProject(int $projectId): array { return (new ProjectAdjustmentSituationService())->forProject($projectId); }

    public function hasPendingForRequester(int $projectId, int $requesterId): bool
    {
        if ($projectId < 1 || $requesterId < 1) return false;
        $query = Database::connection()->prepare("SELECT 1 FROM project_adjustment_requests WHERE project_id=:project AND requested_by=:requester AND status='pending' LIMIT 1");
        $query->execute(['project'=>$projectId,'requester'=>$requesterId]);
        return (bool) $query->fetchColumn();
    }

    private function lockProject(PDO $db, int $id, string $expected): array
    {
        $query=$db->prepare("SELECT p.id,p.code,p.title,p.created_by,p.tutor_id,p.status,p.publication_origin,p.academic_period_id,p.is_available,p.published_at,p.deleted_at,p.withdrawn_at,
            (SELECT COUNT(*) FROM project_files f WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL) active_file_count
            FROM projects p WHERE p.id=:id FOR UPDATE"); $query->execute(['id'=>$id]);
        $project=$query->fetch(); if(!$project||!empty($project['deleted_at'])||!empty($project['withdrawn_at'])) throw new ProjectAdjustmentRequestException('El proyecto solicitado no está disponible.',404);
        if ($expected === '' || $project['status'] !== $expected) throw new ProjectAdjustmentRequestException('El estado del proyecto cambió. Recarga el expediente antes de continuar.',409);
        return $project;
    }
    private function findProject(PDO $db, int $id, string $expected): array
    {
        $query=$db->prepare("SELECT p.id,p.code,p.title,p.created_by,p.tutor_id,p.status,p.publication_origin,p.academic_period_id,p.is_available,p.published_at,p.deleted_at,p.withdrawn_at,
            (SELECT COUNT(*) FROM project_files f WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL) active_file_count
            FROM projects p WHERE p.id=:id"); $query->execute(['id'=>$id]);
        $project=$query->fetch(); if(!$project||!empty($project['deleted_at'])) throw new ProjectAdjustmentRequestException('El proyecto solicitado no existe.',404);
        if ($expected === '' || $project['status'] !== $expected) throw new ProjectAdjustmentRequestException('El estado del proyecto cambió. Recarga el expediente antes de continuar.',409);
        return $project;
    }
    private function requireCapability(PDO $db,array $project,int $actor,string $context,string $capability): void
    { if(empty((new ProjectCapabilityService())->adjustmentCapabilitiesInTransaction($db,$project,$actor,$context)[$capability])) throw new ProjectAdjustmentRequestException('No tienes autorización para realizar esta operación.',403); }
    private function assertPendingModification(array $request): void
    {
        if (($request['status'] ?? '') !== 'pending') throw new ProjectAdjustmentRequestException('La solicitud ya fue resuelta.', 409);
        if (($request['request_type'] ?? '') !== 'published_modification') throw new ProjectAdjustmentRequestException('Esta solicitud no corresponde a una modificación controlada.', 422);
    }
    private function decisionAction(PDO $db, int $requestId): ?string
    {
        $q=$db->prepare("SELECT action FROM project_audit_log WHERE entity_type='project_adjustment_request' AND entity_id=:id AND action IN ('project_adjustment_request_approved','project_adjustment_request_rejected') ORDER BY id DESC LIMIT 1");
        $q->execute(['id'=>$requestId]);$action=$q->fetchColumn();return $action===false?null:(string)$action;
    }
    private function notifyDecision(PDO $db, int $projectId, int $requestId, string $decision, ?string $rejectionReason = null): void
    {
        $students=$db->prepare("SELECT DISTINCT pp.user_id FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL WHERE pp.project_id=:project AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL");
        $students->execute(['project'=>$projectId]);
        $approved=$decision==='approved';
        $notify=$db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) VALUES(:user,:project,'adjustment',:title,:message,:url,'Abrir proyecto',:metadata,:dedup)");
        $state = $db->prepare('SELECT status FROM projects WHERE id=:project LIMIT 1');
        $state->execute(['project' => $projectId]);
        $status = (string) $state->fetchColumn();
        $published = $status === 'published';
        $rejectedMessage = 'La solicitud de modificación fue rechazada. Motivo: ' . (string)$rejectionReason . ' El proyecto conserva su estado actual.';
        foreach($students->fetchAll(PDO::FETCH_COLUMN) as $recipient){
            $notify->execute([
                'user'=>(int)$recipient,
                'project'=>$projectId,
                'title'=>$approved?'Solicitud de modificación aprobada':'Solicitud de modificación rechazada',
                'message'=>$approved?'La solicitud de modificación fue aprobada. El proyecto quedó autorizado para edición.':$rejectedMessage,
                'url'=>($published ? route('repository-detail') : route('project-detail')).'&id='.$projectId,
                'metadata'=>json_encode(['request_id'=>$requestId,'project_id'=>$projectId,'decision'=>$decision],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                'dedup'=>'adjustment-decision:'.$requestId.':'.$decision.':'.(int)$recipient
            ]);
        }
    }
    private function notifyStudentsOfCreation(PDO $db, array $project, int $requestId, string $requestType, string $context, array $actor, bool $studentActor): void
    {
        if ($studentActor) return;
        $students = $db->prepare(
            "SELECT DISTINCT pp.user_id FROM project_participants pp
             INNER JOIN users u ON u.id=pp.user_id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
             WHERE pp.project_id=:project AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL"
        );
        $students->execute(['project' => (int) $project['id']]);
        $actorName = trim((string) ($actor['name'] ?? '')) ?: 'Un docente';
        $role = (string) ($actor['role'] ?? '') === 'administrator' ? 'Administración' : 'Docente';
        $message = $actorName . ' (' . $role . ') solicitó cambios en el proyecto.';
        $notify = $db->prepare(
            "INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key)
             VALUES(:user,:project,'adjustment','Solicitud de cambios',:message,:url,'Abrir proyecto',:metadata,:dedup)"
        );
        $metadata = json_encode([
            'request_id' => $requestId,
            'project_id' => (int) $project['id'],
            'request_type' => $requestType,
            'context' => $context,
            'actor_role' => $role,
            'actor_name' => $actorName,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $url = ($context === 'repository' ? route('repository-detail') : route('project-detail')) . '&id=' . (int) $project['id'];
        foreach ($students->fetchAll(PDO::FETCH_COLUMN) as $recipient) {
            $notify->execute(['user'=>(int)$recipient,'project'=>(int)$project['id'],'message'=>$message,'url'=>$url,'metadata'=>$metadata,'dedup'=>'adjustment:'.$requestId.':'.(int)$recipient]);
        }
    }
    private function notifyAdministratorsOfCreation(PDO $db, int $projectId, int $requestId, string $requestType, string $context, array $actor, bool $studentEditable, int $excludeActor = 0): void
    {
        $details = $db->prepare(
            'SELECT ar.id, ar.project_id, ar.request_type, u.full_name requester_name,
                    p.code project_code, p.title project_title, p.status project_status
             FROM project_adjustment_requests ar
             INNER JOIN users u ON u.id=ar.requested_by
             INNER JOIN projects p ON p.id=ar.project_id
             WHERE ar.id=:request AND ar.project_id=:project AND ar.status=\'pending\''
        );
        $details->execute(['request'=>$requestId,'project'=>$projectId]);
        $request = $details->fetch();
        if (!$request) throw new ProjectAdjustmentRequestException('La solicitud pendiente no está disponible para notificar.', 409);

        $administrators = $db->query(
            "SELECT id FROM users
             WHERE is_admin=1 AND status='active' AND deleted_at IS NULL AND purged_at IS NULL"
        );
        $notify = $db->prepare(
            "INSERT IGNORE INTO notifications
                (user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key)
             VALUES(:user,:project,'adjustment',:title,:message,:url,'Gestionar solicitud',:metadata,:dedup)"
        );
        $metadata = json_encode([
            'context' => 'admin',
            'scope' => 'admin',
            'event' => 'project_adjustment_request_created',
            'request_id' => $requestId,
            'project_id' => $projectId,
            'request_type' => $requestType,
            'request_context' => $context,
            'student_editable' => $studentEditable,
            'actor_role' => (string) ($actor['role'] ?? ''),
            'project_code' => (string) $request['project_code'],
            'project_name' => (string) $request['project_title'],
            'requester_name' => (string) $request['requester_name'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $url = ((string) $request['project_status'] === 'published' ? route('repository-detail') : route('project-detail'))
            . '&id=' . $projectId . '&tab=information#projectAdjustmentListTitle';
        $message = (string) $request['requester_name'] . ' solicitó modificar el proyecto ' . (string) $request['project_code'] . '.';
        foreach ($administrators->fetchAll(PDO::FETCH_COLUMN) as $recipient) {
            if ((int) $recipient === $excludeActor) continue;
            $notify->execute([
                'user' => (int) $recipient,
                'project' => $projectId,
                'title' => 'Nueva solicitud de modificación',
                'message' => $message,
                'url' => $url,
                'metadata' => $metadata,
                'dedup' => 'adjustment-admin:' . $requestId . ':' . (int) $recipient,
            ]);
        }
    }
    private function actorProfile(PDO $db, int $actorId): array
    {
        $query = $db->prepare(
            "SELECT u.full_name, r.code
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id=u.id
             LEFT JOIN roles r ON r.id=ur.role_id
             WHERE u.id=:actor AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL"
        );
        $query->execute(['actor' => $actorId]);
        $rows = $query->fetchAll();
        $role = 'user';
        foreach (['administrator', 'teacher', 'student'] as $candidate) {
            if (count(array_filter($rows, static fn(array $row): bool => strtolower((string) ($row['code'] ?? '')) === $candidate)) > 0) {
                $role = $candidate;
                break;
            }
        }
        return ['name' => (string) ($rows[0]['full_name'] ?? 'Usuario'), 'role' => $role];
    }

    private function studentsCanEditOrdinarily(PDO $db, array $project): bool
    {
        $query = $db->prepare(
            "SELECT DISTINCT pp.user_id
             FROM project_participants pp
             INNER JOIN users u ON u.id=pp.user_id AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
             INNER JOIN student_profiles sp ON sp.user_id=pp.user_id
             WHERE pp.project_id=:project AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL"
        );
        $query->execute(['project' => (int) ($project['id'] ?? 0)]);
        $students = $query->fetchAll(PDO::FETCH_COLUMN);
        if ($students === []) return false;
        $policy = new ProjectCapabilityService();
        foreach ($students as $studentId) {
            if (empty($policy->studentEditSituation($db, $project, (int) $studentId)['can_edit_ordinary'])) return false;
        }
        return true;
    }

    private function studentCanRequestControlledModification(PDO $db, array $project, int $actorId): bool
    {
        return !empty((new ProjectCapabilityService())->studentEditSituation($db, $project, $actorId)['can_request_controlled_modification']);
    }

    private function lockRequest(PDO $db,int $project,int $id,int $version): array
    { $q=$db->prepare('SELECT * FROM project_adjustment_requests WHERE id=:id AND project_id=:project FOR UPDATE');$q->execute(['id'=>$id,'project'=>$project]);$row=$q->fetch();if(!$row)throw new ProjectAdjustmentRequestException('La solicitud administrativa no existe.',404);if($version<1||(int)$row['lock_version']!==$version)$this->conflict();return $row; }
    private function incrementVersion(PDO $db,int $id,int $version): void
    { $q=$db->prepare('UPDATE project_adjustment_requests SET updated_at=NOW(),lock_version=lock_version+1 WHERE id=:id AND lock_version=:version');$q->execute(['id'=>$id,'version'=>$version]);if($q->rowCount()!==1)$this->conflict(); }
    private function conflict(): never { throw new ProjectAdjustmentRequestException('La solicitud fue modificada mientras trabajabas. Recarga el expediente antes de continuar.',409); }
    private function rejectionReason(string $value): string { $value=trim($value);if($value===''||mb_strlen($value)<5||mb_strlen($value)>500)throw new ProjectAdjustmentRequestException('El motivo del rechazo debe contener entre 5 y 500 caracteres.',422);return $value; }
    private function assertRejectionReasonColumn(PDO $db): void
    {
        $query = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='project_adjustment_requests' AND column_name='rejection_reason'");
        $query->execute();
        if (!$query->fetchColumn()) throw new ProjectAdjustmentRequestException('La base de datos aún no está preparada para guardar el motivo del rechazo. Aplica la migración correspondiente.',503);
    }
    private function message(string $value, int $minimum = 1): string { $value=trim($value);if($value===''||mb_strlen($value)<$minimum||mb_strlen($value)>2000)throw new ProjectAdjustmentRequestException($minimum>1?'El motivo de la solicitud debe contener entre '.$minimum.' y 2000 caracteres.':'El mensaje es obligatorio y no puede superar 2000 caracteres.');return $value; }
    private function optionalText(mixed $value,int $max): ?string { $value=trim((string)$value);if($value==='')return null;if(mb_strlen($value)>$max)throw new ProjectAdjustmentRequestException('Una referencia administrativa supera la longitud permitida.');return $value; }
    private function result(PDO $db,int $project,int $id,string $message): array
    { $q=$db->prepare('SELECT * FROM project_adjustment_requests WHERE id=:id');$q->execute(['id'=>$id]);$item=$q->fetch();$item['id']=(int)$item['id'];$item['lock_version']=(int)$item['lock_version'];return ['request'=>$item,'summary'=>(new ProjectAdjustmentSituationService($db))->forProject($project),'message'=>$message]; }
}
