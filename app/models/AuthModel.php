<?php

declare(strict_types=1);

final class AuthModel
{
    public function getAllowedRoles(): array
    {
        return [
            ['icon' => 'fa-user-graduate', 'label' => 'Estudiantes'],
            ['icon' => 'fa-chalkboard-user', 'label' => 'Docentes'],
            ['icon' => 'fa-user-shield', 'label' => 'Administradores'],
        ];
    }
}
