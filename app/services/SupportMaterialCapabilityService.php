<?php

declare(strict_types=1);

/** Centraliza la gestión de Material de apoyo por rol, colaboración y propiedad persistida. */
final class SupportMaterialCapabilityService
{
    public function canCreate(AuthSessionService $session): bool
    {
        return $session->hasAdminAccess()
            || in_array('teacher', array_map('strtolower', $session->roles()), true);
    }

    public function canEditInformation(AuthSessionService $session, ?array $material): bool
    {
        if ($material === null) return false;
        return $this->isAdministrator($session) || $this->isTeacherOwner($session, $material);
    }

    public function canManageFiles(AuthSessionService $session, ?array $material): bool
    {
        if ($material === null) return false;
        return $this->isAdministrator($session) || $this->isTeacherOwner($session, $material);
    }

    public function canChangeStatus(AuthSessionService $session, ?array $material): bool
    {
        if ($material === null) return false;
        return $this->isAdministrator($session) || $this->isTeacherOwner($session, $material);
    }

    public function canWithdraw(AuthSessionService $session, ?array $material): bool
    {
        return $this->canChangeStatus($session, $material);
    }

    public function canDelete(AuthSessionService $session, ?array $material): bool
    {
        if ($material === null) return false;
        return $this->isAdministrator($session) || $this->isTeacherOwner($session, $material);
    }

    public function canManage(AuthSessionService $session, ?array $material): bool
    {
        try { $this->assertCanManage($session, $material); return true; }
        catch (SupportMaterialAccessException) { return false; }
    }

    public function assertCanCreate(AuthSessionService $session): void
    {
        if ($this->canCreate($session)) return;
        throw new SupportMaterialAccessException('No tienes autorización para crear materiales de apoyo.');
    }

    public function assertCanManage(AuthSessionService $session, ?array $material): void
    {
        if ($material === null) throw new SupportMaterialAccessException('El material solicitado no existe.', 404);
        if ($this->isAdministrator($session)) return;
        if (!$this->isTeacherOwner($session, $material)) {
            throw new SupportMaterialAccessException('No tienes autorización para gestionar este material de apoyo.');
        }
    }

    private function isAdministrator(AuthSessionService $session): bool
    {
        return $session->isAdminModeActive() && $session->hasAdminAccess();
    }

    private function isTeacherOwner(AuthSessionService $session, array $material): bool
    {
        return in_array('teacher', array_map('strtolower', $session->roles()), true)
            && (int) ($session->userId() ?? 0) > 0
            && (int) ($material['created_by'] ?? 0) === (int) ($session->userId() ?? 0);
    }

    public function assertCanEditInformation(AuthSessionService $session, ?array $material): void
    {
        if ($material === null) throw new SupportMaterialAccessException('El material solicitado no existe.', 404);
        if ($this->canEditInformation($session, $material)) return;
        throw new SupportMaterialAccessException('No tienes autorización para editar la información de este material de apoyo.');
    }

    public function assertCanManageFiles(AuthSessionService $session, ?array $material): void
    {
        if ($material === null) throw new SupportMaterialAccessException('El material solicitado no existe.', 404);
        if ($this->canManageFiles($session, $material)) return;
        throw new SupportMaterialAccessException('No tienes autorización para gestionar los archivos de este material de apoyo.');
    }

    public function assertCanChangeStatus(AuthSessionService $session, ?array $material): void
    {
        if ($material === null) throw new SupportMaterialAccessException('El material solicitado no existe.', 404);
        if ($this->canChangeStatus($session, $material)) return;
        throw new SupportMaterialAccessException('Solo el docente propietario o el administrador pueden modificar el estado o visibilidad de este material de apoyo.');
    }

    public function assertCanDelete(AuthSessionService $session, ?array $material): void
    {
        if ($material === null) throw new SupportMaterialAccessException('El material solicitado no existe.', 404);
        if ($this->canDelete($session, $material)) return;
        throw new SupportMaterialAccessException('Solo el docente propietario o el administrador pueden eliminar este material de apoyo.');
    }
}

final class SupportMaterialAccessException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 403)
    {
        parent::__construct($message);
    }
}
