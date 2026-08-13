<?php

declare(strict_types=1);

/** Resuelve capacidades efectivas del expediente sin confiar en datos enviados por el cliente. */
final class ProjectCapabilityService
{
    private const KEYS = [
        'view_project', 'edit_information', 'manage_files', 'view_academic_history',
        'view_admin_history', 'change_status', 'manage_participants', 'manage_tutoring',
        'manage_tribunal', 'manage_publication', 'register_delivery', 'review_delivery',
        'create_observation', 'respond_observation', 'request_corrections', 'download_files',
        'review_documents', 'publish_project',
        'create_adjustment_request', 'view_adjustment_requests', 'respond_adjustment_request',
        'address_adjustment_request', 'close_adjustment_request',
    ];

    /** @return array<string,bool> */
    public function forCurrentUser(array $project, string $context): array
    {
        $session = new AuthSessionService();
        $access = new ProjectAccessService();
        return $this->resolve(
            $project, $context, (int) ($session->userId() ?? 0),
            $access->currentRoles(), $session->hasAdminAccess()
        );
    }

    /** @return array<string,bool> */
    public function forProjectId(int $projectId, string $context): array
    {
        if ($projectId < 1) return $this->none();
        $query = Database::connection()->prepare(
             "SELECT p.id,p.created_by,p.tutor_id,p.status,p.is_available,p.deleted_at,pt.code type_code,
             (SELECT COUNT(*) FROM project_files f WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL) active_file_count,
             (SELECT COUNT(*) FROM project_participants author WHERE author.project_id=p.id AND author.role_code='student' AND author.status='active' AND author.removed_at IS NULL) author_count
             FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id"
        );
        $query->execute(['id' => $projectId]);
        $project = $query->fetch();
        if (!$project) return $this->none();
        $participants = Database::connection()->prepare(
            "SELECT user_id,role_code,status,removed_at FROM project_participants
             WHERE project_id=:id AND status='active' AND removed_at IS NULL"
        );
        $participants->execute(['id' => $projectId]);
        $project['participants'] = $participants->fetchAll();
        return $this->forCurrentUser($project, $context);
    }

    /** @return array<string,bool> */
    public function resolve(array $project, string $context, int $userId, array $roles, bool $administrator): array
    {
        $capabilities = $this->none();
        if (!in_array($context, ['academic_management', 'academic', 'repository'], true)) return $capabilities;
        if ((int) ($project['id'] ?? 0) < 1 || !empty($project['deleted_at']) || $userId < 1) return $capabilities;

        $roles = array_values(array_unique(array_map('strtolower', array_map('strval', $roles))));
        $participants = array_values(array_filter((array) ($project['participants'] ?? []), static fn (array $participant): bool =>
            (string) ($participant['status'] ?? 'active') === 'active' && empty($participant['removed_at'])
        ));
        $related = (int) ($project['created_by'] ?? 0) === $userId
            || (int) ($project['tutor_id'] ?? 0) === $userId
            || count(array_filter($participants, static fn (array $participant): bool => (int) ($participant['user_id'] ?? 0) === $userId)) > 0;

        if ($context === 'academic_management') {
            if (!$administrator) return $capabilities;
            foreach (['view_project','edit_information','manage_files','view_academic_history','view_admin_history','change_status','request_corrections','manage_participants','manage_tutoring','manage_tribunal','manage_publication','download_files'] as $key) $capabilities[$key] = true;
            foreach (['create_adjustment_request','view_adjustment_requests','close_adjustment_request'] as $key) $capabilities[$key] = true;
            if ((string)($project['status'] ?? '') === 'development') $capabilities['manage_files'] = false;
            return $capabilities;
        }

        if ($context === 'repository') {
            $published = (string) ($project['status'] ?? '') === 'published';
            $publiclyAvailable = $published && !empty($project['is_available'])
                && (int) ($project['active_file_count'] ?? count((array) ($project['files'] ?? []))) > 0
                && (int) ($project['author_count'] ?? $this->participantCount($participants, ['student'])) > 0;
            $capabilities['view_project'] = $administrator ? $published : $publiclyAvailable;
            if (!$capabilities['view_project']) return $capabilities;
            $capabilities['view_academic_history'] = true;
            $capabilities['download_files'] = true;
            if ($administrator) foreach (['edit_information','manage_files','view_admin_history','manage_publication'] as $key) $capabilities[$key] = true;
            return $capabilities;
        }

        $isTeacher = in_array('teacher', $roles, true);
        $isStudent = in_array('student', $roles, true);
        // Proyectos activos es una bandeja de seguimiento general para Docente:
        // puede consultar cualquier expediente, sin recibir capacidades de mutación.
        // El Estudiante conserva el requisito de relación directa con el proyecto.
        if ($administrator || (!$isTeacher && (!$isStudent || !$related))) return $capabilities;
        $capabilities['view_project'] = true;
        $capabilities['view_academic_history'] = true;
        $capabilities['download_files'] = true;

        $assignedTutor = (int) ($project['tutor_id'] ?? 0) === $userId
            || count(array_filter($participants, static fn (array $participant): bool =>
                (int) ($participant['user_id'] ?? 0) === $userId
                && in_array(strtolower((string) ($participant['role_code'] ?? '')), ['tutor','co_tutor','cotutor','co-tutor'], true)
            )) > 0;
        $capabilities['review_documents'] = $isTeacher && $assignedTutor;
        // El seguimiento de Proyectos activos permite a cualquier Docente solicitar
        // y consultar ajustes, sin concederle edición ni revisión formal.
        if ($isTeacher) {
            $capabilities['create_adjustment_request'] = true;
            $capabilities['view_adjustment_requests'] = true;
        }
        $isOwnerStudent = $isStudent && count(array_filter($participants, static fn (array $participant): bool =>
            (int) ($participant['user_id'] ?? 0) === $userId && strtolower((string) ($participant['role_code'] ?? '')) === 'student'
        )) > 0;
        if ($isOwnerStudent) {
            $capabilities['view_adjustment_requests'] = true;
            $capabilities['respond_adjustment_request'] = true;
            $capabilities['address_adjustment_request'] = true;
            $type = (string) ($project['type_code'] ?? '');
            $status = (string) ($project['status'] ?? '');
            $capabilities['publish_project'] = ($type === 'thesis' && $status === 'tribunal_approved')
                || ($type !== 'thesis' && $status === 'approved');
        }

        // Los permisos académicos globales existen, pero esta pantalla aún no tiene endpoints operativos.
        $capabilities['register_delivery'] = false;
        $capabilities['review_delivery'] = false;
        $capabilities['create_observation'] = false;
        $capabilities['respond_observation'] = false;
        return $capabilities;
    }

    /** Valida la capacidad sensible con datos bloqueados por el caso de uso. */
    public function canReviewDocumentsInTransaction(PDO $db, array $project, int $userId, string $context): bool
    {
        if ($context !== 'academic' || $userId < 1 || (int) ($project['id'] ?? 0) < 1) return false;
        $identity = $db->prepare(
            "SELECT 1 FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id
             WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
               AND r.code='teacher' LIMIT 1"
        );
        $identity->execute(['user'=>$userId]);
        if (!$identity->fetchColumn()) return false;
        if ((int) ($project['tutor_id'] ?? 0) === $userId) return true;
        $assignment = $db->prepare(
            "SELECT 1 FROM project_participants
             WHERE project_id=:project AND user_id=:user AND status='active' AND removed_at IS NULL
               AND LOWER(role_code) IN ('tutor','co_tutor','cotutor','co-tutor') LIMIT 1"
        );
        $assignment->execute(['project'=>(int)$project['id'], 'user'=>$userId]);
        return (bool) $assignment->fetchColumn();
    }

    /** Confirma en datos bloqueados que el actor es estudiante participante activo. */
    public function canPublishAsActiveStudentInTransaction(PDO $db, int $projectId, int $userId): bool
    {
        if ($projectId < 1 || $userId < 1) return false;
        $statement = $db->prepare(
            "SELECT 1
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id=u.id
             INNER JOIN roles r ON r.id=ur.role_id AND r.code='student'
             INNER JOIN project_participants pp ON pp.user_id=u.id
             WHERE u.id=:user AND pp.project_id=:project
               AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
               AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL
             LIMIT 1"
        );
        $statement->execute(['user'=>$userId, 'project'=>$projectId]);
        return (bool) $statement->fetchColumn();
    }

    /** @return array<string,bool> Capacidades de ajustes recalculadas con identidad persistida. */
    public function adjustmentCapabilitiesInTransaction(PDO $db, array $project, int $userId, string $context): array
    {
        $result = $this->none();
        if ($userId < 1 || !in_array($context, ['academic_management', 'academic'], true)) return $result;
        $identity = $db->prepare(
            "SELECT u.is_admin,r.code FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id
             WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL"
        );
        $identity->execute(['user'=>$userId]);
        $rows = $identity->fetchAll();
        if ($rows === []) return $result;
        $isAdmin = count(array_filter($rows, static fn(array $row): bool => !empty($row['is_admin']) || strtolower((string)$row['code']) === 'administrator')) > 0;
        if ($context === 'academic_management' && $isAdmin) {
            foreach (['create_adjustment_request','view_adjustment_requests','close_adjustment_request'] as $key) $result[$key] = true;
            return $result;
        }
        if ($context !== 'academic' || $isAdmin) return $result;
        $roles = array_map(static fn(array $row): string => strtolower((string)$row['code']), $rows);
        $assignment = $db->prepare(
            "SELECT LOWER(role_code) FROM project_participants WHERE project_id=:project AND user_id=:user
             AND status='active' AND removed_at IS NULL"
        );
        $assignment->execute(['project'=>(int)$project['id'], 'user'=>$userId]);
        $projectRoles = array_map('strval', $assignment->fetchAll(PDO::FETCH_COLUMN));
        $teacher = in_array('teacher', $roles, true);
        $student = in_array('student', $roles, true) && in_array('student', $projectRoles, true);
        if ($teacher || $student) $result['view_adjustment_requests'] = true;
        if ($teacher) $result['create_adjustment_request'] = true;
        if ($student) {
            $result['respond_adjustment_request'] = true;
            $result['address_adjustment_request'] = true;
        }
        return $result;
    }

    /** @return array<string,bool> */
    public function none(): array
    {
        return array_fill_keys(self::KEYS, false);
    }

    private function participantCount(array $participants, array $roles): int
    {
        return count(array_filter($participants, static fn (array $participant): bool => in_array((string) ($participant['role_code'] ?? ''), $roles, true)));
    }
}
