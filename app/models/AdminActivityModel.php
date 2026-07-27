<?php

declare(strict_types=1);

final class AdminActivityModel
{
    public function forEntity(string $entityType, int $entityId, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(20, $limit));
        $offset = max(0, $offset);
        $statement = Database::connection()->prepare(
            'SELECT audit.id,audit.actor_user_id,audit.action,audit.action_label,
                    audit.element_label,audit.result,audit.details,audit.created_at,
                    actor.full_name actor_name,actor.email actor_email,
                    GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ", ") actor_roles
             FROM admin_audit_log audit
             LEFT JOIN users actor ON actor.id=audit.actor_user_id
             LEFT JOIN user_roles actor_roles ON actor_roles.user_id=actor.id
             LEFT JOIN roles ON roles.id=actor_roles.role_id
             WHERE audit.entity_type=:entity_type AND audit.entity_id=:entity_id
               AND audit.result="correct"
             GROUP BY audit.id,audit.actor_user_id,audit.action,audit.action_label,
                      audit.element_label,audit.result,audit.details,audit.created_at,
                      actor.full_name,actor.email
             ORDER BY audit.created_at DESC,audit.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('entity_type', $entityType);
        $statement->bindValue('entity_id', $entityId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);

        return [
            'items' => array_map([$this, 'normalize'], $rows),
            'has_more' => $hasMore,
            'next_offset' => $offset + count($rows),
            'incomplete_count' => $this->countIncompleteSupportMaterialEvents($entityId),
        ];
    }

    public function countIncompleteSupportMaterialEvents(int $materialId): int
    {
        return count($this->incompleteSupportMaterialEvents($materialId));
    }

    public function deleteIncompleteSupportMaterialEvents(int $materialId): int
    {
        $rows = $this->incompleteSupportMaterialEvents($materialId, true);
        $ids = array_values(array_map(static fn(array $row): int => (int) $row['id'], $rows));
        if ($ids === []) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::connection()->prepare(
            "DELETE FROM admin_audit_log
             WHERE id IN ({$placeholders})
               AND entity_type='support_material' AND entity_id=?
               AND module='Repositorio'
               AND action IN ('support_material.updated','support_material_updated')"
        );
        $statement->execute([...$ids, $materialId]);
        return $statement->rowCount();
    }

    private function incompleteSupportMaterialEvents(int $materialId, bool $forUpdate = false): array
    {
        if ($materialId < 1) return [];
        $statement = Database::connection()->prepare(
            "SELECT id,action,module,entity_type,entity_id,details
             FROM admin_audit_log
             WHERE entity_type='support_material' AND entity_id=:entity_id
               AND module='Repositorio' AND result='correct'
               AND action IN ('support_material.updated','support_material_updated')
             ORDER BY id" . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute(['entity_id' => $materialId]);
        return array_values(array_filter(
            $statement->fetchAll(),
            fn(array $row): bool => $this->isIncompleteSupportMaterialEvent($row, $materialId)
        ));
    }

    private function isIncompleteSupportMaterialEvent(array $row, int $materialId): bool
    {
        if ((string) ($row['entity_type'] ?? '') !== 'support_material'
            || (int) ($row['entity_id'] ?? 0) !== $materialId
            || (string) ($row['module'] ?? '') !== 'Repositorio'
            || !in_array((string) ($row['action'] ?? ''), ['support_material.updated', 'support_material_updated'], true)) {
            return false;
        }
        return $this->recoverableChanges($row['details'] ?? null) === [];
    }

    private function recoverableChanges(mixed $details): array
    {
        if (is_array($details)) {
            $decoded = $details;
        } else {
            $raw = trim((string) $details);
            if ($raw === '') return [];
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return [];
        }
        $source = is_array($decoded['changes'] ?? null) ? $decoded['changes'] : [];
        if ($source === [] && isset($decoded['field']) && (array_key_exists('old', $decoded) || array_key_exists('previous', $decoded)) && array_key_exists('new', $decoded)) {
            $source = [$decoded];
        }
        $changes = [];
        foreach ($source as $change) {
            if (!is_array($change) || !isset($change['field']) || !array_key_exists('new', $change)
                || (!array_key_exists('old', $change) && !array_key_exists('previous', $change))) continue;
            $changes[] = [
                'field' => (string) $change['field'],
                'label' => (string) ($change['label'] ?? $change['field']),
                'old' => $change['old'] ?? $change['previous'] ?? null,
                'new' => $change['new'],
            ];
        }
        return $changes;
    }

    private function normalize(array $row): array
    {
        $changes = $this->recoverableChanges($row['details'] ?? null);
        $decodedDetails = json_decode((string) ($row['details'] ?? ''), true);
        $cleanupDetails = (string) $row['action'] === 'support_material.history_cleaned' && is_array($decodedDetails)
            ? [
                'deleted_count' => max(0, (int) ($decodedDetails['deleted_count'] ?? 0)),
                'reason' => (string) ($decodedDetails['reason'] ?? ''),
            ]
            : null;
        $legacyWithoutDetails = in_array((string) $row['action'], ['support_material.updated', 'support_material_updated'], true)
            && $changes === [];

        $utc = new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC'));
        $config = require APP_PATH . '/config/app.php';
        $timezoneName = (string) ($config['timezone'] ?? 'America/Guayaquil');
        if (!in_array($timezoneName, timezone_identifiers_list(), true)) $timezoneName = 'America/Guayaquil';
        $localDate = $utc->setTimezone(new DateTimeZone($timezoneName));

        return [
            'id' => (int) $row['id'],
            'action' => (string) $row['action'],
            'action_label' => $this->actionLabel((string) $row['action'], (string) ($row['action_label'] ?? '')),
            'summary' => (string) ($row['element_label'] ?? 'Material de apoyo'),
            'actor' => [
                'name' => trim((string) ($row['actor_name'] ?? '')) ?: 'Usuario no disponible',
                'email' => (string) ($row['actor_email'] ?? ''),
                'role' => (string) ($row['actor_roles'] ?? ''),
            ],
            'created_at' => $localDate->format(DateTimeInterface::ATOM),
            'created_at_label' => $this->formatDate($localDate),
            'changes' => $changes,
            'has_details' => $changes !== [],
            'legacy_without_details' => $legacyWithoutDetails,
            'cleanup' => $cleanupDetails,
        ];
    }

    private function actionLabel(string $action, string $fallback): string
    {
        return match ($action) {
            'support_material.updated', 'support_material_updated' => 'Editó la información del material',
            'support_material.created', 'support_material_created' => 'Creó el material de apoyo',
            'support_material.history_cleaned' => 'Realizó una depuración del historial administrativo',
            default => $fallback !== '' ? $fallback : 'Se actualizó el material',
        };
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        $months = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
            7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
        return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')]
            . ' de ' . $date->format('Y') . ', ' . $date->format('H:i');
    }
}
