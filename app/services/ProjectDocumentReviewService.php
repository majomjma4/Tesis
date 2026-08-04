<?php

declare(strict_types=1);

/** Lee el estado de revisión de la versión vigente de cada archivo sin consultas por elemento. */
final class ProjectDocumentReviewService
{
    public const STATUSES = ['development', 'under_review', 'approved', 'corrections_requested'];

    public function __construct(private readonly ?PDO $db = null) {}

    /** @return array{files:array<int,array<string,mixed>>,summary:array<string,mixed>} */
    public function describeCurrentFiles(int $projectId, array $files): array
    {
        $active = array_values(array_filter($files, static fn(array $file): bool =>
            (int)($file['project_id'] ?? 0) === $projectId && empty($file['deleted_at']) && empty($file['purged_at'])
        ));
        if ($active === []) return ['files'=>[], 'summary'=>$this->summary([])];

        $ids = array_map(static fn(array $file): int => (int)$file['id'], $active);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = ($this->db ?? Database::connection())->prepare(
            "SELECT f.id,
                    COALESCE(s.status,'development') document_status,
                    COALESCE(v.latest_version,0)+1 current_version_number,
                    f.created_at current_updated_at
             FROM project_files f
             LEFT JOIN project_file_review_states s
               ON s.file_id=f.id AND s.project_id=f.project_id AND s.checksum_sha256=f.checksum_sha256
             LEFT JOIN (
               SELECT file_id,MAX(version_number) latest_version FROM project_file_versions
               WHERE file_id IN ($placeholders) GROUP BY file_id
             ) v ON v.file_id=f.id
             WHERE f.project_id=? AND f.id IN ($placeholders)
               AND f.deleted_at IS NULL AND f.purged_at IS NULL"
        );
        $query->execute([...$ids, $projectId, ...$ids]);
        $states = [];
        foreach ($query->fetchAll() as $row) $states[(int)$row['id']] = $row;

        foreach ($active as &$file) {
            $state = $states[(int)$file['id']] ?? [];
            $status = in_array((string)($state['document_status'] ?? ''), self::STATUSES, true)
                ? (string)$state['document_status'] : 'development';
            $file['document_status'] = $status;
            $file['document_status_label'] = $this->label($status);
            $file['current_version_number'] = max(1, (int)($state['current_version_number'] ?? 1));
            $file['current_updated_at'] = $state['current_updated_at'] ?? ($file['created_at'] ?? null);
        }
        unset($file);
        return ['files'=>$active, 'summary'=>$this->summary($active)];
    }

    /** @return array<string,mixed> */
    public function summary(array $files): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($files as $file) {
            $status = (string)($file['document_status'] ?? 'development');
            if (isset($counts[$status])) $counts[$status]++;
        }
        $total = count($files);
        return $counts + [
            'total'=>$total,
            'all_active_documents_approved'=>$total > 0 && $counts['approved'] === $total,
        ];
    }

    /** Fuente central del requisito de aprobación para todos los documentos activos y vigentes. */
    public function approvalSummaryForProject(int $projectId): array
    {
        $db = $this->db ?? Database::connection();
        $query = $db->prepare(
            'SELECT id,project_id,original_name,checksum_sha256,created_at,deleted_at,purged_at
             FROM project_files WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL ORDER BY id'
        );
        $query->execute(['project'=>$projectId]);
        return $this->describeCurrentFiles($projectId, $query->fetchAll())['summary'];
    }

    public function label(string $status): string
    {
        return match ($status) {
            'under_review' => 'En revisión',
            'approved' => 'Aprobado',
            'corrections_requested' => 'Correcciones solicitadas',
            default => 'En desarrollo',
        };
    }

    /** Registra el resultado de la versión vigente; no expone por sí mismo ninguna acción HTTP. */
    public function recordCurrentStatus(int $projectId, int $fileId, string $expectedChecksum, string $status, ?int $actorId): void
    {
        if (!in_array($status, self::STATUSES, true)) throw new InvalidArgumentException('El estado documental no es válido.');
        if (!preg_match('/^[a-f0-9]{64}$/', $expectedChecksum)) throw new InvalidArgumentException('La versión documental no es válida.');
        $db = $this->db ?? Database::connection();
        $file = $db->prepare('SELECT checksum_sha256 FROM project_files WHERE id=:file AND project_id=:project AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');
        $file->execute(['file'=>$fileId, 'project'=>$projectId]);
        $checksum = $file->fetchColumn();
        if (!is_string($checksum) || !hash_equals($checksum, $expectedChecksum)) throw new RuntimeException('El documento cambió de versión. Actualiza la pantalla e inténtalo nuevamente.');
        $statement = $db->prepare(
            'INSERT INTO project_file_review_states(project_id,file_id,checksum_sha256,status,reviewed_by)
             VALUES(:project,:file,:checksum,:status,:actor)
             ON DUPLICATE KEY UPDATE status=VALUES(status),reviewed_by=VALUES(reviewed_by),updated_at=UTC_TIMESTAMP()'
        );
        $statement->execute(['project'=>$projectId,'file'=>$fileId,'checksum'=>$expectedChecksum,'status'=>$status,'actor'=>$actorId]);
    }
}
