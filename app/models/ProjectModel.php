<?php

declare(strict_types=1);

/** Consultas reales del conjunto de proyectos del estudiante autenticado. */
final class ProjectModel
{
    /** Proyectos directos publicados relacionados con el estudiante, sólo para Terminados. */
    public function getDirectRepositoryFinishedProjectsResult(int $userId): array
    {
        if ($userId < 1 || !Database::isEnabled()) return ['status' => 'error', 'items' => []];
        try {
            $db = Database::connection();
            $statement = $db->prepare("SELECT DISTINCT p.id,p.code,p.title,p.subtitle,p.status,p.current_stage,p.updated_at,
                p.published_at,p.repository_added_by,pt.code AS type_key,pt.name AS type,c.name AS career,
                ap.name AS period,t.full_name AS tutor,publisher.full_name AS publisher
                FROM projects p
                INNER JOIN project_types pt ON pt.id=p.project_type_id
                INNER JOIN careers c ON c.id=p.career_id
                INNER JOIN academic_periods ap ON ap.id=p.academic_period_id
                INNER JOIN project_participants pp ON pp.project_id=p.id
                INNER JOIN users student_user ON student_user.id=pp.user_id
                LEFT JOIN users t ON t.id=p.tutor_id
                LEFT JOIN users publisher ON publisher.id=p.repository_added_by
                WHERE p.deleted_at IS NULL AND p.withdrawn_at IS NULL
                  AND p.status='published' AND p.publication_origin='direct_repository'
                  AND p.is_available=1 AND p.published_at IS NOT NULL
                  AND pp.user_id=:user_id AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL
                  AND student_user.status='active' AND student_user.deleted_at IS NULL AND student_user.purged_at IS NULL
                ORDER BY p.published_at DESC,p.id DESC");
            $statement->execute(['user_id' => $userId]);
            $rows = $statement->fetchAll();
            $items = array_map(static function (array $row): array {
                $publishedAt = (string) ($row['published_at'] ?? '');
                $date = $publishedAt;
                try { $date = (new DateTimeImmutable($publishedAt, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Guayaquil'))->format('d/m/Y'); } catch (Throwable) {}
                $publisher = trim((string) ($row['publisher'] ?? ''));
                return [
                    'id' => (int) $row['id'], 'code' => (string) $row['code'], 'title' => (string) $row['title'],
                    'subtitle' => (string) ($row['subtitle'] ?? ''), 'status' => 'Publicado', 'status_key' => 'published',
                    'type' => (string) $row['type'], 'type_key' => (string) $row['type_key'], 'career' => (string) $row['career'],
                    'period' => (string) $row['period'], 'tutor' => (string) ($row['tutor'] ?? ''),
                    'stage' => 'repository_direct', 'last_activity' => 'Publicación en repositorio · ' . $date,
                    'review_situation' => [], 'observations' => [], 'final_documents' => [], 'latest_delivery' => null,
                    'key_dates' => [['label' => 'Publicación', 'value' => $date]], 'activity_order' => 0,
                    'tags' => [], 'technologies' => [], 'repository_id' => (int) $row['id'], 'repository_available' => true,
                    'publication_origin' => 'direct_repository', 'is_direct_repository' => true,
                    'publisher' => $publisher, 'capabilities' => [],
                ];
            }, $rows);
            return ['status' => $items === [] ? 'empty' : 'loaded', 'items' => $items];
        } catch (Throwable $error) {
            error_log('Student direct repository finished query: ' . $error->getMessage());
            return ['status' => 'error', 'items' => []];
        }
    }

    public function getProjectsForUser(int $userId): array
    {
        return (array) $this->getStudentProjectsResult($userId)['items'];
    }

    /** Cuenta proyectos estudiantiles autorizados para la etiqueta global de navegación. */
    public function getStudentProjectCountResult(int $userId): array
    {
        if ($userId < 1 || !Database::isEnabled()) {
            return ['status' => 'error', 'count' => null];
        }
        try {
            $statement = Database::connection()->prepare("SELECT COUNT(DISTINCT p.id)
                FROM projects p
                INNER JOIN project_types pt ON pt.id=p.project_type_id
                INNER JOIN careers c ON c.id=p.career_id
                INNER JOIN academic_periods ap ON ap.id=p.academic_period_id
                INNER JOIN project_participants pp ON pp.project_id=p.id
                INNER JOIN users student_user ON student_user.id=pp.user_id
                WHERE p.deleted_at IS NULL AND p.withdrawn_at IS NULL
                  AND p.publication_origin = 'workflow'
                  AND pp.user_id=:user_id AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL
                  AND student_user.status='active' AND student_user.deleted_at IS NULL AND student_user.purged_at IS NULL");
            $statement->execute(['user_id' => $userId]);
            $count = (int) $statement->fetchColumn();
            return ['status' => $count === 0 ? 'empty' : 'loaded', 'count' => $count];
        } catch (Throwable $error) {
            error_log('Student project count query: ' . $error->getMessage());
            return ['status' => 'error', 'count' => null];
        }
    }

    /** Devuelve loaded, empty o error sin confundir una falla técnica con ausencia. */
    public function getStudentProjectsResult(int $userId): array
    {
        if ($userId < 1 || !Database::isEnabled()) {
            return ['status' => 'error', 'items' => [], 'message' => 'No fue posible consultar tus proyectos en este momento.'];
        }
        try {
            $statement = Database::connection()->prepare("SELECT DISTINCT p.id,p.code,p.title,p.subtitle,p.status,p.current_stage,p.updated_at,p.is_available,
                pt.code AS type_key,pt.name AS type,c.name AS career,ap.name AS period,t.full_name AS tutor,
                (SELECT pd.version_number FROM project_deliveries pd WHERE pd.project_id=p.id ORDER BY pd.submitted_at DESC,pd.id DESC LIMIT 1) AS latest_delivery_version,
                (SELECT COUNT(*) FROM project_observations po WHERE po.project_id=p.id) AS observation_count,
                (SELECT COUNT(*) FROM project_files pf WHERE pf.project_id=p.id AND pf.deleted_at IS NULL AND pf.purged_at IS NULL) AS final_document_count
                FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id INNER JOIN careers c ON c.id=p.career_id
                INNER JOIN academic_periods ap ON ap.id=p.academic_period_id LEFT JOIN users t ON t.id=p.tutor_id
                INNER JOIN project_participants pp ON pp.project_id=p.id INNER JOIN users student_user ON student_user.id=pp.user_id
                WHERE p.deleted_at IS NULL AND p.withdrawn_at IS NULL
                  AND p.publication_origin = 'workflow'
                  AND pp.user_id=:user_id AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL
                  AND student_user.status='active' AND student_user.deleted_at IS NULL AND student_user.purged_at IS NULL
                ORDER BY p.updated_at DESC,p.id DESC");
            $statement->execute(['user_id' => $userId]);
            $rows = $statement->fetchAll();
            $situations = (new ProjectReviewSituationService())->forProjects(array_map('intval', array_column($rows, 'id')));
            $totalRows = count($rows);
            $items = array_map(static function (array $row, int $index) use ($situations, $totalRows): array {
                $status = (string) $row['status'];
                $labels = project_academic_labels($status);
                $latestDelivery = $row['latest_delivery_version'] === null ? null : ['version' => 'v' . (int) $row['latest_delivery_version']];
                $finalDocumentCount = $status === 'published' ? (int) ($row['final_document_count'] ?? 0) : 0;
                $situation = $situations[(int) $row['id']] ?? ProjectReviewSituationService::emptySituation();
                $lastActivity = 'Sin actividad registrada';
                if ((string) ($row['updated_at'] ?? '') !== '') {
                    try { $lastActivity = (new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Guayaquil'))->format('d/m/Y'); } catch (Throwable) {}
                }
                return [
                    'id' => (int) $row['id'], 'code' => (string) $row['code'], 'title' => (string) $row['title'],
                    'subtitle' => (string) ($row['subtitle'] ?? ''), 'status' => $labels['status'], 'status_key' => $status,
                    'type' => (string) $row['type'], 'type_key' => (string) $row['type_key'], 'career' => (string) $row['career'],
                    'period' => (string) $row['period'], 'tutor' => (string) ($row['tutor'] ?? ''), 'stage' => (string) ($row['current_stage'] ?? ''),
                    'last_activity' => 'Actualización del expediente · ' . $lastActivity, 'review_situation' => $situation,
                    'observations' => array_fill(0, (int) ($row['observation_count'] ?? 0), []),
                    'final_documents' => array_fill(0, $finalDocumentCount, []), 'latest_delivery' => $latestDelivery,
                    'key_dates' => [['label' => 'Inicio del expediente', 'value' => 'Registrado'], ['label' => 'Última actividad', 'value' => $lastActivity], ['label' => 'Próximo hito', 'value' => 'Por definir']],
                    'activity_order' => $totalRows - $index, 'tags' => [], 'technologies' => [],
                    'repository_id' => $status === 'published' ? (int) $row['id'] : null,
                    'repository_available' => $status === 'published' && !empty($row['is_available']) && $finalDocumentCount > 0,
                ];
            }, $rows, array_keys($rows));
            return ['status' => $items === [] ? 'empty' : 'loaded', 'items' => $items];
        } catch (Throwable $error) {
            error_log('Student projects query: ' . $error->getMessage());
            return ['status' => 'error', 'items' => [], 'message' => 'No fue posible consultar tus proyectos en este momento.'];
        }
    }

    public function findProjectForUser(int $projectId, int $userId): ?array
    {
        return (new ProjectRecordModel())->find($projectId, $userId, false);
    }
}
