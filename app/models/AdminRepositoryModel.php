<?php

declare(strict_types=1);

final class AdminRepositoryModel
{
    public function setPresentationFile(int $projectId, ?int $fileId, int $actor): void
    {
        if ($projectId < 1 || ($fileId !== null && $fileId < 1)) {
            throw new InvalidArgumentException('El archivo seleccionado no es válido.');
        }
        Database::transaction(function (PDO $database) use ($projectId, $fileId, $actor): void {
            $projectStatement = $database->prepare(
                'SELECT id,title,presentation_file_id FROM projects
                 WHERE id=:id AND deleted_at IS NULL FOR UPDATE'
            );
            $projectStatement->execute(['id' => $projectId]);
            $project = $projectStatement->fetch();
            if (!$project) throw new InvalidArgumentException('El proyecto ya no está disponible.');
            $file = null;
            if ($fileId !== null) {
                $fileStatement = $database->prepare(
                    "SELECT id,original_name FROM project_files
                     WHERE id=:file_id AND project_id=:project_id AND deleted_at IS NULL
                       AND LOWER(extension) IN ('pdf','docx','txt','png','jpg','jpeg','webp')
                     FOR UPDATE"
                );
                $fileStatement->execute(['file_id' => $fileId, 'project_id' => $projectId]);
                $file = $fileStatement->fetch();
                if (!$file) throw new InvalidArgumentException('Selecciona un archivo compatible con la vista previa.');
            }
            $previousId = (int) ($project['presentation_file_id'] ?? 0);
            $database->prepare(
                'UPDATE projects SET presentation_file_id=:file_id WHERE id=:project_id'
            )->execute(['file_id' => $fileId, 'project_id' => $projectId]);
            (new ProjectAuditService($database))->record(
                $projectId,
                $actor,
                $fileId === null ? 'project.presentation_removed' : ($previousId > 0 ? 'project.presentation_changed' : 'project.presentation_selected'),
                'project_file',
                $fileId,
                ['presentation_file_id' => $previousId ?: null],
                ['presentation_file_id' => $fileId, 'file_name' => $file['original_name'] ?? null]
            );
        });
    }

    public function listing(string $filter = '', array $pagination = []): array
    {
        $eligible = "((pt.code='thesis' AND p.status='tribunal_approved') OR (pt.code<>'thesis' AND p.status='approved'))";
        $activeParticipantWhere = "p.deleted_at IS NULL
            AND EXISTS (
                SELECT 1
                FROM project_participants student_participant
                JOIN student_profiles student_profile
                  ON student_profile.user_id=student_participant.user_id
                WHERE student_participant.project_id=p.id
                  AND student_participant.role_code='student'
                  AND student_participant.status='active'
            )
            AND p.status IN ('approved','defense','tribunal_approved','published')";
        $where = $activeParticipantWhere;
        $params = [];

        if ($filter === '' || $filter === 'published') {
            $where = ProjectCapabilityService::institutionalPublishedProjectWhere('p');
        } elseif ($filter === 'eligible') {
            $where .= " AND $eligible AND EXISTS(
                SELECT 1 FROM project_files f
                WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL
            )";
        } elseif ($filter === 'incomplete') {
            $where .= " AND $eligible AND NOT EXISTS(
                SELECT 1 FROM project_files f
                WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL
            )";
        }

        $from = " FROM projects p
            JOIN project_types pt ON pt.id=p.project_type_id
            JOIN careers c ON c.id=p.career_id
            JOIN academic_periods ap ON ap.id=p.academic_period_id
            LEFT JOIN users u ON u.id=p.tutor_id
            WHERE $where";

        $sql = "SELECT
                p.id,
                p.code,
                p.title,
                p.summary,
                p.status,
                p.published_at,
                p.is_available,
                p.updated_at,
                pt.code type_code,
                pt.name type_name,
                c.name career_name,
                ap.name period_name,
                u.full_name tutor_name,
                (
                    SELECT GROUP_CONCAT(participant.full_name ORDER BY pp.is_leader DESC, participant.full_name SEPARATOR ', ')
                    FROM project_participants pp
                    JOIN users participant ON participant.id=pp.user_id
                    WHERE pp.project_id=p.id AND pp.status='active'
                ) authors,
                (
                    SELECT COUNT(*) FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL
                ) file_count,
                (
                    SELECT GROUP_CONCAT(DISTINCT UPPER(f.extension) ORDER BY f.extension SEPARATOR ', ')
                    FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL
                ) formats,
                (
                    SELECT COUNT(*) FROM project_adjustment_requests ar
                    WHERE ar.project_id=p.id
                      AND ar.status='pending'
                      AND ar.request_type='published_modification'
                ) pending_adjustment_count"
            . $from
            . " ORDER BY COALESCE(p.published_at,p.updated_at) DESC";

        return PaginationService::run(
            Database::connection(),
            'SELECT COUNT(*)' . $from,
            $sql,
            $params,
            $pagination ?: PaginationService::request()
        );
    }

    public function summary(): array
    {
        $database = Database::connection();
        $eligible = "((pt.code='thesis' AND p.status='tribunal_approved') OR (pt.code<>'thesis' AND p.status='approved'))";
        $pendingRows = $database->query(
            "SELECT p.academic_period_id,COUNT(DISTINCT p.id) total
            FROM projects p
            JOIN project_types pt ON pt.id=p.project_type_id
            WHERE p.deleted_at IS NULL
              AND p.status='approved'
              AND EXISTS(
                  SELECT 1
                  FROM project_participants pp
                  JOIN student_profiles sp ON sp.user_id=pp.user_id
                  WHERE pp.project_id=p.id
                    AND pp.role_code='student'
                    AND pp.status='active'
              )
            GROUP BY p.academic_period_id"
        )->fetchAll();
        $pendingByPeriod = [];
        foreach ($pendingRows as $row) {
            $pendingByPeriod[(int) $row['academic_period_id']] = (int) $row['total'];
        }

        return [
            'pending' => array_sum($pendingByPeriod),
            'pending_by_period' => $pendingByPeriod,
            'eligible' => (int) $database->query(
                "SELECT COUNT(DISTINCT p.id) FROM projects p
                JOIN project_types pt ON pt.id=p.project_type_id
                WHERE p.deleted_at IS NULL AND $eligible
                AND EXISTS(
                    SELECT 1 FROM project_participants pp
                    JOIN student_profiles sp ON sp.user_id=pp.user_id
                    WHERE pp.project_id=p.id AND pp.role_code='student' AND pp.status='active'
                )
                AND EXISTS(
                    SELECT 1 FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL
                )"
            )->fetchColumn(),
            'published' => (int) $database->query(
                "SELECT COUNT(DISTINCT p.id) FROM projects p
                WHERE " . ProjectCapabilityService::institutionalPublishedProjectWhere('p')
            )->fetchColumn(),
            'withdrawn' => (int) $database->query(
                "SELECT COUNT(*) FROM projects p
                WHERE p.deleted_at IS NULL
                  AND p.status='published'
                  AND p.withdrawn_at IS NOT NULL"
            )->fetchColumn(),
            'incomplete' => (int) $database->query(
                "SELECT COUNT(DISTINCT p.id) FROM projects p
                JOIN project_types pt ON pt.id=p.project_type_id
                WHERE p.deleted_at IS NULL AND $eligible
                AND EXISTS(
                    SELECT 1 FROM project_participants pp
                    JOIN student_profiles sp ON sp.user_id=pp.user_id
                    WHERE pp.project_id=p.id AND pp.role_code='student' AND pp.status='active'
                )
                AND NOT EXISTS(
                    SELECT 1 FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL
                )"
            )->fetchColumn(),
        ];
    }

    public function filterCatalogs(): array
    {
        $database = Database::connection();
        return [
            'types' => $database->query(
                "SELECT id,name FROM project_types
                WHERE is_active=1 ORDER BY name"
            )->fetchAll(),
            'periods' => $database->query(
                "SELECT id,name,status,starts_on
                FROM academic_periods
                WHERE status IN ('active','closed')
                ORDER BY (status='active') DESC,starts_on DESC"
            )->fetchAll(),
        ];
    }

    public function withdrawnPublications(): array
    {
        return Database::connection()->query(
            "SELECT
                p.id,p.code,p.title,p.status,p.published_at,p.is_available,p.withdrawn_at,p.withdrawn_by,pt.code type_code,pt.name type_name,ap.name period_name,
                u.full_name tutor_name,
                (
                    SELECT GROUP_CONCAT(participant.full_name ORDER BY pp.is_leader DESC, participant.full_name SEPARATOR ', ')
                    FROM project_participants pp
                    JOIN users participant ON participant.id=pp.user_id
                    WHERE pp.project_id=p.id AND pp.status='active'
                ) authors,
                (
                    SELECT COUNT(*) FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL
                ) file_count,
                (
                    SELECT u.full_name FROM users u WHERE u.id=p.withdrawn_by
                ) withdrawn_by_name
            FROM projects p
            JOIN project_types pt ON pt.id=p.project_type_id
            JOIN academic_periods ap ON ap.id=p.academic_period_id
            LEFT JOIN users u ON u.id=p.tutor_id
            WHERE p.deleted_at IS NULL
            AND p.status='published' AND p.withdrawn_at IS NOT NULL
            AND EXISTS(
                SELECT 1 FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL AND f.purged_at IS NULL
            )
            ORDER BY withdrawn_at DESC"
        )->fetchAll();
    }

    public function restorePublication(int $id, int $actor, ?int $requiredOwnerId = null): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('El proyecto no es válido.');
        }

        Database::transaction(function (PDO $database) use ($id, $actor, $requiredOwnerId): void {
            $query = $database->prepare(
                "SELECT p.id,p.title,p.status,p.published_at,p.is_available,p.withdrawn_at,p.withdrawn_by,p.created_by,p.publication_origin,pt.code type_code
                FROM projects p
                JOIN project_types pt ON pt.id=p.project_type_id
                WHERE p.id=:id AND p.deleted_at IS NULL
                FOR UPDATE"
            );
            $query->execute(['id' => $id]);
            $before = $query->fetch();
            if (!$before) {
                throw new InvalidArgumentException('El proyecto ya no está disponible.');
            }

            if ($requiredOwnerId !== null && (
                (int)($before['created_by'] ?? 0) !== $requiredOwnerId
                || (string)($before['publication_origin'] ?? '') !== ProjectPublicationOrigin::DIRECT_REPOSITORY
                || (int)($before['withdrawn_by'] ?? 0) !== $requiredOwnerId
            )) throw new SupportMaterialAccessException('No tienes autorización para restaurar este proyecto.');
            if ($before['status'] !== 'published' || $before['withdrawn_at'] === null) throw new InvalidArgumentException('Solo pueden reincorporarse proyectos retirados previamente del repositorio.');

            $database->prepare(
                "UPDATE projects SET withdrawn_at=NULL,withdrawn_by=NULL WHERE id=:id AND status='published' AND withdrawn_at IS NOT NULL"
            )->execute(['id' => $id]);

            (new ProjectAuditService($database))->record(
                $id,
                $actor,
                'project_reincorporated',
                'project',
                $id,
                $before,
                ['status' => 'published','published_at' => $before['published_at'],'is_available' => (bool)$before['is_available'],'withdrawn_at' => null],
                'Reincorporación al repositorio institucional'
            );
        });
    }

    public function setPublished(int $id, bool $publish, int $actor, ?int $requiredOwnerId = null): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('El proyecto no es válido.');
        }

        Database::transaction(function (PDO $database) use ($id, $publish, $actor, $requiredOwnerId): void {
            $query = $database->prepare(
                'SELECT p.id,p.title,p.status,p.published_at,p.is_available,p.withdrawn_at,p.withdrawn_by,p.created_by,p.publication_origin,pt.code type_code
                FROM projects p
                JOIN project_types pt ON pt.id=p.project_type_id
                WHERE p.id=:id AND p.deleted_at IS NULL
                FOR UPDATE'
            );
            $query->execute(['id' => $id]);
            $before = $query->fetch();

            if (!$before) {
                throw new InvalidArgumentException('El proyecto ya no está disponible.');
            }

            if ($requiredOwnerId !== null && (
                (int)($before['created_by'] ?? 0) !== $requiredOwnerId
                || (string)($before['publication_origin'] ?? '') !== ProjectPublicationOrigin::DIRECT_REPOSITORY
                || (string)($before['status'] ?? '') !== 'published'
                || $before['withdrawn_at'] !== null
            )) throw new SupportMaterialAccessException('No tienes autorización para retirar este proyecto.');
            if ($publish) {
                $required = $before['type_code'] === 'thesis' ? 'tribunal_approved' : 'approved';
                if ($before['status'] !== $required) {
                    throw new InvalidArgumentException(
                        $required === 'tribunal_approved'
                            ? 'La tesis debe estar Aprobada por el Tribunal antes de publicarse.'
                            : 'El proyecto debe estar aprobado antes de publicarse.'
                    );
                }
                (new ProjectStatusTransitionService())->transitionInTransaction(
                    $database, $id, (string) $before['status'], 'published', '', $actor, 'repository'
                );
                return;
            } else {
                if ($before['status'] !== 'published') {
                    throw new InvalidArgumentException('El proyecto no está publicado.');
                }
                if ($before['withdrawn_at'] !== null) throw new InvalidArgumentException('El proyecto ya está retirado del Repositorio.');
                $database->prepare(
                    'UPDATE projects SET withdrawn_at=UTC_TIMESTAMP(),withdrawn_by=:actor WHERE id=:id AND status=\'published\' AND withdrawn_at IS NULL'
                )->execute(['actor' => $actor, 'id' => $id]);
                $after = ['status' => 'published','published_at' => $before['published_at'],'is_available' => (bool)$before['is_available'],'withdrawn_at' => 'set'];
                $action = 'project_withdrawn';
            }

            (new ProjectAuditService($database))->record(
                $id,
                $actor,
                $action,
                'project',
                $id,
                $before,
                $after
            );
        });
    }

    public function setAvailability(int $id, bool $available, int $actor, ?int $requiredOwnerId = null): void
    {
        if ($id < 1) throw new InvalidArgumentException('El proyecto no es válido.');
        Database::transaction(function (PDO $database) use ($id, $available, $actor, $requiredOwnerId): void {
            $query = $database->prepare(
                "SELECT id,title,status,is_available,created_by,publication_origin,withdrawn_at FROM projects
                 WHERE id=:id AND deleted_at IS NULL FOR UPDATE"
            );
            $query->execute(['id' => $id]);
            $before = $query->fetch();
            if (!$before) throw new InvalidArgumentException('El proyecto ya no está disponible.');
            if ($requiredOwnerId !== null && (
                (int)($before['created_by'] ?? 0) !== $requiredOwnerId
                || (string)($before['publication_origin'] ?? '') !== ProjectPublicationOrigin::DIRECT_REPOSITORY
                || $before['withdrawn_at'] !== null
            )) throw new SupportMaterialAccessException('No tienes autorización para cambiar este proyecto.');
            if ((string) $before['status'] !== 'published') {
                throw new InvalidArgumentException('La disponibilidad solo puede cambiarse en proyectos publicados.');
            }
            if ($requiredOwnerId !== null && $available) {
                $lastAvailability = $database->prepare("SELECT user_id FROM project_audit_log WHERE project_id=:id AND action='project_availability_changed' ORDER BY id DESC LIMIT 1");
                $lastAvailability->execute(['id'=>$id]);
                if ((int)$lastAvailability->fetchColumn() !== $requiredOwnerId) throw new SupportMaterialAccessException('No puedes revertir una disponibilidad cambiada por administración.');
            }
            if ((bool) $before['is_available'] === $available) {
                throw new InvalidArgumentException($available
                    ? 'El proyecto ya está disponible.'
                    : 'El proyecto ya está marcado como no disponible.');
            }
            $database->prepare(
                'UPDATE projects SET is_available=:available WHERE id=:id AND status=\'published\''
            )->execute(['available' => $available ? 1 : 0, 'id' => $id]);
            (new ProjectAuditService($database))->record(
                $id,
                $actor,
                'project_availability_changed',
                'project',
                $id,
                ['is_available' => (bool) $before['is_available']],
                ['is_available' => $available]
            );
        });
    }
}
