<?php

declare(strict_types=1);

final class AcademicPeriodModel
{
    public function all(): array
    {
        return Database::connection()->query(
            "SELECT id, code, name, starts_on, ends_on, status
             FROM academic_periods
             ORDER BY starts_on DESC, id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function active(): ?array
    {
        $period = array_values(array_filter($this->all(), static fn(array $item): bool => ($item['status'] ?? '') === 'active'))[0] ?? null;
        return $period ?: null;
    }
}
