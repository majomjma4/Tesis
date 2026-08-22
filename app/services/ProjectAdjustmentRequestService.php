<?php

declare(strict_types=1);

/** Única vía de escritura para las solicitudes administrativas de ajuste. */
final class ProjectAdjustmentRequestService
{
    private const TYPES = ['incomplete_information','incorrect_data','inconsistency','other'];

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

    public function createInTransaction(PDO $db, int $projectId, string $expectedStatus, int $actorId, string $context, array $data): array
    {
        $project = $this->lockProject($db, $projectId, $expectedStatus);
        $this->requireCapability($db, $project, $actorId, $context, 'create_adjustment_request');
        $type = strtolower(trim((string)($data['request_type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) throw new ProjectAdjustmentRequestException('El tipo de solicitud administrativa no es válido.');
        $message = $this->message((string)($data['message'] ?? ''));
        $section = $this->optionalText($data['related_section'] ?? null, 100);
        $field = null;
        $fileId = empty($data['file_id']) ? null : (int)$data['file_id'];
        if ($fileId !== null) {
            $file = $db->prepare('SELECT id FROM project_files WHERE id=:file AND project_id=:project AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');
            $file->execute(['file'=>$fileId, 'project'=>$projectId]);
            if (!$file->fetchColumn()) throw new ProjectAdjustmentRequestException('El archivo relacionado no pertenece al proyecto o ya no está activo.');
        }
        $insert = $db->prepare(
            "INSERT INTO project_adjustment_requests(project_id,requested_by,request_type,message,related_section,related_field,file_id,status)
             VALUES(:project,:actor,:type,:message,:section,:field,:file,'pending')"
        );
        $insert->execute(['project'=>$projectId,'actor'=>$actorId,'type'=>$type,'message'=>$message,'section'=>$section,'field'=>$field,'file'=>$fileId]);
        $id = (int)$db->lastInsertId();
        (new ProjectAuditService($db))->record($projectId, $actorId, 'project_adjustment_request_created', 'project_adjustment_request', $id, null, ['status'=>'pending','request_type'=>$type,'file_id'=>$fileId]);
        $students = $db->prepare("SELECT DISTINCT user_id FROM project_participants WHERE project_id=:project AND role_code='student' AND status='active' AND removed_at IS NULL");
        $students->execute(['project'=>$projectId]);
        $notify = $db->prepare(
            "INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key)
             VALUES(:user,:project,'adjustment','Solicitud de ajuste de información',:message,:url,'Abrir proyecto',:metadata,:dedup)"
        );
        foreach ($students->fetchAll(PDO::FETCH_COLUMN) as $recipient) {
            $notify->execute(['user'=>(int)$recipient,'project'=>$projectId,'message'=>'Se ha registrado una solicitud de ajuste para corregir información del proyecto.','url'=>route('project-detail').'&id='.$projectId,
                'metadata'=>json_encode(['request_id'=>$id,'project_id'=>$projectId], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR), 'dedup'=>'adjustment:'.$id.':'.(int)$recipient]);
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

    public function listForProject(int $projectId, string $expectedStatus, int $actorId, string $context): array
    {
        $db = Database::connection();
        $project = $this->findProject($db, $projectId, $expectedStatus);
        $this->requireCapability($db, $project, $actorId, $context, 'view_adjustment_requests');
        $query = $db->prepare('SELECT ar.*,u.full_name requested_by_name,c.full_name closed_by_name FROM project_adjustment_requests ar INNER JOIN users u ON u.id=ar.requested_by LEFT JOIN users c ON c.id=ar.closed_by WHERE ar.project_id=:project ORDER BY ar.created_at DESC,ar.id DESC');
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
        foreach ($items as &$item) { $item['id']=(int)$item['id']; $item['lock_version']=(int)$item['lock_version']; $item['responses']=$responses[$item['id']] ?? []; }
        unset($item);
        return ['items'=>$items,'summary'=>(new ProjectAdjustmentSituationService($db))->forProject($projectId)];
    }

    public function summaryForProject(int $projectId): array { return (new ProjectAdjustmentSituationService())->forProject($projectId); }

    private function lockProject(PDO $db, int $id, string $expected): array
    {
        $query=$db->prepare('SELECT id,tutor_id,status,deleted_at FROM projects WHERE id=:id FOR UPDATE'); $query->execute(['id'=>$id]);
        $project=$query->fetch(); if(!$project||!empty($project['deleted_at'])) throw new ProjectAdjustmentRequestException('El proyecto solicitado no existe.',404);
        if ($expected === '' || $project['status'] !== $expected) throw new ProjectAdjustmentRequestException('El estado del proyecto cambió. Recarga el expediente antes de continuar.',409);
        return $project;
    }
    private function findProject(PDO $db, int $id, string $expected): array
    {
        $query=$db->prepare('SELECT id,tutor_id,status,deleted_at FROM projects WHERE id=:id'); $query->execute(['id'=>$id]);
        $project=$query->fetch(); if(!$project||!empty($project['deleted_at'])) throw new ProjectAdjustmentRequestException('El proyecto solicitado no existe.',404);
        if ($expected === '' || $project['status'] !== $expected) throw new ProjectAdjustmentRequestException('El estado del proyecto cambió. Recarga el expediente antes de continuar.',409);
        return $project;
    }
    private function requireCapability(PDO $db,array $project,int $actor,string $context,string $capability): void
    { if(empty((new ProjectCapabilityService())->adjustmentCapabilitiesInTransaction($db,$project,$actor,$context)[$capability])) throw new ProjectAdjustmentRequestException('No tienes autorización para realizar esta operación.',403); }
    private function lockRequest(PDO $db,int $project,int $id,int $version): array
    { $q=$db->prepare('SELECT * FROM project_adjustment_requests WHERE id=:id AND project_id=:project FOR UPDATE');$q->execute(['id'=>$id,'project'=>$project]);$row=$q->fetch();if(!$row)throw new ProjectAdjustmentRequestException('La solicitud administrativa no existe.',404);if($version<1||(int)$row['lock_version']!==$version)$this->conflict();return $row; }
    private function incrementVersion(PDO $db,int $id,int $version): void
    { $q=$db->prepare('UPDATE project_adjustment_requests SET updated_at=NOW(),lock_version=lock_version+1 WHERE id=:id AND lock_version=:version');$q->execute(['id'=>$id,'version'=>$version]);if($q->rowCount()!==1)$this->conflict(); }
    private function conflict(): never { throw new ProjectAdjustmentRequestException('La solicitud fue modificada mientras trabajabas. Recarga el expediente antes de continuar.',409); }
    private function message(string $value): string { $value=trim($value);if($value===''||mb_strlen($value)>2000)throw new ProjectAdjustmentRequestException('El mensaje es obligatorio y no puede superar 2000 caracteres.');return $value; }
    private function optionalText(mixed $value,int $max): ?string { $value=trim((string)$value);if($value==='')return null;if(mb_strlen($value)>$max)throw new ProjectAdjustmentRequestException('Una referencia administrativa supera la longitud permitida.');return $value; }
    private function result(PDO $db,int $project,int $id,string $message): array
    { $q=$db->prepare('SELECT * FROM project_adjustment_requests WHERE id=:id');$q->execute(['id'=>$id]);$item=$q->fetch();$item['id']=(int)$item['id'];$item['lock_version']=(int)$item['lock_version'];return ['request'=>$item,'summary'=>(new ProjectAdjustmentSituationService($db))->forProject($project),'message'=>$message]; }
}
