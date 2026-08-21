<?php

declare(strict_types=1);

/** Determines which documents from the previous review still require correction. */
final class ProjectCorrectionReadinessService
{
    public function __construct(private readonly ?PDO $db = null) {}

    /** @return array{has_deliveries:bool,source:string,required:array<int,array{file_id:int,checksum:string,name:string}>,total_needed:int,completed:int,pending:int[],eligible:bool} */
    public function forProject(int $projectId): array
    {
        $db = $this->db ?? Database::connection();
        $hasDeliveries = $this->hasDeliveries($db, $projectId);
        if (!$hasDeliveries) return ['has_deliveries'=>false,'source'=>'legacy','required'=>[],'total_needed'=>0,'completed'=>0,'pending'=>[],'eligible'=>true];
        $required = $this->requiredFromLatestReview($db, $projectId);
        if ($required === null) $required = $this->fallbackRequiredFromCurrentState($db, $projectId);
        $current = $this->currentFiles($db, $projectId); $completed = 0; $pending = [];
        foreach ($required as $item) {
            $file = $current[$item['file_id']] ?? null;
            $corrected = $file !== null
                && !hash_equals(strtolower($item['checksum']), strtolower((string)$file['checksum_sha256']))
                && (string)$file['document_status'] !== 'corrections_requested';
            if ($corrected) $completed++; else $pending[] = $item['file_id'];
        }
        $total = count($required);
        return ['has_deliveries'=>true,'source'=>'latest_review','required'=>array_values($required),'total_needed'=>$total,'completed'=>$completed,'pending'=>$pending,'eligible'=>$completed === $total];
    }

    private function hasDeliveries(PDO $db, int $projectId): bool
    {
        $query = $db->prepare('SELECT 1 FROM project_deliveries WHERE project_id=:project LIMIT 1');
        $query->execute(['project'=>$projectId]); return (bool)$query->fetchColumn();
    }

    /** @return array<int,array{file_id:int,checksum:string,name:string}>|null */
    private function requiredFromLatestReview(PDO $db, int $projectId): ?array
    {
        $query = $db->prepare("SELECT new_state FROM project_audit_log WHERE project_id=:project AND action='project_document_review_completed' ORDER BY id DESC LIMIT 1");
        $query->execute(['project'=>$projectId]); $payload = $query->fetchColumn();
        if (!is_string($payload) || $payload === '') return null;
        $state = json_decode($payload, true);
        if (!is_array($state) || !array_key_exists('documents', $state) || !is_array($state['documents'])) return null;
        $required = [];
        foreach ($state['documents'] as $document) {
            if (!is_array($document) || (string)($document['status'] ?? '') !== 'corrections_requested') continue;
            $fileId = (int)($document['file_id'] ?? 0); $checksum = strtolower(trim((string)($document['checksum'] ?? '')));
            if ($fileId < 1 || !preg_match('/^[a-f0-9]{64}$/', $checksum)) continue;
            $required[$fileId] = ['file_id'=>$fileId,'checksum'=>$checksum,'name'=>(string)($document['name'] ?? 'Documento')];
        }
        return $required;
    }

    /** Compatibility fallback for modern records without a review audit payload. */
    private function fallbackRequiredFromCurrentState(PDO $db, int $projectId): array
    {
        $query = $db->prepare("SELECT f.id file_id,f.checksum_sha256 checksum,f.original_name name FROM project_files f LEFT JOIN project_file_review_states s ON s.project_id=f.project_id AND s.file_id=f.id AND s.checksum_sha256=f.checksum_sha256 WHERE f.project_id=:project AND f.deleted_at IS NULL AND f.purged_at IS NULL AND s.status='corrections_requested'");
        $query->execute(['project'=>$projectId]); $required = [];
        foreach ($query->fetchAll() as $row) $required[(int)$row['file_id']] = ['file_id'=>(int)$row['file_id'],'checksum'=>strtolower((string)$row['checksum']),'name'=>(string)$row['name']];
        return $required;
    }

    /** @return array<int,array{checksum_sha256:string,document_status:string}> */
    private function currentFiles(PDO $db, int $projectId): array
    {
        $query = $db->prepare("SELECT f.id,f.checksum_sha256,COALESCE(s.status,'development') document_status FROM project_files f LEFT JOIN project_file_review_states s ON s.project_id=f.project_id AND s.file_id=f.id AND s.checksum_sha256=f.checksum_sha256 WHERE f.project_id=:project AND f.deleted_at IS NULL AND f.purged_at IS NULL");
        $query->execute(['project'=>$projectId]); $files = [];
        foreach ($query->fetchAll() as $row) $files[(int)$row['id']] = $row; return $files;
    }
}
