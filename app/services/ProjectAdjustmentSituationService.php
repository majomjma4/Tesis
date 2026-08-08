<?php

declare(strict_types=1);

/** Resumen agregado de solicitudes administrativas; no consulta observaciones académicas. */
final class ProjectAdjustmentSituationService
{
    public function __construct(private readonly ?PDO $db = null) {}

    public function forProject(int $projectId): array
    {
        $statement = ($this->db ?? Database::connection())->prepare(
            "SELECT a.pending_count,latest.id latest_id,latest.message,latest.created_at,latest.status,u.full_name requested_by
             FROM (SELECT COUNT(CASE WHEN status='pending' THEN 1 END) pending_count,MAX(id) latest_id
                   FROM project_adjustment_requests WHERE project_id=:project) a
             LEFT JOIN project_adjustment_requests latest ON latest.id=a.latest_id
             LEFT JOIN users u ON u.id=latest.requested_by"
        );
        $statement->execute(['project'=>$projectId]);
        $aggregate = $statement->fetch() ?: [];
        $latest = null;
        if ((int)($aggregate['latest_id'] ?? 0) > 0) {
            $latest=['id'=>(int)$aggregate['latest_id'],'message'=>$aggregate['message'],'created_at'=>$aggregate['created_at'],
                'requested_by'=>$aggregate['requested_by'],'status'=>$aggregate['status']];
        }
        $count = (int)($aggregate['pending_count'] ?? 0);
        return [
            'has_pending_adjustments'=>$count > 0, 'pending_count'=>$count,
            'latest_request'=>$latest, 'latest_request_date'=>$latest['created_at'] ?? null,
            'latest_request_author'=>$latest['requested_by'] ?? null, 'latest'=>$latest,
        ];
    }
}
