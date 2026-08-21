<?php

declare(strict_types=1);

/** Resuelve el cargo especial de Gestión de Titulación para docentes. */
final class TeacherThesisCapabilityService
{
    public function canManageCurrentUser(): bool
    {
        $session = new AuthSessionService();
        return $this->canManage((int) ($session->userId() ?? 0), $session->roles(), $session->isAdminModeActive());
    }

    /** @return array{manage_thesis_process:bool} */
    public function capabilitiesForCurrentUser(): array
    {
        return ['manage_thesis_process' => $this->canManageCurrentUser()];
    }

    public function canManage(int $userId, array $roles, bool $hasAdministrativeAccess = false): bool
    {
        if ($userId < 1 || $hasAdministrativeAccess || !in_array('teacher', array_map('strtolower', $roles), true)) return false;
        $query = Database::connection()->prepare('SELECT 1 FROM teacher_profiles WHERE user_id=:user AND can_manage_thesis=1 LIMIT 1');
        $query->execute(['user' => $userId]);
        return (bool) $query->fetchColumn();
    }
}
