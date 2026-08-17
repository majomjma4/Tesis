<?php

declare(strict_types=1);

/** Central preflight for moving a project into academic review. */
final class ProjectReviewReadinessService
{
    public function check(int $projectId, bool $attemptConversion = true): array
    {
        $query = Database::connection()->prepare("SELECT f.id,f.project_id,f.original_name,f.storage_name,f.extension,f.size_bytes,f.checksum_sha256,f.deleted_at,f.purged_at
            FROM project_files f
            LEFT JOIN project_file_review_states s ON s.project_id=f.project_id AND s.file_id=f.id AND s.checksum_sha256=f.checksum_sha256
            WHERE f.project_id=:project AND f.deleted_at IS NULL AND f.purged_at IS NULL
              AND COALESCE(s.status,'development') IN ('development','corrections_requested')
            ORDER BY f.sort_order,f.id");
        $query->execute(['project'=>$projectId]);
        $files = $query->fetchAll();
        $pending = [];
        foreach ($files as $file) {
            try { $representation = (new ProjectReviewRepresentationService())->forFile($file, $attemptConversion); }
            catch (Throwable $exception) { $representation = ['required'=>true,'ready'=>false,'reason'=>'invalid_document']; }
            if (!empty($representation['required']) && empty($representation['ready'])) $pending[] = ['file_id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'reason'=>(string)($representation['reason']??'manual_pdf_required')];
        }
        $activeFileCount = count($files);
        return [
            'active_file_count' => $activeFileCount,
            'ready' => $activeFileCount > 0 && $pending === [],
            'reason' => $activeFileCount === 0 ? 'no_active_files' : ($pending === [] ? null : 'pending_review_representations'),
            'message' => $activeFileCount === 0 ? 'No hay archivos para enviar a revisión.' : null,
            'pending_review_representations' => $pending,
        ];
    }
}
