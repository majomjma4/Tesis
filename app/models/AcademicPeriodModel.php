<?php

declare(strict_types=1);

final class AcademicPeriodModel
{
    public function active(): ?array
    {
        $statement = Database::connection()->query(
            "SELECT id, code, name, starts_on, ends_on, status
             FROM academic_periods
             WHERE status = 'active'
             ORDER BY starts_on DESC, id DESC
             LIMIT 1"
        );
        $period = $statement->fetch(PDO::FETCH_ASSOC);
        return $period ?: null;
    }
}
