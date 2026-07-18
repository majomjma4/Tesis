<?php

declare(strict_types=1);

/** Centraliza identidad temporal y matriz de permisos del módulo académico. */
final class ProjectAccessService
{
    private const PERMISSIONS = [
        'student' => ['project.view', 'delivery.create', 'observation.reply', 'observation.address', 'comment.create', 'calendar.manage'],
        'tutor' => ['project.view', 'delivery.review', 'observation.create', 'observation.resolve', 'comment.create', 'status.review'],
        'cotutor' => ['project.view', 'delivery.review', 'observation.create', 'comment.create'],
        'jury' => ['project.view', 'delivery.review', 'observation.create', 'observation.resolve', 'comment.create', 'defense.evaluate'],
        'coordinator' => ['project.view', 'participant.manage', 'status.manage', 'final_document.validate', 'repository.publish', 'audit.view'],
        'administrator' => ['*'],
    ];

    public function currentUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return max(1, (int) ($_SESSION['user_id'] ?? 1));
    }

    public function currentRole(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $role = strtolower((string) ($_SESSION['role'] ?? 'student'));
        return array_key_exists($role, self::PERMISSIONS) ? $role : 'student';
    }

    public function can(string $permission): bool
    {
        $granted = self::PERMISSIONS[$this->currentRole()];
        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    public function permissions(): array
    {
        return self::PERMISSIONS[$this->currentRole()];
    }
}
