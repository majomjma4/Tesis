<?php

declare(strict_types=1);

/** Deriva la situación de revisión sin duplicarla en la tabla projects. */
final class ProjectReviewSituationService
{
    public static function normalizeFilter(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['pending', 'none', 'addressed'], true) ? $value : '';
    }

    /** Agregación exclusiva por proyecto; nunca cuenta una vez por observación. */
    public function aggregate(?PDO $connection = null, bool $activeOnly = false): array
    {
        $db = $connection ?? Database::connection();
        $activeCondition = $activeOnly ? " AND p.status<>'published'" : '';
        $row = $db->query(
            "SELECT
             COALESCE(SUM(COALESCE(review.pending_count,0)>0),0) pending,
             COALESCE(SUM(COALESCE(review.pending_count,0)=0 AND COALESCE(review.addressed_count,0)>0),0) addressed,
             COALESCE(SUM(COALESCE(review.observation_count,0)=0),0) none
             FROM projects p
             LEFT JOIN (
               SELECT project_id,COUNT(*) observation_count,
                 SUM(status='pending') pending_count,
                 SUM(status IN ('addressed','resolved')) addressed_count
               FROM project_observations GROUP BY project_id
             ) review ON review.project_id=p.id
             WHERE p.deleted_at IS NULL{$activeCondition}"
        )->fetch() ?: [];
        return ['pending'=>(int)($row['pending']??0),'addressed'=>(int)($row['addressed']??0),'none'=>(int)($row['none']??0)];
    }

    /** @param list<int> $projectIds @return array<int,array<string,mixed>> */
    public function forProjects(array $projectIds, ?PDO $connection = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $db = $connection ?? Database::connection();
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $db->prepare(
            "SELECT p.id,
             COUNT(DISTINCT CASE WHEN o.status='pending' THEN o.id END) pending_count,
             COUNT(DISTINCT CASE WHEN o.status IN ('addressed','resolved') THEN o.id END)>0 has_addressed,
             MAX(CASE WHEN a.action='project_corrections_requested' THEN a.created_at END) corrections_at,
             MAX(d.submitted_at) latest_delivery_at
             FROM projects p
             LEFT JOIN project_observations o ON o.project_id=p.id
             LEFT JOIN project_audit_log a ON a.project_id=p.id AND a.action='project_corrections_requested'
             LEFT JOIN project_deliveries d ON d.project_id=p.id
             WHERE p.id IN ($marks) GROUP BY p.id"
        );
        $statement->execute($ids);
        $result = [];
        foreach ($statement->fetchAll() as $row) $result[(int) $row['id']] = $this->normalizeRow($row);
        foreach ($ids as $id) $result[$id] ??= $this->emptySituation();
        return $result;
    }

    /** @return array{has_pending_observations:bool,pending_observations_count:int,has_addressed_observations:bool,latest_corrections_requested_at:?string,has_new_delivery_after_corrections:bool} */
    public function forProject(int $projectId, ?PDO $connection = null): array
    {
        if ($projectId < 1) return $this->emptySituation();
        $db = $connection ?? Database::connection();
        $statement = $db->prepare(
            "SELECT
             (SELECT COUNT(*) FROM project_observations WHERE project_id=:pending_project AND status='pending') pending_count,
             EXISTS(SELECT 1 FROM project_observations WHERE project_id=:addressed_project AND status IN ('addressed','resolved')) has_addressed,
             (SELECT MAX(created_at) FROM project_audit_log WHERE project_id=:audit_project AND action='project_corrections_requested') corrections_at,
             (SELECT MAX(submitted_at) FROM project_deliveries WHERE project_id=:delivery_project) latest_delivery_at"
        );
        $statement->execute([
            'pending_project' => $projectId,
            'addressed_project' => $projectId,
            'audit_project' => $projectId,
            'delivery_project' => $projectId,
        ]);
        $row = $statement->fetch() ?: [];
        return $this->normalizeRow($row);
    }

    public function filterCondition(string $situation, string $projectAlias = 'p'): ?string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $projectAlias)) throw new InvalidArgumentException('Alias de proyecto no válido.');
        return match ($situation) {
            'pending' => "EXISTS(SELECT 1 FROM project_observations review_observation WHERE review_observation.project_id={$projectAlias}.id AND review_observation.status='pending')",
            'none' => "NOT EXISTS(SELECT 1 FROM project_observations review_observation WHERE review_observation.project_id={$projectAlias}.id AND review_observation.status='pending')",
            'addressed' => "NOT EXISTS(SELECT 1 FROM project_observations pending_observation WHERE pending_observation.project_id={$projectAlias}.id AND pending_observation.status='pending') AND EXISTS(SELECT 1 FROM project_observations addressed_observation WHERE addressed_observation.project_id={$projectAlias}.id AND addressed_observation.status IN ('addressed','resolved'))",
            default => null,
        };
    }

    private function normalizeRow(array $row): array
    {
        $correctionsAt = !empty($row['corrections_at']) ? (string) $row['corrections_at'] : null;
        $latestDeliveryAt = !empty($row['latest_delivery_at']) ? (string) $row['latest_delivery_at'] : null;
        $pending = (int) ($row['pending_count'] ?? 0);
        return [
            'has_pending_observations' => $pending > 0,
            'pending_observations_count' => $pending,
            'has_addressed_observations' => !empty($row['has_addressed']),
            'latest_corrections_requested_at' => $correctionsAt,
            'has_new_delivery_after_corrections' => $correctionsAt !== null && $latestDeliveryAt !== null
                && strtotime($latestDeliveryAt) > strtotime($correctionsAt),
        ];
    }

    public static function emptySituation(): array
    {
        return [
            'has_pending_observations' => false,
            'pending_observations_count' => 0,
            'has_addressed_observations' => false,
            'latest_corrections_requested_at' => null,
            'has_new_delivery_after_corrections' => false,
        ];
    }
}
