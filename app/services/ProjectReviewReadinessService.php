<?php

declare(strict_types=1);

/** Central preflight for moving a project into academic review. */
final class ProjectReviewReadinessService
{
    public function check(int $projectId, bool $attemptConversion = true): array
    {
        $query = Database::connection()->prepare("SELECT id,project_id,original_name,storage_name,extension,size_bytes,checksum_sha256,deleted_at,purged_at FROM project_files WHERE project_id=:project AND deleted_at IS NULL AND purged_at IS NULL ORDER BY sort_order,id");
        $query->execute(['project'=>$projectId]);
        $pending = [];
        foreach ($query->fetchAll() as $file) {
            try { $representation = (new ProjectReviewRepresentationService())->forFile($file, $attemptConversion); }
            catch (Throwable $exception) { $representation = ['required'=>true,'ready'=>false,'reason'=>'invalid_document']; }
            if (!empty($representation['required']) && empty($representation['ready'])) $pending[] = ['file_id'=>(int)$file['id'],'name'=>(string)$file['original_name'],'reason'=>(string)($representation['reason']??'manual_pdf_required')];
        }
        return ['ready'=>$pending===[],'pending_review_representations'=>$pending];
    }
}
