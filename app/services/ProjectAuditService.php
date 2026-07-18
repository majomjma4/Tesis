<?php

declare(strict_types=1);

/** Registra cambios persistentes del expediente dentro de la misma transacción de negocio. */
final class ProjectAuditService
{
    public function __construct(private readonly ?PDO $db = null) {}

    public function record(int $projectId, ?int $userId, string $action, string $entityType, ?int $entityId, ?array $previousState = null, ?array $newState = null, ?string $reason = null): int
    {
        if ($projectId < 1 || $action === '' || $entityType === '') {
            throw new InvalidArgumentException('Los datos de auditoría son incompletos.');
        }
        $connection = $this->db ?? Database::connection();
        $statement = $connection->prepare(
            'INSERT INTO project_audit_log
             (project_id, user_id, action, entity_type, entity_id, previous_state, new_state, reason, ip_address, user_agent)
             VALUES (:project_id, :user_id, :action, :entity_type, :entity_id, :previous_state, :new_state, :reason, :ip_address, :user_agent)'
        );
        $statement->execute([
            'project_id' => $projectId, 'user_id' => $userId, 'action' => $action,
            'entity_type' => $entityType, 'entity_id' => $entityId,
            'previous_state' => $previousState === null ? null : json_encode($previousState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'new_state' => $newState === null ? null : json_encode($newState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'ip_address' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
        return (int) $connection->lastInsertId();
    }
}
