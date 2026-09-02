<?php

declare(strict_types=1);

/** Resuelve capacidades efectivas del expediente sin confiar en datos enviados por el cliente. */
final class ProjectCapabilityService
{
    public const REPOSITORY_DIRECT_PUBLISH = 'repository_direct_publish';
    public const INSTITUTIONAL_ACTIVE_STATUSES = ['development', 'under_review', 'approved', 'defense', 'tribunal_approved'];
    public const INSTITUTIONAL_ARCHIVE_EXTENSIONS = ['zip', 'rar', '7z', 'tar', 'gz'];

    /** SQL de elegibilidad para el listado de proyectos actualmente activos. */
    public static function institutionalActiveProjectEligibilityWhere(string $alias = 'p'): string
    {
        self::assertProjectAlias($alias);

        return "{$alias}.deleted_at IS NULL
            AND {$alias}.withdrawn_at IS NULL
            AND {$alias}.publication_origin = 'workflow'
            AND EXISTS (
                SELECT 1
                FROM project_participants active_student
                INNER JOIN student_profiles active_student_profile
                    ON active_student_profile.user_id=active_student.user_id
                INNER JOIN users active_student_user
                    ON active_student_user.id=active_student.user_id
                WHERE active_student.project_id={$alias}.id
                  AND active_student.role_code='student'
                  AND active_student.status='active'
                  AND active_student.removed_at IS NULL
                  AND active_student_user.status='active'
                  AND active_student_user.deleted_at IS NULL
                  AND active_student_user.purged_at IS NULL
            )";
    }

    /** SQL del subconjunto que sigue en flujo académico. */
    public static function institutionalActiveProjectWhere(string $alias = 'p'): string
    {
        return self::institutionalActiveProjectEligibilityWhere($alias)
            . " AND {$alias}.status IN ('development','under_review','approved','defense','tribunal_approved')";
    }

    /** SQL para registros históricos no eliminados ni retirados. */
    public static function institutionalHistoricalProjectWhere(string $alias = 'p'): string
    {
        self::assertProjectAlias($alias);
        return "{$alias}.deleted_at IS NULL AND {$alias}.withdrawn_at IS NULL";
    }

    /** SQL del universo publicado, alineado con el Repositorio administrativo. */
    public static function institutionalPublishedProjectWhere(string $alias = 'p'): string
    {
        $historical = self::institutionalHistoricalProjectWhere($alias);
        return $historical . "
            AND {$alias}.status='published'
            AND EXISTS (
                SELECT 1
                FROM project_participants published_student
                INNER JOIN student_profiles published_student_profile
                    ON published_student_profile.user_id=published_student.user_id
                INNER JOIN users published_student_user
                    ON published_student_user.id=published_student.user_id
                WHERE published_student.project_id={$alias}.id
                  AND published_student.role_code='student'
                  AND published_student.status='active'
                  AND published_student.removed_at IS NULL
                  AND published_student_user.status='active'
                  AND published_student_user.deleted_at IS NULL
                  AND published_student_user.purged_at IS NULL
            )
            AND EXISTS (
                SELECT 1 FROM project_files published_file
                WHERE published_file.project_id={$alias}.id
                  AND published_file.deleted_at IS NULL
                  AND published_file.purged_at IS NULL
            )";
    }

    private static function assertProjectAlias(string $alias): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException('Alias de proyecto no válido.');
        }
    }

    private const KEYS = [
        'view_project', 'edit_information', 'manage_files', 'view_academic_history',
        'view_admin_history', 'change_status', 'manage_participants', 'manage_tutoring',
        'manage_tribunal', 'manage_publication', 'register_delivery', 'review_delivery',
        'create_observation', 'respond_observation', 'request_corrections', 'download_files',
        'review_documents', 'publish_project', 'view_institutional_files',
        'manage_workspace_files', 'send_for_review',
        'create_adjustment_request', 'view_adjustment_requests', 'respond_adjustment_request',
        'address_adjustment_request', 'close_adjustment_request',
        'approve_adjustment_request', 'reject_adjustment_request', 'manage_adjustment_requests',
        'manage_own_repository_content', 'manage_own_repository_status',
    ];

    private function isRelatedTeacher(PDO $db, array $project, int $userId): bool
    {
        if ($userId < 1) return false;
        if ((int) ($project['tutor_id'] ?? 0) === $userId) return true;
        $query = $db->prepare(
            "SELECT 1 FROM project_participants
             WHERE project_id=:project AND user_id=:user
               AND status='active' AND removed_at IS NULL
               AND LOWER(role_code) IN ('tutor','cotutor','co_tutor','co-tutor')
             LIMIT 1"
        );
        $query->execute(['project' => (int) ($project['id'] ?? 0), 'user' => $userId]);
        return (bool) $query->fetchColumn();
    }

    /** @return array<string,bool> */
    public function forCurrentUser(array $project, string $context): array
    {
        $session = new AuthSessionService();
        $access = new ProjectAccessService();
        $userId = (int) ($session->userId() ?? 0);
        $roles = $access->currentRoles();
        $administrator = $session->isAdminModeActive();
        $resolved = $this->resolve(
            $project, $context, $userId, $roles, $administrator
        );
        return $resolved;
    }

    /** Capability explícita para el motor de publicación directa del Repository. */
    public function canPublishDirectRepository(?AuthSessionService $session = null): bool
    {
        $session ??= new AuthSessionService();
        if (!$session->isAuthenticated() || $session->isAdminModeActive()) return false;
        $userId = (int) ($session->userId() ?? 0);
        if ($userId < 1) return false;
        $roles = array_map('strtolower', array_map('strval', (new ProjectAccessService())->currentRoles()));
        if (!in_array('teacher', $roles, true)) return false;
        $query = Database::connection()->prepare("SELECT 1 FROM users u INNER JOIN teacher_profiles tp ON tp.user_id=u.id INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher' WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL LIMIT 1");
        $query->execute(['user'=>$userId]);
        return (bool) $query->fetchColumn();
    }

    /** @return array<string,bool> */
    public function forProjectId(int $projectId, string $context): array
    {
        $project = $this->projectForId($projectId, $context === 'repository_owner');
        return $project === null ? $this->none() : $this->forCurrentUser($project, $context);
    }

    /**
     * Authorizes access to a project resource before an endpoint reads history,
     * files, previews or ZIP contents. Generic tracking capabilities for a
     * teacher are intentionally broader and must not be used for this check.
     */
    public function canViewProjectResource(int $projectId, string $context, string $resource = 'private'): bool
    {
        if (!in_array($context, ['academic_management', 'academic', 'repository', 'repository_owner'], true)) return false;
        $project = $this->projectForId($projectId, $context === 'repository_owner');
        if ($project === null) return false;

        $session = new AuthSessionService();
        $access = new ProjectAccessService();
        $userId = (int) ($session->userId() ?? 0);
        if ($userId < 1) return false;
        if ($context === 'repository_owner') {
            return !$session->isAdminModeActive()
                && $this->isOwnedRepositoryReadOnlyProject($project, $userId, $access->currentRoles(), false);
        }
        if ($session->isAdminModeActive()) return true;
        if ($context === 'academic_management') return false;
        if ($context !== 'repository'
            && (string) ($project['publication_origin'] ?? ProjectPublicationOrigin::WORKFLOW) === ProjectPublicationOrigin::DIRECT_REPOSITORY) return false;
        if (!empty($project['withdrawn_at'])) return false;
        if ($context === 'repository') return !empty($this->resolve($project, $context, $userId, $access->currentRoles(), false)['view_project']);

        $roles = array_map('strtolower', array_map('strval', $access->currentRoles()));
        $participants = (array) ($project['participants'] ?? []);
        if (in_array('teacher', $roles, true)) {
            if ((int) ($project['tutor_id'] ?? 0) === $userId) return true;
            foreach ($participants as $participant) {
                if ((int) ($participant['user_id'] ?? 0) !== $userId) continue;
                if (in_array(strtolower((string) ($participant['role_code'] ?? '')), ['tutor', 'cotutor', 'co_tutor', 'co-tutor', 'tribunal', 'jury'], true)) return true;
            }
            return false;
        }
        if (!in_array('student', $roles, true)) return false;
        foreach ($participants as $participant) {
            if ((int) ($participant['user_id'] ?? 0) === $userId && strtolower((string) ($participant['role_code'] ?? '')) === 'student') return true;
        }
        return false;
    }

    /** Permite sólo la consulta del contenido directo ocultado por su Teacher propietario. */
    public function canViewOwnedRepositoryProject(array $project, int $userId, array $roles, bool $administrator = false): bool
    {
        return $this->isOwnedRepositoryReadOnlyProject($project, $userId, $roles, $administrator);
    }

    /** Lectura institucional del catálogo docente; no concede capacidades de gestión. */
    public function canViewActiveProject(int $projectId, string $context = 'academic'): bool
    {
        if ($context !== 'academic' || $projectId < 1) return false;
        $session = new AuthSessionService();
        $access = new ProjectAccessService();
        if ($session->isAdminModeActive() || (int) ($session->userId() ?? 0) < 1) return false;
        $roles = array_map('strtolower', array_map('strval', $access->currentRoles()));
        if (!in_array('teacher', $roles, true)) return false;
        $project = $this->projectForId($projectId);
        return $project !== null && $this->isInstitutionallyVisibleActiveProject($project);
    }

    /** Authorizes only the current, institutionally visible file. */
    public function canViewInstitutionalFile(int $fileId, string $context = 'academic'): bool
    {
        if ($context !== 'academic' || $fileId < 1) return false;
        $session = new AuthSessionService();
        $access = new ProjectAccessService();
        if ($session->isAdminModeActive() || (int) ($session->userId() ?? 0) < 1) return false;
        $roles = array_map('strtolower', array_map('strval', $access->currentRoles()));
        if (!in_array('teacher', $roles, true)) return false;

        $statement = Database::connection()->prepare(
            "SELECT project_id, extension FROM project_files
             WHERE id=:file AND deleted_at IS NULL AND purged_at IS NULL LIMIT 1"
        );
        $statement->execute(['file' => $fileId]);
        $file = $statement->fetch();
        if (!$file || in_array(strtolower((string) ($file['extension'] ?? '')), self::INSTITUTIONAL_ARCHIVE_EXTENSIONS, true)) return false;

        return $this->canViewActiveProject((int) $file['project_id'], $context);
    }

    private function projectForId(int $projectId, bool $includeDeleted = false): ?array
    {
        if ($projectId < 1) return null;
        $query = Database::connection()->prepare(
             "SELECT p.id,p.created_by,p.tutor_id,p.status,p.publication_origin,p.is_available,p.withdrawn_at,p.deleted_at,pt.code type_code,
             (SELECT COUNT(*) FROM project_files f WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL) active_file_count,
             (SELECT COUNT(*) FROM project_participants author WHERE author.project_id=p.id AND author.role_code='student' AND author.status='active' AND author.removed_at IS NULL) author_count
             FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id=:id"
        );
        $query->execute(['id' => $projectId]);
        $project = $query->fetch();
        if (!$project || (!$includeDeleted && !empty($project['deleted_at']))) return null;
        $participants = Database::connection()->prepare(
            "SELECT user_id,role_code,status,removed_at FROM project_participants
             WHERE project_id=:id AND status='active' AND removed_at IS NULL"
        );
        $participants->execute(['id' => $projectId]);
        $project['participants'] = $participants->fetchAll();
        return $project;
    }

    /** @param list<int> $projectIds @return array<int,array<string,bool>> */
    public function forProjectIds(array $projectIds, string $context): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $db = Database::connection();
        $projects = $db->prepare("SELECT p.id,p.created_by,p.tutor_id,p.status,p.publication_origin,p.is_available,p.withdrawn_at,p.deleted_at,pt.code type_code,
            (SELECT COUNT(*) FROM project_files f WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL) active_file_count,
            (SELECT COUNT(*) FROM project_participants author WHERE author.project_id=p.id AND author.role_code='student' AND author.status='active' AND author.removed_at IS NULL) author_count
            FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id WHERE p.id IN ($marks)");
        $projects->execute($ids);
        $rows = [];
        foreach ($projects->fetchAll() as $project) $rows[(int)$project['id']] = $project;
        $participants = $db->prepare("SELECT project_id,user_id,role_code,status,removed_at FROM project_participants WHERE project_id IN ($marks) AND status='active' AND removed_at IS NULL");
        $participants->execute($ids);
        foreach ($participants->fetchAll() as $participant) $rows[(int)$participant['project_id']]['participants'][] = $participant;
        $session = new AuthSessionService();
        $access = new ProjectAccessService();
        $userId = (int) ($session->userId() ?? 0);
        $roles = $access->currentRoles();
        $administrator = $session->isAdminModeActive();
        $result = [];
        foreach ($ids as $id) {
            $project = $rows[$id] ?? null;
            $result[$id] = is_array($project) ? $this->resolve($project, $context, $userId, $roles, $administrator) : $this->none();
        }
        return $result;
    }

    /** @return array<string,bool> */
    public function resolve(array $project, string $context, int $userId, array $roles, bool $administrator): array
    {
        $capabilities = $this->none();
        if (!in_array($context, ['academic_management', 'academic', 'repository', 'repository_owner'], true)) return $capabilities;
        if ((int) ($project['id'] ?? 0) < 1 || $userId < 1) return $capabilities;
        if ($context === 'repository_owner') {
            if (!$this->isOwnedRepositoryReadOnlyProject($project, $userId, $roles, $administrator)) return $capabilities;
            $capabilities['view_project'] = true;
            $capabilities['download_files'] = true;
            $capabilities['download_academic_package'] = true;
            $capabilities['view_institutional_files'] = true;
            return $capabilities;
        }
        if (!empty($project['deleted_at'])) return $capabilities;
        if (!$administrator && !empty($project['withdrawn_at'])) return $capabilities;
        if ($context !== 'repository'
            && (string) ($project['publication_origin'] ?? ProjectPublicationOrigin::WORKFLOW) === ProjectPublicationOrigin::DIRECT_REPOSITORY) {
            return $capabilities;
        }

        $roles = array_values(array_unique(array_map('strtolower', array_map('strval', $roles))));
        $participants = array_values(array_filter((array) ($project['participants'] ?? []), static fn (array $participant): bool =>
            (string) ($participant['status'] ?? 'active') === 'active' && empty($participant['removed_at'])
        ));
        $related = (int) ($project['created_by'] ?? 0) === $userId
            || (int) ($project['tutor_id'] ?? 0) === $userId
            || count(array_filter($participants, static fn (array $participant): bool => (int) ($participant['user_id'] ?? 0) === $userId)) > 0;

        if ($context === 'academic_management') {
            if (!$administrator) return $capabilities;
            foreach (['view_project','edit_information','manage_files','view_academic_history','view_admin_history','change_status','request_corrections','manage_participants','manage_tutoring','manage_tribunal','manage_publication','download_files','download_academic_package','view_institutional_files'] as $key) $capabilities[$key] = true;
            foreach (['create_adjustment_request','view_adjustment_requests','close_adjustment_request','approve_adjustment_request','reject_adjustment_request','manage_adjustment_requests'] as $key) $capabilities[$key] = true;
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
            // El permiso de lectura acompaña al acceso al repositorio. La existencia
            // de contenido real la decide el timeline; los proyectos directos no
            // producen eventos académicos y la vista no crea una pestaña vacía.
            $capabilities['view_academic_history'] = true;
            $capabilities['download_files'] = true;
            $capabilities['download_academic_package'] = true;
            $capabilities['view_institutional_files'] = true;
            if ($administrator) $capabilities['view_admin_history'] = true;
            $isDirectRepositoryOwner = !$administrator
                && in_array('teacher', $roles, true)
                && (string) ($project['publication_origin'] ?? '') === ProjectPublicationOrigin::DIRECT_REPOSITORY
                && (int) ($project['created_by'] ?? 0) === $userId
                && $this->isActiveTeacher($userId);
            if ($isDirectRepositoryOwner) {
                $capabilities['edit_information'] = true;
                $capabilities['manage_files'] = true;
                $capabilities['manage_own_repository_content'] = true;
                $capabilities['manage_own_repository_status'] = true;
            }
            $studentParticipant = in_array('student', $roles, true)
                && count(array_filter($participants, static fn (array $participant): bool =>
                    (int) ($participant['user_id'] ?? 0) === $userId
                    && strtolower((string) ($participant['role_code'] ?? '')) === 'student'
                    && (!array_key_exists('is_student', $participant) || !empty($participant['is_student']))
                )) > 0;
            $administrativeIdentity = in_array('administrator', $roles, true);
            $teacherActor = !$administrator
                && in_array('teacher', $roles, true)
                && $this->isActiveTeacher($userId);
            $studentActor = !$administrator && !$administrativeIdentity && $studentParticipant;
            if (($studentActor || $teacherActor)
                && (string) ($project['publication_origin'] ?? '') === ProjectPublicationOrigin::WORKFLOW) {
                $capabilities['create_adjustment_request'] = true;
            }
            return $capabilities;
        }

        $administrativeIdentity = in_array('administrator', $roles, true);
        // Un usuario con doble rol actúa como Docente mientras no esté en
        // Admin Mode; la identidad administrativa solo gobierna ese modo.
        $isTeacher = in_array('teacher', $roles, true) && !$administrator;
        $isStudent = in_array('student', $roles, true);
        // Proyectos activos es una bandeja de seguimiento general para Docente:
        // puede consultar cualquier expediente, sin recibir capacidades de mutación.
        // El Estudiante conserva el requisito de relación directa con el proyecto.
        $activeStudentParticipant = $isStudent && count(array_filter($participants, static fn (array $participant): bool =>
            (int) ($participant['user_id'] ?? 0) === $userId
            && strtolower((string) ($participant['role_code'] ?? '')) === 'student'
        )) > 0;
        if ($administrator || (!$isTeacher && (!$isStudent || !$activeStudentParticipant))) return $capabilities;
        if ($isTeacher && !$this->isInstitutionallyVisibleActiveProject($project) && !$related) return $capabilities;
        $capabilities['view_project'] = true;
        $capabilities['view_academic_history'] = !$isTeacher;
        $capabilities['view_institutional_files'] = $isTeacher || $isStudent;
        $capabilities['download_files'] = !$isTeacher || $related;
        $capabilities['download_academic_package'] = !$isTeacher || $related;

        $assignedTutor = (int) ($project['tutor_id'] ?? 0) === $userId
            || count(array_filter($participants, static fn (array $participant): bool =>
                (int) ($participant['user_id'] ?? 0) === $userId
                && in_array(strtolower((string) ($participant['role_code'] ?? '')), ['tutor','co_tutor','cotutor','co-tutor'], true)
            )) > 0;
        if ($isTeacher && $assignedTutor) $capabilities['view_academic_history'] = true;
        if ($isTeacher) $capabilities['download_files'] = $assignedTutor;
        if ($isTeacher) $capabilities['download_academic_package'] = true;
        $capabilities['review_documents'] = $isTeacher && $assignedTutor;
        // El seguimiento de Proyectos activos permite a cualquier Docente solicitar
        // y consultar ajustes, sin concederle edición ni revisión formal.
        if ($isTeacher) {
            // Cualquier docente con acceso institucional legítimo puede pedir
            // cambios; no se limita esta acción a tutor/cotutor.
            $capabilities['create_adjustment_request'] = $this->isAcademicAdjustmentProject($project)
                && ($this->isInstitutionallyVisibleActiveProject($project) || $this->isRelatedAcademicTeacher($project, $userId));
            $capabilities['view_adjustment_requests'] = (int) ($project['tutor_id'] ?? 0) === $userId
                || count(array_filter($participants, static fn (array $participant): bool =>
                    (int) ($participant['user_id'] ?? 0) === $userId
                    && in_array(strtolower((string) ($participant['role_code'] ?? '')), ['tutor','cotutor','co_tutor','co-tutor'], true)
                )) > 0;
        }
        $isOwnerStudent = $isStudent && count(array_filter($participants, static fn (array $participant): bool =>
            (int) ($participant['user_id'] ?? 0) === $userId && strtolower((string) ($participant['role_code'] ?? '')) === 'student'
        )) > 0;
        if ($isOwnerStudent) {
            $status = (string) ($project['status'] ?? '');
            $situation = $this->studentEditSituation(Database::connection(), $project, $userId);
            $capabilities['edit_information'] = !empty($situation['can_edit_ordinary']);
            $capabilities['view_adjustment_requests'] = true;
            // Un expediente publicado conserva lectura, pero no permite mutaciones.
            $capabilities['respond_adjustment_request'] = $status !== 'published';
            $capabilities['address_adjustment_request'] = $status !== 'published';
            $capabilities['create_adjustment_request'] = !empty($situation['can_request_controlled_modification']);
            $type = (string) ($project['type_code'] ?? '');
            $capabilities['publish_project'] = ($type === 'thesis' && $status === 'tribunal_approved')
                || ($type !== 'thesis' && $status === 'approved');
            $capabilities['manage_workspace_files'] = !empty($situation['can_edit_ordinary']);
            $capabilities['send_for_review'] = !empty($situation['can_edit_ordinary']);
        }

        // La entrega estudiantil se habilita únicamente mientras el expediente sigue en desarrollo.
        $capabilities['register_delivery'] = !empty($capabilities['send_for_review']);
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

    /** Revalida en datos persistidos la identidad Student y su participación activa. */
    /**
     * Fuente única de verdad para la edición estudiantil.
     * La fecha se compara en la base de datos (UTC), nunca con un valor del
     * navegador. La reapertura administrativa queda respaldada por auditoría.
     *
     * @return array<string,mixed>
     */
    public function studentEditSituation(PDO $db, array $project, int $userId): array
    {
        $projectId = (int) ($project['id'] ?? 0);
        $empty = [
            'project_id' => $projectId,
            'student_participant' => false,
            'workflow_project' => false,
            'period_active' => false,
            'administratively_reopened' => false,
            'can_edit_ordinary' => false,
            'can_request_controlled_modification' => false,
            'reason' => 'not_available',
        ];
        if ($projectId < 1 || $userId < 1) return $empty;

        $current = $db->prepare(
            'SELECT p.id,p.status,p.publication_origin,p.academic_period_id,p.deleted_at,p.withdrawn_at,
                    ap.status period_status,ap.starts_on period_starts_on,ap.ends_on period_ends_on
             FROM projects p
             LEFT JOIN academic_periods ap ON ap.id=p.academic_period_id
             WHERE p.id=:project LIMIT 1'
        );
        $current->execute(['project' => $projectId]);
        $row = $current->fetch();
        if (!$row) return $empty;
        if (!empty($row['deleted_at']) || !empty($row['withdrawn_at'])) return $empty;

        $student = $db->prepare(
            "SELECT 1
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id=u.id
             INNER JOIN roles r ON r.id=ur.role_id AND r.code='student'
             INNER JOIN student_profiles sp ON sp.user_id=u.id
             INNER JOIN project_participants pp ON pp.user_id=u.id
             WHERE u.id=:user AND pp.project_id=:project
               AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
               AND LOWER(pp.role_code)='student' AND pp.status='active' AND pp.removed_at IS NULL
             LIMIT 1"
        );
        $student->execute(['user' => $userId, 'project' => $projectId]);
        $studentParticipant = (bool) $student->fetchColumn();

        $periodCheck = $db->prepare(
            'SELECT 1 FROM academic_periods
             WHERE id=:period AND status=\'active\'
               AND starts_on <= UTC_DATE() AND ends_on >= UTC_DATE() LIMIT 1'
        );
        $periodCheck->execute(['period' => (int) ($row['academic_period_id'] ?? 0)]);
        $periodActive = (bool) $periodCheck->fetchColumn();

        $reopen = $db->prepare(
            "SELECT action FROM project_audit_log
             WHERE project_id=:project
               AND action IN ('project_reopened_for_adjustment','project_submitted_for_review',
                              'project_corrections_requested','project_approved','project_tribunal_approved',
                              'tribunal_approved','project_published','project_unpublished',
                              'project_republished','project_publication_reverted')
             ORDER BY id DESC LIMIT 1"
        );
        $reopen->execute(['project' => $projectId]);
        $administrativelyReopened = (string) $reopen->fetchColumn() === 'project_reopened_for_adjustment';

        $status = (string) ($row['status'] ?? '');
        $workflow = (string) ($row['publication_origin'] ?? ProjectPublicationOrigin::WORKFLOW) === ProjectPublicationOrigin::WORKFLOW;
        $ordinary = $studentParticipant && $workflow && $status === 'development'
            && ($periodActive || $administrativelyReopened);
        $formalized = in_array($status, ['approved', 'defense', 'tribunal_approved', 'published'], true);
        $controlled = $studentParticipant && $workflow && !$ordinary
            && (!$periodActive || $formalized);

        $reason = $ordinary ? 'ordinary_editing'
            : (!$studentParticipant ? 'not_project_student' : (!$workflow ? 'not_academic_workflow'
                : (!$periodActive ? 'academic_period_finished' : ($formalized ? 'formalized_project' : 'academic_state_locked'))));
        return [
            'project_id' => $projectId,
            'student_participant' => $studentParticipant,
            'workflow_project' => $workflow,
            'period_id' => (int) ($row['academic_period_id'] ?? 0),
            'period_status' => (string) ($row['period_status'] ?? ''),
            'period_starts_on' => (string) ($row['period_starts_on'] ?? ''),
            'period_ends_on' => (string) ($row['period_ends_on'] ?? ''),
            'period_active' => $periodActive,
            'administratively_reopened' => $administrativelyReopened && $status === 'development',
            'status' => $status,
            'can_edit_ordinary' => $ordinary,
            'can_request_controlled_modification' => $controlled,
            'reason' => $reason,
        ];
    }

    public function canStudentEditProjectInTransaction(PDO $db, int $projectId, int $userId): bool
    {
        if ($projectId < 1 || $userId < 1) return false;
        return !empty($this->studentEditSituation($db, ['id' => $projectId], $userId)['can_edit_ordinary']);
    }

    /** @return array<string,bool> Capacidades de ajustes recalculadas con identidad persistida. */
    public function adjustmentCapabilitiesInTransaction(PDO $db, array $project, int $userId, string $context): array
    {
        $result = $this->none();
        if ($userId < 1 || !in_array($context, ['academic_management', 'academic', 'repository'], true)) return $result;
        $projectId = (int) ($project['id'] ?? 0);
        if ($projectId < 1 || !empty($project['deleted_at']) || !empty($project['withdrawn_at'])) return $result;
        $identity = $db->prepare(
            "SELECT u.is_admin,r.code FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id
             WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL"
        );
        $identity->execute(['user'=>$userId]);
        $rows = $identity->fetchAll();
        if ($rows === []) return $result;
        $isAdmin = count(array_filter($rows, static fn(array $row): bool => !empty($row['is_admin']) || strtolower((string)$row['code']) === 'administrator')) > 0;
        $session = new AuthSessionService();
        $adminMode = $session->isAuthenticated()
            && (int) ($session->userId() ?? 0) === $userId
            && $session->hasAdminAccess()
            && $session->isAdminModeActive();
        $origin = (string) ($project['publication_origin'] ?? ProjectPublicationOrigin::WORKFLOW);
        $status = (string) ($project['status'] ?? '');
        if ($context === 'academic_management') {
            if (!$isAdmin || !$adminMode || $origin !== ProjectPublicationOrigin::WORKFLOW) return $result;
            foreach (['create_adjustment_request','view_adjustment_requests','close_adjustment_request','approve_adjustment_request','reject_adjustment_request','manage_adjustment_requests'] as $key) $result[$key] = true;
            return $result;
        }
        if ($origin !== ProjectPublicationOrigin::WORKFLOW) return $result;
        $roles = array_map(static fn(array $row): string => strtolower((string)$row['code']), $rows);
        $assignment = $db->prepare(
            "SELECT LOWER(role_code) FROM project_participants WHERE project_id=:project AND user_id=:user
              AND status='active' AND removed_at IS NULL FOR UPDATE"
        );
        $assignment->execute(['project'=>$projectId, 'user'=>$userId]);
        $projectRoles = array_map('strval', $assignment->fetchAll(PDO::FETCH_COLUMN));
        // La cuenta puede tener is_admin=1 y seguir operando como Docente
        // fuera de Admin Mode. Mantener esta decisión igual que resolve().
        $teacherIdentity = in_array('teacher', $roles, true) && !$adminMode && $this->isActiveTeacherInTransaction($db, $userId);
        $teacher = $teacherIdentity && ($this->isInstitutionallyVisibleActiveProject($project) || $this->isRelatedAcademicTeacherInTransaction($db, $project, $userId));
        $student = in_array('student', $roles, true) && in_array('student', $projectRoles, true);
        $studentProfile = false;
        if ($student) {
            $profile = $db->prepare('SELECT 1 FROM student_profiles WHERE user_id=:user LIMIT 1');
            $profile->execute(['user' => $userId]);
            $studentProfile = (bool) $profile->fetchColumn();
        }
        if ($context === 'repository') {
            if (($isAdmin && !$teacherIdentity) || (string) ($project['status'] ?? '') !== 'published' || empty($project['is_available'])) return $result;
            $files = $db->prepare("SELECT 1 FROM project_files WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL LIMIT 1");
            $files->execute(['project' => $projectId]);
            if (!$files->fetchColumn()) return $result;
            if (($student && $studentProfile) || ($teacherIdentity && $status === 'published')) $result['create_adjustment_request'] = true;
            return $result;
        }
        if ($context !== 'academic' || ($isAdmin && !$teacherIdentity) || $status === 'published') return $result;
        $institutionallyActive = in_array($status, self::INSTITUTIONAL_ACTIVE_STATUSES, true);
        // El estudiante participante conserva lectura de sus solicitudes en
        // estados formalizados para poder seguir una reapertura controlada.
        if (!$institutionallyActive && !$student) return $result;
        if ($student) $result['view_adjustment_requests'] = true;
        if ($teacher && $institutionallyActive) $result['view_adjustment_requests'] = $this->isRelatedTeacher($db, $project, $userId);
        if ($teacher) $result['create_adjustment_request'] = true;
        $studentSituation = $student ? $this->studentEditSituation($db, $project, $userId) : null;
        if ($student && $studentProfile && !empty($studentSituation['can_request_controlled_modification'])) $result['create_adjustment_request'] = true;
        if ($student && (string) ($project['status'] ?? '') !== 'published') {
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

    private function isInstitutionallyVisibleActiveProject(array $project): bool
    {
        return empty($project['deleted_at'])
            && empty($project['withdrawn_at'])
            && (string) ($project['publication_origin'] ?? ProjectPublicationOrigin::WORKFLOW) === ProjectPublicationOrigin::WORKFLOW
            && in_array((string) ($project['status'] ?? ''), self::INSTITUTIONAL_ACTIVE_STATUSES, true);
    }

    private function isAcademicAdjustmentProject(array $project): bool
    {
        return empty($project['deleted_at'])
            && empty($project['withdrawn_at'])
            && (string) ($project['publication_origin'] ?? ProjectPublicationOrigin::WORKFLOW) === ProjectPublicationOrigin::WORKFLOW
            && in_array((string) ($project['status'] ?? ''), [...self::INSTITUTIONAL_ACTIVE_STATUSES, 'published'], true);
    }

    private function isRelatedAcademicTeacher(array $project, int $userId): bool
    {
        if ($userId < 1) return false;
        if ((int) ($project['tutor_id'] ?? 0) === $userId) return true;
        foreach ((array) ($project['participants'] ?? []) as $participant) {
            if ((int) ($participant['user_id'] ?? 0) !== $userId) continue;
            if (in_array(strtolower((string) ($participant['role_code'] ?? '')), ['tutor', 'cotutor', 'co_tutor', 'co-tutor', 'tribunal', 'jury'], true)) return true;
        }
        return false;
    }

    private function isOwnedRepositoryReadOnlyProject(array $project, int $userId, array $roles, bool $administrator): bool
    {
        if ($administrator || $userId < 1
            || (string) ($project['publication_origin'] ?? '') !== ProjectPublicationOrigin::DIRECT_REPOSITORY
            || (int) ($project['created_by'] ?? 0) !== $userId
            || in_array('administrator', array_map('strtolower', array_map('strval', $roles)), true)
            || !in_array('teacher', array_map('strtolower', array_map('strval', $roles)), true)
            || !$this->isActiveTeacher($userId)) return false;

        if (!empty($project['deleted_at'])) {
            return (int) ($project['deleted_by'] ?? 0) === $userId;
        }
        if (!empty($project['withdrawn_at'])) {
            return (int) ($project['withdrawn_by'] ?? 0) === $userId;
        }
        if ((string) ($project['status'] ?? '') !== 'published' || !empty($project['is_available'])) return false;

        $query = Database::connection()->prepare(
            "SELECT pal.user_id
             FROM project_audit_log pal
             WHERE pal.project_id=:project AND pal.action='project_availability_changed'
             ORDER BY pal.id DESC LIMIT 1"
        );
        $query->execute(['project' => (int) ($project['id'] ?? 0)]);
        return (int) $query->fetchColumn() === $userId;
    }

    private function isActiveTeacher(int $userId): bool
    {
        if ($userId < 1) return false;
        $query = Database::connection()->prepare(
            "SELECT 1
             FROM users u
             INNER JOIN teacher_profiles tp ON tp.user_id=u.id
             INNER JOIN user_roles ur ON ur.user_id=u.id
             INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher'
             WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
             LIMIT 1"
        );
        $query->execute(['user' => $userId]);
        return (bool) $query->fetchColumn();
    }

    private function isActiveTeacherInTransaction(PDO $db, int $userId): bool
    {
        if ($userId < 1) return false;
        $query = $db->prepare(
            "SELECT 1
             FROM users u
             INNER JOIN teacher_profiles tp ON tp.user_id=u.id
             INNER JOIN user_roles ur ON ur.user_id=u.id
             INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher'
             WHERE u.id=:user AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
             LIMIT 1"
        );
        $query->execute(['user' => $userId]);
        return (bool) $query->fetchColumn();
    }

    private function isRelatedAcademicTeacherInTransaction(PDO $db, array $project, int $userId): bool
    {
        if ($userId < 1) return false;
        if ((int) ($project['tutor_id'] ?? 0) === $userId) return true;
        $query = $db->prepare(
            "SELECT 1 FROM project_participants
             WHERE project_id=:project AND user_id=:user
               AND status='active' AND removed_at IS NULL
               AND LOWER(role_code) IN ('tutor','cotutor','co_tutor','co-tutor','tribunal','jury')
             LIMIT 1"
        );
        $query->execute(['project' => (int) ($project['id'] ?? 0), 'user' => $userId]);
        return (bool) $query->fetchColumn();
    }
}
