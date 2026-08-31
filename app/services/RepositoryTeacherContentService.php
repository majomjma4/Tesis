<?php

declare(strict_types=1);

/** Gestiona el inventario propio del Teacher sin ampliar sus capacidades administrativas. */
final class RepositoryTeacherContentService
{
    /** @return array{active:list<array<string,mixed>>,unavailable:list<array<string,mixed>>,withdrawn:list<array<string,mixed>>,trash:list<array<string,mixed>>,counts:array<string,int>} */
    public function snapshot(int $teacherId): array
    {
        $empty = [
            'active' => [],
            'unavailable' => [],
            'withdrawn' => [],
            'trash' => [],
            'counts' => ['active' => 0, 'unavailable' => 0, 'withdrawn' => 0, 'trash' => 0],
        ];
        if (!$this->isActiveTeacher($teacherId)) return $empty;

        $db = Database::connection();
        $settings = (new SystemSettingModel())->all();
        $projectRetentionDays = max(1, (int) ($settings['retention_projects_days'] ?? 60));
        $materialRetentionDays = max(1, (int) ($settings['retention_materials_days'] ?? 60));
        $projectsQuery = $db->prepare(
            "SELECT p.id,p.code,p.title,p.status,p.is_available,p.published_at,p.updated_at,
                    p.created_by,p.publication_origin,p.withdrawn_at,p.withdrawn_by,
                    p.deleted_at,p.deleted_by,p.deletion_reason,
                    pt.name type_name,ap.name period_name,c.name career_name,
                    (SELECT COUNT(*) FROM project_files pf
                     WHERE pf.project_id=p.id AND pf.deleted_at IS NULL AND pf.purged_at IS NULL) file_count,
                    (SELECT pal.user_id FROM project_audit_log pal
                     WHERE pal.project_id=p.id AND pal.action='project_availability_changed'
                     ORDER BY pal.id DESC LIMIT 1) last_availability_actor_id
             FROM projects p
             INNER JOIN project_types pt ON pt.id=p.project_type_id
             INNER JOIN careers c ON c.id=p.career_id
             INNER JOIN academic_periods ap ON ap.id=p.academic_period_id
             WHERE p.created_by=:teacher_id
               AND p.publication_origin=:origin
             ORDER BY COALESCE(p.updated_at,p.created_at) DESC,p.id DESC"
        );
        $projectsQuery->execute([
            'teacher_id' => $teacherId,
            'origin' => ProjectPublicationOrigin::DIRECT_REPOSITORY,
        ]);

        $result = $empty;
        foreach ($projectsQuery->fetchAll() as $project) {
            $item = $this->projectItem($project, $projectRetentionDays);
            $bucket = $this->projectBucket($project, $teacherId);
            if ($bucket !== null) $result[$bucket][] = $item;
        }

        foreach ((new SupportMaterialModel())->getTeacherOwnedManagement($teacherId) as $material) {
            $item = $this->materialItem($material, $materialRetentionDays);
            $bucket = $this->materialBucket($material, $teacherId);
            if ($bucket !== null) $result[$bucket][] = $item;
        }

        foreach (['active', 'unavailable', 'withdrawn', 'trash'] as $bucket) {
            usort($result[$bucket], static fn(array $left, array $right): int =>
                strcmp((string) ($right['sort_at'] ?? ''), (string) ($left['sort_at'] ?? ''))
                ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0))
            );
            $result['counts'][$bucket] = count($result[$bucket]);
        }
        return $result;
    }

    /** @return array<string,mixed> */
    public function ownedDirectProject(int $projectId, int $teacherId, bool $includeHidden = true): array
    {
        if (!$this->isActiveTeacher($teacherId) || $projectId < 1) {
            throw new SupportMaterialAccessException('No tienes autorización para gestionar este proyecto.');
        }
        $where = $includeHidden ? '' : ' AND p.deleted_at IS NULL AND p.withdrawn_at IS NULL';
        $query = Database::connection()->prepare(
            "SELECT p.* FROM projects p
             WHERE p.id=:id AND p.created_by=:teacher_id
               AND p.publication_origin=:origin{$where}
             LIMIT 1"
        );
        $query->execute([
            'id' => $projectId,
            'teacher_id' => $teacherId,
            'origin' => ProjectPublicationOrigin::DIRECT_REPOSITORY,
        ]);
        $project = $query->fetch();
        if (!$project) throw new SupportMaterialAccessException('No tienes autorización para gestionar este proyecto.', 404);
        return $project;
    }

    /** Revalida en backend la propiedad y el estado antes de una mutaciÃ³n del proyecto propio. */
    public function assertDirectProjectAction(array $project, int $teacherId, string $action): void
    {
        if (!$this->isActiveTeacher($teacherId)
            || (int) ($project['created_by'] ?? 0) !== $teacherId
            || (string) ($project['publication_origin'] ?? '') !== ProjectPublicationOrigin::DIRECT_REPOSITORY) {
            throw new SupportMaterialAccessException('No tienes autorizaciÃ³n para gestionar este proyecto.');
        }

        if ($action === 'restore') {
            if (empty($project['deleted_at']) || (int) ($project['deleted_by'] ?? 0) !== $teacherId) {
                throw new SupportMaterialAccessException('No puedes restaurar un proyecto retirado por administraciÃ³n.');
            }
            return;
        }
        if ($action === 'restore_publication') {
            if (empty($project['withdrawn_at']) || (int) ($project['withdrawn_by'] ?? 0) !== $teacherId) {
                throw new SupportMaterialAccessException('No puedes revertir una decisiÃ³n de retiro tomada por administraciÃ³n.');
            }
            return;
        }

        if (!empty($project['deleted_at']) || !empty($project['withdrawn_at']) || (string) ($project['status'] ?? '') !== 'published') {
            throw new SupportMaterialAccessException('El proyecto no estÃ¡ disponible para esta operaciÃ³n.');
        }
    }

    /** Revalida la propiedad y la evidencia del actor para operar material propio. */
    public function assertSupportMaterialAction(array $material, int $teacherId, string $action): void
    {
        if (!$this->isActiveTeacher($teacherId) || (int) ($material['created_by'] ?? 0) !== $teacherId) {
            throw new SupportMaterialAccessException('No tienes autorizaciÃ³n para gestionar este material de apoyo.');
        }

        if ($action === 'restore') {
            if (empty($material['deleted_at']) || (int) ($material['deleted_by'] ?? 0) !== $teacherId) {
                throw new SupportMaterialAccessException('No puedes restaurar un material retirado por administraciÃ³n.');
            }
            return;
        }

        if (!empty($material['deleted_at'])) {
            throw new SupportMaterialAccessException('El material no estÃ¡ disponible para esta operaciÃ³n.');
        }
        if ((string) ($material['status_key'] ?? '') === 'withdrawn'
            && (int) ($material['withdrawn_by'] ?? 0) !== $teacherId) {
            throw new SupportMaterialAccessException('No puedes modificar una decisiÃ³n tomada por administraciÃ³n.');
        }
        if ((string) ($material['status_key'] ?? '') === 'published' && empty($material['is_available'])) {
            $query = Database::connection()->prepare(
                "SELECT actor_user_id FROM admin_audit_log
                 WHERE entity_type='support_material' AND entity_id=:id
                   AND action='support_material_availability_changed'
                 ORDER BY id DESC LIMIT 1"
            );
            $query->execute(['id' => (int) ($material['id'] ?? 0)]);
            if ((int) $query->fetchColumn() !== $teacherId) {
                throw new SupportMaterialAccessException('No puedes modificar una disponibilidad cambiada por administraciÃ³n.');
            }
        }
    }

    public function restoreProject(int $projectId, int $teacherId): void
    {
        $this->ownedDirectProject($projectId, $teacherId);
        Database::transaction(function (PDO $db) use ($projectId, $teacherId): void {
            $query = $db->prepare(
                "SELECT p.* FROM projects p
                 WHERE p.id=:id AND p.created_by=:teacher_id
                   AND p.publication_origin=:origin AND p.deleted_at IS NOT NULL
                 FOR UPDATE"
            );
            $query->execute([
                'id' => $projectId,
                'teacher_id' => $teacherId,
                'origin' => ProjectPublicationOrigin::DIRECT_REPOSITORY,
            ]);
            $project = $query->fetch();
            if (!$project || (int) ($project['deleted_by'] ?? 0) !== $teacherId) {
                throw new SupportMaterialAccessException('No puedes restaurar un proyecto retirado por administración.');
            }
            $update = $db->prepare(
                'UPDATE projects
                 SET deleted_at=NULL,deleted_by=NULL,deletion_reason=NULL,updated_at=UTC_TIMESTAMP()
                 WHERE id=:id AND deleted_at IS NOT NULL AND deleted_by=:teacher_id'
            );
            $update->execute(['id' => $projectId, 'teacher_id' => $teacherId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('El proyecto cambió de estado antes de restaurarse.');
            (new ProjectAuditService($db))->record(
                $projectId,
                $teacherId,
                'project_restored',
                'project',
                $projectId,
                ['deleted_at' => $project['deleted_at'], 'deleted_by' => $project['deleted_by']],
                ['deleted_at' => null, 'deleted_by' => null, 'origin' => ProjectPublicationOrigin::DIRECT_REPOSITORY],
                'Restauración del contenido propio desde la Papelera'
            );
        });
    }

    private function projectBucket(array $project, int $teacherId): ?string
    {
        if ($project['deleted_at'] !== null) {
            return (int) ($project['deleted_by'] ?? 0) === $teacherId ? 'trash' : null;
        }
        if ($project['withdrawn_at'] !== null) {
            return (int) ($project['withdrawn_by'] ?? 0) === $teacherId ? 'withdrawn' : null;
        }
        if ((string) ($project['status'] ?? '') !== 'published') return null;
        if ((int) ($project['is_available'] ?? 0) === 1) return 'active';
        return (int) ($project['last_availability_actor_id'] ?? 0) === $teacherId ? 'unavailable' : null;
    }

    private function materialBucket(array $material, int $teacherId): ?string
    {
        if (!empty($material['deleted_at'])) {
            return (int) ($material['deleted_by'] ?? 0) === $teacherId ? 'trash' : null;
        }
        if ((string) ($material['status_key'] ?? '') === 'withdrawn') {
            return (int) ($material['withdrawn_by'] ?? 0) === $teacherId ? 'withdrawn' : null;
        }
        if ((string) ($material['status_key'] ?? '') === 'published' && empty($material['is_available'])) {
            $query = Database::connection()->prepare(
                "SELECT actor_user_id FROM admin_audit_log
                 WHERE entity_type='support_material' AND entity_id=:id
                   AND action='support_material_availability_changed'
                 ORDER BY id DESC LIMIT 1"
            );
            $query->execute(['id' => (int) ($material['id'] ?? 0)]);
            return (int) $query->fetchColumn() === $teacherId ? 'unavailable' : null;
        }
        if ((string) ($material['status_key'] ?? '') === 'published' && !empty($material['is_available'])) return 'active';
        if ((string) ($material['status_key'] ?? '') === 'draft') return 'active';
        return null;
    }

    /** @return array<string,mixed> */
    private function trashMetadata(mixed $deletedAt, int $retentionDays): array
    {
        $deletedAt = trim((string) $deletedAt);
        if ($deletedAt === '') return ['trash_at_label' => '', 'trash_days_left' => null, 'trash_retention_days' => $retentionDays, 'trash_origin_label' => ''];
        try {
            $date = new DateTimeImmutable($deletedAt, new DateTimeZone('UTC'));
            $expires = $date->modify('+' . $retentionDays . ' days');
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $daysLeft = max(0, (int) ceil(($expires->getTimestamp() - $now->getTimestamp()) / 86400));
            return [
                'trash_at_label' => $date->setTimezone(new DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i'),
                'trash_days_left' => $daysLeft,
                'trash_retention_days' => $retentionDays,
                'trash_origin_label' => 'Accion realizada por el Teacher propietario',
            ];
        } catch (Throwable) {
            return ['trash_at_label' => $deletedAt, 'trash_days_left' => null, 'trash_retention_days' => $retentionDays, 'trash_origin_label' => 'Accion realizada por el Teacher propietario'];
        }
    }

    private function projectItem(array $project, int $retentionDays): array
    {
        return [
            'kind' => 'project',
            'id' => (int) $project['id'],
            'title' => (string) $project['title'],
            'code' => (string) $project['code'],
            'type' => (string) $project['type_name'],
            'career' => (string) $project['career_name'],
            'period' => (string) $project['period_name'],
            'file_count' => (int) ($project['file_count'] ?? 0),
            ...$this->trashMetadata($project['deleted_at'] ?? null, $retentionDays),
            'sort_at' => (string) ($project['updated_at'] ?? $project['published_at'] ?? ''),
            'detail_url' => route('repository-detail') . '&id=' . (int) $project['id'] . '&tab=information',
            'edit_url' => route('repository-detail') . '&id=' . (int) $project['id'] . '&tab=information',
        ];
    }

    /** @return array<string,mixed> */
    private function materialItem(array $material, int $retentionDays): array
    {
        return [
            'kind' => 'material',
            'id' => (int) $material['id'],
            'title' => (string) $material['title'],
            'code' => '',
            'type' => (string) ($material['type'] ?? $material['material_type'] ?? 'Material de apoyo'),
            'career' => '',
            'period' => (string) ($material['pao_label'] ?? $material['period_name'] ?? ''),
            'file_count' => (int) ($material['files_count'] ?? count((array) ($material['files'] ?? []))),
            ...$this->trashMetadata($material['deleted_at'] ?? null, $retentionDays),
            'sort_at' => (string) ($material['updated_at'] ?? $material['created_at'] ?? ''),
            'detail_url' => route('support-material-detail') . '&id=' . (int) $material['id'],
            'edit_url' => route('support-material-detail') . '&id=' . (int) $material['id'] . '&mode=edit&tab=information',
        ];
    }

    public function isActiveTeacher(int $teacherId): bool
    {
        if ($teacherId < 1) return false;
        $query = Database::connection()->prepare(
            "SELECT 1 FROM users u
             INNER JOIN teacher_profiles tp ON tp.user_id=u.id
             INNER JOIN user_roles ur ON ur.user_id=u.id
             INNER JOIN roles r ON r.id=ur.role_id AND r.code='teacher'
             WHERE u.id=:id AND u.status='active'
               AND u.deleted_at IS NULL AND u.purged_at IS NULL
             LIMIT 1"
        );
        $query->execute(['id' => $teacherId]);
        return (bool) $query->fetchColumn();
    }
}
