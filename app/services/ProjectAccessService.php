<?php

declare(strict_types=1);

/** Centraliza identidad temporal y matriz de permisos del módulo académico. */
final class ProjectAccessService
{
    private const PERMISSIONS = [
        'student' => ['project.view', 'project.create', 'delivery.create', 'observation.reply', 'observation.address', 'comment.create', 'calendar.manage'],
        'teacher' => ['project.view', 'project.create', 'delivery.review', 'observation.create', 'observation.resolve', 'comment.create', 'status.review', 'defense.evaluate'],
        'administrator' => ['*'],
    ];

    public function currentUserId(): int
    {
        return (new AuthSessionService())->userId() ?? 0;
    }

    public function currentRole(): string
    {
        $roles = $this->currentRoles();
        foreach (['administrator', 'teacher', 'student'] as $priority) if (in_array($priority, $roles, true)) return $priority;
        return 'student';
    }

    public function currentRoles(): array
    {
        $roles = (new AuthSessionService())->roles();
        $valid = array_values(array_filter(array_map('strtolower', $roles), static fn (string $role): bool => isset(self::PERMISSIONS[$role])));
        return $valid ?: ['student'];
    }

    public function can(string $permission): bool
    {
        foreach ($this->currentRoles() as $role) { $granted = self::PERMISSIONS[$role]; if (in_array('*', $granted, true) || in_array($permission, $granted, true)) return true; }
        return false;
    }

    public function permissions(): array
    {
        $permissions = [];
        foreach ($this->currentRoles() as $role) $permissions = array_merge($permissions, self::PERMISSIONS[$role]);
        return array_values(array_unique($permissions));
    }

    public function projectCreationPolicy(): array
    {
        $role = $this->currentRole();
        $actorType = match ($role) {
            'student' => 'student',
            'teacher' => 'teacher',
            'administrator' => 'management',
            default => 'restricted',
        };
        return ['role' => $role, 'actor_type' => $actorType, 'can_create' => $this->can('project.create'),
            'auto_leader' => $actorType === 'student', 'can_add_students' => in_array($actorType, ['student', 'teacher', 'management'], true),
            'must_select_leader' => in_array($actorType, ['teacher', 'management'], true), 'can_configure_full_team' => $actorType === 'management',
            'student_tutor_mode' => 'proposed', 'can_self_assign_tutor' => $actorType !== 'student'];
    }
}
