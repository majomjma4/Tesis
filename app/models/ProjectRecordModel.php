<?php

declare(strict_types=1);

/** Datos reales y normalizados para el expediente digital de un proyecto. */
final class ProjectRecordModel
{
    public function find(int $projectId, ?int $userId, bool $administrator, bool $publishedOnly = false): ?array
    {
        $db = Database::connection();
        $sql = "SELECT p.*, pt.code AS type_code, pt.name AS type_name, c.name AS career_name,
                       ap.name AS period_name, s.code AS subject_code, s.name AS subject_name,
                       rl.name AS research_line_name, tutor.id AS tutor_user_id,
                       tutor.username AS tutor_username, tutor.full_name AS tutor_name,
                       tutor.email AS tutor_email
                FROM projects p
                INNER JOIN project_types pt ON pt.id=p.project_type_id
                INNER JOIN careers c ON c.id=p.career_id
                INNER JOIN academic_periods ap ON ap.id=p.academic_period_id
                LEFT JOIN academic_subjects s ON s.id=p.academic_subject_id
                LEFT JOIN research_lines rl ON rl.id=p.research_line_id
                LEFT JOIN users tutor ON tutor.id=p.tutor_id
                WHERE p.id=:id AND p.deleted_at IS NULL";
        if ($publishedOnly) $sql .= " AND p.status='published'" . ($administrator ? '' : ' AND p.is_available=1') . "
            AND EXISTS (SELECT 1 FROM project_files visible_file WHERE visible_file.project_id=p.id AND visible_file.deleted_at IS NULL)
            AND EXISTS (SELECT 1 FROM project_participants visible_student INNER JOIN student_profiles visible_profile ON visible_profile.user_id=visible_student.user_id WHERE visible_student.project_id=p.id AND visible_student.role_code='student' AND visible_student.status='active' AND visible_student.removed_at IS NULL)";
        if (!$administrator && !$publishedOnly) $sql .= " AND (p.created_by=:viewer OR EXISTS (SELECT 1 FROM project_participants access_participant WHERE access_participant.project_id=p.id AND access_participant.user_id=:viewer AND access_participant.status='active'))";
        $statement = $db->prepare($sql);
        $parameters = ['id' => $projectId];
        if (!$administrator && !$publishedOnly) $parameters['viewer'] = (int) $userId;
        $statement->execute($parameters);
        $project = $statement->fetch();
        if (!$project) return null;

        $project['participants'] = $this->rows($db, "SELECT pp.user_id,pp.role_code,pp.permission_level,pp.is_leader,pp.assigned_at,
                   u.username,u.full_name,u.email,(tp.user_id IS NOT NULL) AS is_teacher
            FROM project_participants pp INNER JOIN users u ON u.id=pp.user_id
            LEFT JOIN teacher_profiles tp ON tp.user_id=u.id
            WHERE pp.project_id=:id AND pp.status='active' AND pp.removed_at IS NULL
            ORDER BY FIELD(pp.role_code,'student','tutor','cotutor','tribunal','jury'),pp.is_leader DESC,u.full_name", $projectId);
        $project['student_authors'] = array_values(array_filter(
            $project['participants'],
            static fn (array $participant): bool => (string) $participant['role_code'] === 'student'
        ));
        $leaderId = null;
        foreach ($project['student_authors'] as $author) {
            if (!empty($author['is_leader'])) {
                $leaderId = (int) $author['user_id'];
                break;
            }
        }
        if ($leaderId === null) {
            foreach ($project['student_authors'] as $author) {
                if ((int) $author['user_id'] === (int) $project['created_by']) {
                    $leaderId = (int) $author['user_id'];
                    break;
                }
            }
        }
        foreach ($project['student_authors'] as &$author) {
            $author['is_display_leader'] = $leaderId !== null && (int) $author['user_id'] === $leaderId;
        }
        unset($author);
        $project['keywords'] = (new ProjectKeywordModel())->forProject($projectId, $db);
        $project['files'] = $this->rows($db, "SELECT pf.*,u.full_name AS uploaded_by_name,pd.version_number,pd.title AS delivery_title,pd.status AS delivery_status,pd.submitted_at
            FROM project_files pf LEFT JOIN users u ON u.id=pf.uploaded_by LEFT JOIN project_deliveries pd ON pd.id=pf.delivery_id
            WHERE pf.project_id=:id AND pf.deleted_at IS NULL AND pf.purged_at IS NULL ORDER BY pf.sort_order,pf.created_at DESC,pf.id DESC", $projectId);
        $project['deliveries'] = $this->rows($db, "SELECT pd.*,u.full_name AS author_name,ps.label AS stage_label
            FROM project_deliveries pd INNER JOIN users u ON u.id=pd.submitted_by LEFT JOIN project_stages ps ON ps.id=pd.stage_id
            WHERE pd.project_id=:id ORDER BY pd.version_number DESC,pd.submitted_at DESC", $projectId);
        $project['observations'] = $this->rows($db, "SELECT po.*,u.full_name AS author_name,pd.version_number
            FROM project_observations po INNER JOIN users u ON u.id=po.author_id LEFT JOIN project_deliveries pd ON pd.id=po.delivery_id
            WHERE po.project_id=:id ORDER BY po.created_at DESC,po.id DESC", $projectId);
        $project['responses'] = $this->rows($db, "SELECT response.*,u.full_name AS author_name,po.project_id FROM observation_responses response INNER JOIN project_observations po ON po.id=response.observation_id INNER JOIN users u ON u.id=response.author_id WHERE po.project_id=:id ORDER BY response.created_at DESC,response.id DESC", $projectId);
        $project['comments'] = $this->rows($db, "SELECT pc.*,u.full_name AS author_name FROM project_comments pc INNER JOIN users u ON u.id=pc.author_id WHERE pc.project_id=:id AND pc.deleted_at IS NULL ORDER BY pc.created_at DESC", $projectId);
        $project['stages'] = $this->rows($db, "SELECT stage_code,label,status,completed_at FROM project_stages WHERE project_id=:id ORDER BY position", $projectId);
        $project['activity'] = $this->rows($db, "SELECT pal.id,pal.action,pal.entity_type,pal.entity_id,pal.previous_state,pal.new_state,pal.reason,pal.created_at,u.full_name AS actor_name FROM project_audit_log pal LEFT JOIN users u ON u.id=pal.user_id WHERE pal.project_id=:id ORDER BY pal.created_at DESC,pal.id DESC", $projectId);
        $project['tribunal_approved_at'] = null;
        foreach ($project['activity'] as $audit) if (in_array((string)$audit['action'],['project_tribunal_approved','tribunal_approved'],true)) { $project['tribunal_approved_at']=$audit['created_at']; break; }
        $approvalStatus = (string) $project['type_code'] === 'thesis' ? 'tribunal_approved' : 'approved';
        $project['academic_approved_at'] = $approvalStatus === 'approved' && !empty($project['approved_at'])
            ? $project['approved_at'] : $this->statusTransitionDate($project['activity'], $approvalStatus);
        $project['academic_completed_at'] = $project['academic_approved_at'];
        $project['repository_published_at'] = $this->latestDate(
            $project['published_at'] ?? null,
            $this->statusTransitionDate($project['activity'], 'published')
        );
        return $project;
    }

    public function findFile(int $projectId, int $fileId): ?array
    {
        $statement = Database::connection()->prepare("SELECT * FROM project_files WHERE id=:file_id AND project_id=:project_id AND deleted_at IS NULL AND purged_at IS NULL");
        $statement->execute(['file_id' => $fileId, 'project_id' => $projectId]);
        return $statement->fetch() ?: null;
    }

    private function rows(PDO $db, string $sql, int $projectId): array
    {
        $statement = $db->prepare($sql);
        $statement->execute(['id' => $projectId]);
        return $statement->fetchAll();
    }

    private function statusTransitionDate(array $activity, string $targetStatus): ?string
    {
        $labels = ['approved' => 'Aprobado', 'tribunal_approved' => 'Aprobado por el Tribunal', 'published' => 'Publicado'];
        foreach ($activity as $event) {
            $action = (string) ($event['action'] ?? '');
            if (($targetStatus === 'approved' && $action === 'project_approved')
                || ($targetStatus === 'tribunal_approved' && in_array($action, ['project_tribunal_approved', 'tribunal_approved'], true))
                || ($targetStatus === 'published' && in_array($action, ['project_published', 'project_republished'], true))) {
                return (string) $event['created_at'];
            }
            $state = json_decode((string) ($event['new_state'] ?? ''), true);
            if (!is_array($state)) continue;
            if (($state['status'] ?? null) === $targetStatus || ($state['Estado'] ?? null) === ($labels[$targetStatus] ?? null)) return (string) $event['created_at'];
            foreach ((array) ($state['_history_changes'] ?? []) as $change) {
                if (($change['field'] ?? '') === 'Estado' && ($change['to'] ?? '') === ($labels[$targetStatus] ?? '')) return (string) $event['created_at'];
            }
        }
        return null;
    }

    private function latestDate(?string $first, ?string $second): ?string
    {
        if (!$first) return $second ?: null;
        if (!$second) return $first;
        return strtotime($second) > strtotime($first) ? $second : $first;
    }

}
