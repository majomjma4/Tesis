<?php
declare(strict_types=1);

final class ThesisPublicationException extends InvalidArgumentException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->status;
    }
}

final class ThesisPublicationService
{
    public function publish(int $id, string $expected, int $actor): array
    {
        return Database::transaction(fn (PDO $db) => $this->publishTx($db, $id, $expected, $actor));
    }

    private function publishTx(PDO $db, int $id, string $expected, int $actor): array
    {
        $q = $db->prepare("SELECT p.id,p.code,p.status,pt.code type_code FROM projects p JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id AND p.deleted_at IS NULL FOR UPDATE");
        $q->execute(['id' => $id]);
        $project = $q->fetch();
        if (!$project || (string) $project['type_code'] !== 'thesis') throw new ThesisPublicationException('El proyecto de Titulación solicitado no está disponible.', 404);
        if ((string) $project['status'] !== 'tribunal_approved') throw new ThesisPublicationException('El proyecto cambió de estado y no puede publicarse.', 409);
        if ($expected !== 'tribunal_approved') throw new ThesisPublicationException('El estado esperado no es válido.', 409);

        $defense = $db->prepare('SELECT result FROM project_defenses WHERE project_id=:id ORDER BY attempt_number DESC LIMIT 1 FOR UPDATE');
        $defense->execute(['id' => $id]);
        if ((string) $defense->fetchColumn() !== 'approved') throw new ThesisPublicationException('El resultado del Tribunal no está aprobado.');

        $tribunal = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM project_participants WHERE project_id=:id AND status='active' AND removed_at IS NULL AND LOWER(role_code) IN ('tribunal','jury') FOR UPDATE");
        $tribunal->execute(['id' => $id]);
        if (!ThesisTribunalService::isValidMemberCount((int) $tribunal->fetchColumn())) throw new ThesisPublicationException('El Tribunal no tiene una composición válida de ' . ThesisTribunalService::memberRangeLabel() . ' miembros activos.');

        $result = (new ProjectStatusTransitionService())->transitionInTransaction($db, $id, 'tribunal_approved', 'published', '', $actor, 'thesis_management');
        $this->notify($db, $id, (string) $project['code']);
        return $result;
    }

    private function notify(PDO $db, int $id, string $code): void
    {
        $q = $db->prepare("INSERT IGNORE INTO notifications(user_id,project_id,type,title,message,action_url,action_label,metadata,deduplication_key) SELECT DISTINCT pp.user_id,:project,'repository','Proyecto publicado',:message,:url,'Ver en Repositorio',:metadata,CONCAT('thesis-published:',:project,':',pp.user_id) FROM project_participants pp JOIN users u ON u.id=pp.user_id WHERE pp.project_id=:project AND pp.status='active' AND pp.removed_at IS NULL AND LOWER(pp.role_code) IN ('student','tutor','cotutor','co_tutor','co-tutor') AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL");
        $q->execute([
            'project' => $id,
            'message' => 'El trabajo de titulación ha sido publicado en el Repositorio institucional.',
            'url' => route('repository-detail') . '&id=' . $id,
            'metadata' => json_encode(['project_code' => $code, 'publication' => 'institutional'], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
