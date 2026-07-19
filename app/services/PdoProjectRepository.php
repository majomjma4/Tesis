<?php
declare(strict_types=1);

/** Repositorio preparado para MariaDB; no se usa mientras DB_ENABLED sea false. */
final class PdoProjectRepository implements ProjectRepositoryInterface
{
    public function __construct(private readonly ?PDO $db = null) {}
    private function connection(): PDO { return $this->db ?? Database::connection(); }

    public function findVisibleByUser(int $userId): array
    {
        $sql = "SELECT DISTINCT p.id, p.code, pt.code AS type_key, pt.name AS type, p.title, p.summary,
                       p.status, p.current_stage, ap.code AS period, c.name AS career, p.updated_at
                FROM projects p
                INNER JOIN project_types pt ON pt.id = p.project_type_id
                LEFT JOIN academic_periods ap ON ap.id = p.academic_period_id
                LEFT JOIN careers c ON c.id = p.career_id
                INNER JOIN project_participants pp ON pp.project_id = p.id AND pp.user_id = :user_id AND pp.status = 'active'
                WHERE p.deleted_at IS NULL ORDER BY p.updated_at DESC";
        $statement = $this->connection()->prepare($sql); $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function findVisibleProject(int $projectId, int $userId): ?array
    {
        foreach ($this->findVisibleByUser($userId) as $project) if ((int) $project['id'] === $projectId) return $project;
        return null;
    }

    public function createProject(array $project, array $participants, array $files): int
    {
        $db = $this->connection(); $db->beginTransaction();
        try {
            $statement = $db->prepare('INSERT INTO projects (code, project_type_id, career_id, academic_period_id, title, summary, modality, research_line_id, academic_subject_id, proposed_tutor_id, tutor_id, status, current_stage, created_by) VALUES (:code,:project_type_id,:career_id,:academic_period_id,:title,:summary,:modality,:research_line_id,:academic_subject_id,:proposed_tutor_id,:tutor_id,:status,:current_stage,:created_by)');
            $statement->execute($project); $projectId = (int) $db->lastInsertId();
            $participantStatement = $db->prepare('INSERT INTO project_participants (project_id,user_id,role_code,permission_level,is_leader) VALUES (:project_id,:user_id,:role_code,:permission_level,:is_leader)');
            foreach ($participants as $participant) $participantStatement->execute($participant + ['project_id' => $projectId]);
            $fileStatement = $db->prepare('INSERT INTO project_files (project_id,delivery_id,category,original_name,storage_name,storage_path,mime_type,extension,size_bytes,checksum_sha256,uploaded_by) VALUES (:project_id,NULL,:category,:original_name,:storage_name,:storage_path,:mime_type,:extension,:size_bytes,:checksum_sha256,:uploaded_by)');
            foreach ($files as $file) $fileStatement->execute($file + ['project_id' => $projectId]);
            $db->commit(); return $projectId;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }
}
