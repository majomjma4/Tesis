<?php

declare(strict_types=1);

/** Centraliza la gestión de Material de apoyo por rol y propiedad persistida. */
final class SupportMaterialCapabilityService
{
    public function assertCanCreate(AuthSessionService $session): void
    {
        if ($session->hasAdminAccess()) return;
        if (in_array('teacher', array_map('strtolower', $session->roles()), true)) return;
        throw new SupportMaterialAccessException('No tienes autorización para crear materiales de apoyo.');
    }

    public function assertCanManage(AuthSessionService $session, ?array $material): void
    {
        if ($material === null) throw new SupportMaterialAccessException('El material solicitado no existe.', 404);
        if ($session->hasAdminAccess()) return;
        $isTeacher = in_array('teacher', array_map('strtolower', $session->roles()), true);
        if (!$isTeacher || (int) ($session->userId() ?? 0) < 1
            || (int) ($material['created_by'] ?? 0) !== (int) $session->userId()) {
            throw new SupportMaterialAccessException('No tienes autorización para gestionar este material de apoyo.');
        }
    }
}

final class SupportMaterialAccessException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 403)
    {
        parent::__construct($message);
    }
}
