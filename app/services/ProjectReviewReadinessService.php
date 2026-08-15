<?php

declare(strict_types=1);

/** Central preflight for moving a project into academic review. */
final class ProjectReviewReadinessService
{
    public function check(int $projectId, bool $attemptConversion = true): array
    {
        $query = Database::connection()->prepare("SELECT id,project_id,original_name,storage_name,extension,size_bytes,checksum_sha256,deleted_at,purged_at FROM project_files WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL ORDER BY sort_order,id");
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
