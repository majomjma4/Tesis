<?php

declare(strict_types=1);

/** Lee el estado de revisión de la versión vigente de cada archivo sin consultas por elemento. */
final class ProjectDocumentReviewService
{
    public function observationsForRevision(int $projectId, int $fileId, string $checksum): array
    {
        $q = ($this->db ?? Database::connection())->prepare('SELECT po.*, u.full_name author_name FROM project_observations po JOIN users u ON u.id=po.author_id WHERE po.project_id=:project AND po.file_id=:file AND po.file_checksum_sha256=:checksum ORDER BY po.created_at,po.id');
        $q->execute(['project'=>$projectId,'file'=>$fileId,'checksum'=>$checksum]);
        return $q->fetchAll() ?: [];
    }
    public const STATUSES = ['development', 'under_review', 'approved', 'corrections_requested'];

    public function __construct(private readonly ?PDO $db = null) {}

    /** @return array{files:array<int,array<string,mixed>>,summary:array<string,mixed>} */
    public function describeCurrentFiles(int $projectId, array $files, bool $reviewScope = false, bool $forTeacherReview = false): array
    {
        $active = array_values(array_filter($files, static fn(array $file): bool =>
            (int)($file['project_id'] ?? 0) === $projectId && empty($file['deleted_at']) && empty($file['purged_at'])
        ));
        $active = $this->filesInReviewScope($projectId, $active, $reviewScope);
        if ($active === []) return ['files'=>[], 'summary'=>$this->summary([])];

        $ids = array_map(static fn(array $file): int => (int)$file['id'], $active);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db = $this->db ?? Database::connection();
        $query = $db->prepare(
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

        $closedMap = [];
        if ($forTeacherReview && !$this->isProjectUnderActiveReview($projectId)) {
            $closedMap = $this->getHistoricalClosedReviewMap($projectId);
        }

        foreach ($active as &$file) {
            $fileId = (int)$file['id'];
            if (!empty($closedMap[$fileId])) {
                $hist = $closedMap[$fileId];
                $status = in_array((string)($hist['document_status'] ?? ''), self::STATUSES, true)
                    ? (string)$hist['document_status'] : 'development';
                $file['checksum_sha256'] = (string)($hist['checksum'] ?? ($file['checksum_sha256'] ?? ''));
                $file['document_status'] = $status;
                $file['document_status_label'] = $this->label($status);
                $state = $states[$fileId] ?? [];
                $file['current_version_number'] = max(1, (int)($state['current_version_number'] ?? 1));
                $file['current_updated_at'] = $state['current_updated_at'] ?? ($file['created_at'] ?? null);
            } else {
                $state = $states[$fileId] ?? [];
                $status = in_array((string)($state['document_status'] ?? ''), self::STATUSES, true)
                    ? (string)$state['document_status'] : 'development';
                $file['document_status'] = $status;
                $file['document_status_label'] = $this->label($status);
                $file['current_version_number'] = max(1, (int)($state['current_version_number'] ?? 1));
                $file['current_updated_at'] = $state['current_updated_at'] ?? ($file['created_at'] ?? null);
            }
        }
        unset($file);
        return ['files'=>$active, 'summary'=>$this->summary($active)];
    }

    public function isProjectUnderActiveReview(int $projectId): bool
    {
        $db = $this->db ?? Database::connection();
        $p = $db->prepare("SELECT status FROM projects WHERE id=:id AND deleted_at IS NULL");
        $p->execute(['id'=>$projectId]);
        $status = strtolower(trim((string)($p->fetchColumn() ?: '')));
        return $status === 'under_review';
    }

    public function getHistoricalClosedReviewMap(int $projectId): array
    {
        $db = $this->db ?? Database::connection();
        $audits = $db->prepare("
            SELECT new_state, created_at
            FROM project_audit_log
            WHERE project_id = :project
              AND action IN ('project_document_review_completed', 'project_observation_batch_created')
            ORDER BY id ASC
        ");
        $audits->execute(['project'=>$projectId]);
        $rows = $audits->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $a) {
            $payload = json_decode($a['new_state'] ?? '{}', true);
            $documents = is_array($payload['documents'] ?? null) ? $payload['documents'] : [];
            foreach ($documents as $doc) {
                $fileId = (int) ($doc['file_id'] ?? 0);
                if ($fileId > 0 && !empty($doc['checksum']) && !empty($doc['status'])) {
                    $map[$fileId] = [
                        'file_id' => $fileId,
                        'checksum' => (string) $doc['checksum'],
                        'document_status' => (string) $doc['status'],
                        'closed_at' => $a['created_at'],
                    ];
                }
            }
        }
        return $map;
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
            'SELECT id,project_id,original_name,checksum_sha256,created_at,deleted_at,purged_at,delivery_id
             FROM project_files WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL ORDER BY id'
        );
        $query->execute(['project'=>$projectId]);
        return $this->describeCurrentFiles($projectId, $query->fetchAll(), true)['summary'];
    }

    /**
     * Returns the active files that belong to the academic review scope.
     * Modern projects exclude workspace files until they are assigned to a delivery;
     * legacy projects without deliveries retain the historical behavior.
     */
    public function filesInReviewScope(int $projectId, array $files, bool $reviewScope = true): array
    {
        if (!$reviewScope || !$this->usesModernDeliveries($projectId)) return $files;
        return array_values(array_filter($files, static fn(array $file): bool => !empty($file['delivery_id'])));
    }

    /** @return list<array<string,mixed>> */
    public function loadFilesInReviewScope(PDO $db, int $projectId, bool $forUpdate = false): array
    {
        $query = $db->prepare(
            'SELECT id,project_id,original_name,checksum_sha256,created_at,deleted_at,purged_at,delivery_id
             FROM project_files WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL ORDER BY id'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $query->execute(['project'=>$projectId]);
        return $this->filesInReviewScope($projectId, $query->fetchAll(), true);
    }

    private function usesModernDeliveries(int $projectId): bool
    {
        $db = $this->db ?? Database::connection();
        $query = $db->prepare('SELECT 1 FROM project_deliveries WHERE project_id=:project LIMIT 1');
        $query->execute(['project'=>$projectId]);
        return (bool)$query->fetchColumn();
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
