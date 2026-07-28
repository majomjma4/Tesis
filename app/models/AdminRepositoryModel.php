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
        $where = "p.deleted_at IS NULL AND p.status IN ('approved','defense','tribunal_approved','published')";
        $params = [];

        if ($filter === '' || $filter === 'published') {
            $where .= " AND p.status='published' AND EXISTS(
                SELECT 1 FROM project_files published_file
                WHERE published_file.project_id=p.id AND published_file.deleted_at IS NULL
            )";
        } elseif ($filter === 'eligible') {
            $where .= " AND $eligible AND EXISTS(
                SELECT 1 FROM project_files f
                WHERE f.project_id=p.id AND f.deleted_at IS NULL
            )";
        } elseif ($filter === 'incomplete') {
            $where .= " AND $eligible AND NOT EXISTS(
                SELECT 1 FROM project_files f
                WHERE f.project_id=p.id AND f.deleted_at IS NULL
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
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL
                ) file_count,
                (
                    SELECT GROUP_CONCAT(DISTINCT UPPER(f.extension) ORDER BY f.extension SEPARATOR ', ')
                    FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL
                ) formats"
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

        return [
            'eligible' => (int) $database->query(
                "SELECT COUNT(*) FROM projects p
                JOIN project_types pt ON pt.id=p.project_type_id
                WHERE p.deleted_at IS NULL AND $eligible
                AND EXISTS(
                    SELECT 1 FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL
                )"
            )->fetchColumn(),
            'published' => (int) $database->query(
                "SELECT COUNT(*) FROM projects
                WHERE deleted_at IS NULL AND status='published'
                AND EXISTS(
                    SELECT 1 FROM project_files f
                    WHERE f.project_id=projects.id AND f.deleted_at IS NULL
                )"
            )->fetchColumn(),
            'incomplete' => (int) $database->query(
                "SELECT COUNT(*) FROM projects p
                JOIN project_types pt ON pt.id=p.project_type_id
                WHERE p.deleted_at IS NULL AND $eligible
                AND NOT EXISTS(
                    SELECT 1 FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL
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
                "SELECT DISTINCT ap.id,ap.name,ap.starts_on
                FROM academic_periods ap
                JOIN projects p ON p.academic_period_id=ap.id
                WHERE p.deleted_at IS NULL AND p.status='published'
                ORDER BY ap.starts_on DESC"
            )->fetchAll(),
        ];
    }

    public function withdrawnPublications(): array
    {
        return Database::connection()->query(
            "SELECT
                p.id,p.code,p.title,p.status,pt.name type_name,ap.name period_name,
                u.full_name tutor_name,
                (
                    SELECT COUNT(*) FROM project_files f
                    WHERE f.project_id=p.id AND f.deleted_at IS NULL
                ) file_count,
                (
                    SELECT MAX(log.created_at) FROM project_audit_log log
                    WHERE log.project_id=p.id AND log.action='project_unpublished'
                ) withdrawn_at
            FROM projects p
            JOIN project_types pt ON pt.id=p.project_type_id
            JOIN academic_periods ap ON ap.id=p.academic_period_id
            LEFT JOIN users u ON u.id=p.tutor_id
            WHERE p.deleted_at IS NULL
            AND p.status IN ('approved','tribunal_approved')
            AND EXISTS(
                SELECT 1 FROM project_audit_log log
                WHERE log.project_id=p.id AND log.action='project_unpublished'
            )
            AND EXISTS(
                SELECT 1 FROM project_files f
                WHERE f.project_id=p.id AND f.deleted_at IS NULL
            )
            ORDER BY withdrawn_at DESC"
        )->fetchAll();
    }

    public function restorePublication(int $id, int $actor): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('El proyecto no es válido.');
        }

        Database::transaction(function (PDO $database) use ($id, $actor): void {
            $query = $database->prepare(
                "SELECT p.id,p.title,p.status,p.published_at,pt.code type_code
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

            $requiredStatus = $before['type_code'] === 'thesis' ? 'tribunal_approved' : 'approved';
            if ($before['status'] !== $requiredStatus) {
                throw new InvalidArgumentException(
                    'El proyecto cambió de estado y ya no puede restaurarse directamente.'
                );
            }

            $withdrawn = $database->prepare(
                "SELECT COUNT(*) FROM project_audit_log
                WHERE project_id=:id AND action='project_unpublished'"
            );
            $withdrawn->execute(['id' => $id]);
            if ((int) $withdrawn->fetchColumn() < 1) {
                throw new InvalidArgumentException(
                    'Solo pueden restaurarse proyectos retirados previamente del repositorio.'
                );
            }

            $database->prepare(
                "UPDATE projects
                SET status='published',published_at=CURRENT_TIMESTAMP
                WHERE id=:id"
            )->execute(['id' => $id]);

            (new ProjectAuditService($database))->record(
                $id,
                $actor,
                'project_republished',
                'project',
                $id,
                $before,
                ['status' => 'published'],
                'Restauración de una publicación retirada'
            );
        });
    }

    public function setPublished(int $id, bool $publish, int $actor): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('El proyecto no es válido.');
        }

        Database::transaction(function (PDO $database) use ($id, $publish, $actor): void {
            $query = $database->prepare(
                'SELECT p.id,p.title,p.status,p.published_at,pt.code type_code
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

            if ($publish) {
                $required = $before['type_code'] === 'thesis' ? 'tribunal_approved' : 'approved';
                if ($before['status'] !== $required) {
                    throw new InvalidArgumentException(
                        $required === 'tribunal_approved'
                            ? 'La tesis debe estar Aprobada por el Tribunal antes de publicarse.'
                            : 'El proyecto debe estar aprobado antes de publicarse.'
                    );
                }

                $database->prepare(
                    "UPDATE projects
                    SET status='published', published_at=CURRENT_TIMESTAMP
                    WHERE id=:id"
                )->execute(['id' => $id]);
                $after = ['status' => 'published'];
                $action = 'project_published';
            } else {
                if ($before['status'] !== 'published') {
                    throw new InvalidArgumentException('El proyecto no está publicado.');
                }

                $previous = $before['type_code'] === 'thesis' ? 'tribunal_approved' : 'approved';
                $database->prepare(
                    'UPDATE projects SET status=:status, published_at=NULL WHERE id=:id'
                )->execute(['status' => $previous, 'id' => $id]);
                $after = ['status' => $previous];
                $action = 'project_unpublished';
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
}
