<?php
declare(strict_types=1);

/** Límite de persistencia: las vistas no conocerán SQL ni PDO. */
interface ProjectRepositoryInterface
{
    public function findVisibleByUser(int $userId): array;
    public function findVisibleProject(int $projectId, int $userId): ?array;
    public function createProject(array $project, array $participants, array $files): int;
}
