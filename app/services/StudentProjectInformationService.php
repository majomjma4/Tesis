<?php

declare(strict_types=1);

/** Edición acotada de información académica por un estudiante participante activo. */
final class StudentProjectInformationService
{
    private const MIN_SUMMARY_LENGTH = 30;
    private const MAX_TITLE_LENGTH = 240;

    /** @param array{title:mixed,summary:mixed,tutoring_user_ids:mixed,tutoring_primary_id:mixed,author_user_ids:mixed,author_leader_id:mixed} $input */
    public function save(int $projectId, array $input, int $actorId): array
    {
        $payload = $this->normalize($projectId, $input, $actorId);
        return Database::transaction(fn (PDO $db): array => $this->saveInTransaction($db, $payload, $actorId));
    }

    /** Disponible para pruebas de integración y composición transaccional controlada. */
    public function saveInputInTransaction(PDO $db, int $projectId, array $input, int $actorId): array
    {
        return $this->saveInTransaction($db, $this->normalize($projectId, $input, $actorId), $actorId);
    }

    /** @param array{project_id:int,title:string,summary:string,tutoring_user_ids:list<int>,tutoring_primary_id:int,author_user_ids:list<int>,author_leader_id:int} $payload */
    /** Disponible para pruebas de integración dentro de una transacción exterior con rollback. */
    public function saveInTransaction(PDO $db, array $payload, int $actorId): array
    {
        $projectQuery = $db->prepare(
            'SELECT id,title,summary,tutor_id,status,deleted_at,withdrawn_at FROM projects WHERE id=:id FOR UPDATE'
        );
        $projectQuery->execute(['id' => $payload['project_id']]);
        $project = $projectQuery->fetch();
        if (!$project || !empty($project['deleted_at']) || !empty($project['withdrawn_at'])) {
            throw new StudentProjectInformationException('El proyecto solicitado no está disponible.', 404);
        }
        if ((string) $project['status'] !== 'development') {
            throw new StudentProjectInformationException('La información solo puede editarse mientras el proyecto está En desarrollo.', 422);
        }

        $participant = $db->prepare(
            "SELECT 1
             FROM project_participants pp
             INNER JOIN user_roles ur ON ur.user_id=pp.user_id
             INNER JOIN roles r ON r.id=ur.role_id AND r.code='student'
             WHERE pp.project_id=:project AND pp.user_id=:actor AND pp.role_code='student'
               AND pp.status='active' AND pp.removed_at IS NULL
             LIMIT 1 FOR UPDATE"
        );
        $participant->execute(['project' => $payload['project_id'], 'actor' => $actorId]);
        if (!$participant->fetchColumn()) {
            throw new StudentProjectInformationException('No tienes autorización para editar este proyecto.', 403);
        }
        if (!in_array($actorId, $payload['author_user_ids'], true)) {
            throw new StudentProjectInformationException('No puedes retirarte a ti mismo del proyecto desde esta opción.', 422);
        }

        $tutoring = (new ProjectTutoringService())->sync(
            $db, $payload['project_id'], $payload['tutoring_user_ids'], $payload['tutoring_primary_id'],
            $project['tutor_id'] === null ? null : (int) $project['tutor_id']
        );
        $authors = (new ProjectAuthorService())->sync(
            $db, $payload['project_id'], $payload['author_user_ids'], $payload['author_leader_id']
        );
        $newTutorId = (int) $tutoring['after_primary'];
        $fieldChanges = [];
        foreach (['title' => $payload['title'], 'summary' => $payload['summary'], 'tutor_id' => $newTutorId] as $field => $newValue) {
            $oldValue = $project[$field] ?? null;
            if ($field === 'tutor_id') {
                $oldValue = $oldValue === null ? null : (int) $oldValue;
            } else {
                $oldValue = $oldValue === null ? null : (string) $oldValue;
            }
            if ($oldValue !== $newValue) $fieldChanges[$field] = [$oldValue, $newValue];
        }
        if ($fieldChanges === [] && empty($tutoring['changed']) && empty($authors['changed'])) {
            return ['changed' => false, 'project_id' => $payload['project_id']];
        }

        $db->prepare('UPDATE projects SET title=:title,summary=:summary,tutor_id=:tutor,updated_at=CURRENT_TIMESTAMP WHERE id=:id')
            ->execute(['title' => $payload['title'], 'summary' => $payload['summary'], 'tutor' => $newTutorId, 'id' => $payload['project_id']]);

        [$previous, $next, $history] = $this->auditStates($fieldChanges, $tutoring, $authors);
        $next['_history_changes'] = $history;
        $auditId = (new ProjectAuditService($db))->record(
            $payload['project_id'], $actorId, 'project_updated', 'project', $payload['project_id'], $previous, $next
        );

        return ['changed' => true, 'project_id' => $payload['project_id'], 'audit_id' => $auditId];
    }

    private function normalize(int $projectId, array $input, int $actorId): array
    {
        if ($projectId < 1 || $actorId < 1) throw new StudentProjectInformationException('La solicitud no es válida.', 422);
        $title = trim((string) ($input['title'] ?? ''));
        if (mb_strlen($title) < 5) throw new StudentProjectInformationException('Ingresa un título de al menos cinco caracteres.');
        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) throw new StudentProjectInformationException('El título no puede superar 240 caracteres.');
        $summary = trim((string) ($input['summary'] ?? ''));
        if (mb_strlen($summary) < self::MIN_SUMMARY_LENGTH) throw new StudentProjectInformationException('Describe el proyecto con al menos 30 caracteres.');
        return [
            'project_id' => $projectId,
            'title' => $title,
            'summary' => $summary,
            'tutoring_user_ids' => $this->ids($input['tutoring_user_ids'] ?? []),
            'tutoring_primary_id' => (int) ($input['tutoring_primary_id'] ?? 0),
            'author_user_ids' => $this->ids($input['author_user_ids'] ?? []),
            'author_leader_id' => (int) ($input['author_leader_id'] ?? 0),
        ];
    }

    /** @return list<int> */
    private function ids(mixed $raw): array
    {
        if (!is_array($raw)) throw new StudentProjectInformationException('La lista de participantes no es válida.');
        return array_values(array_map('intval', $raw));
    }

    private function auditStates(array $fields, array $tutoring, array $authors): array
    {
        $labels = ['title' => 'Título', 'summary' => 'Descripción'];
        $previous = [];
        $next = [];
        $history = [];
        foreach ($fields as $field => [$old, $new]) {
            if ($field === 'tutor_id' && !empty($tutoring['changed'])) continue;
            $label = $labels[$field] ?? $field;
            $from = $old === null || $old === '' ? 'Sin asignar' : (string) $old;
            $to = $new === null || $new === '' ? 'Sin asignar' : (string) $new;
            $previous[$label] = $from;
            $next[$label] = $to;
            $history[] = ['field' => $label, 'verb' => 'cambiado', 'from' => $from, 'to' => $to];
        }
        if (!empty($tutoring['changed'])) {
            $from = implode(', ', array_column($tutoring['before'], 'name')) ?: 'Sin tutoría';
            $to = implode(', ', array_column($tutoring['after'], 'name'));
            $previous['Tutoría'] = $from;
            $next['Tutoría'] = $to;
            $next['_tutoring'] = ['added' => $tutoring['added'], 'removed' => $tutoring['removed'], 'previous_reference' => $tutoring['before_primary'], 'new_reference' => $tutoring['after_primary']];
            $history[] = ['field' => 'Tutoría', 'verb' => 'actualizada', 'from' => $from, 'to' => $to];
        }
        if (!empty($authors['changed'])) {
            $from = implode(', ', array_column($authors['before'], 'name')) ?: 'Sin autores';
            $to = implode(', ', array_column($authors['after'], 'name'));
            $previous['Autores'] = $from;
            $next['Autores'] = $to;
            $next['_authors'] = ['added' => $authors['added'], 'removed' => $authors['removed'], 'previous_leader' => $authors['before_leader'], 'new_leader' => $authors['after_leader']];
            $history[] = ['field' => 'Autores', 'verb' => 'actualizados', 'from' => $from, 'to' => $to];
        }
        return [$previous, $next, $history];
    }
}
