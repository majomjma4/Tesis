<?php

declare(strict_types=1);

/** Resuelve el nombre persistido del resultado de correcciones durante la transición de esquema. */
final class ProjectDeliveryStatusService
{
    public static function correctionsRequested(PDO $db): string
    {
        $query = $db->query("SHOW COLUMNS FROM project_deliveries LIKE 'status'");
        $column = $query ? $query->fetch() : false;
        $type = strtolower((string)($column['Type'] ?? $column['type'] ?? ''));
        if (str_contains($type, "'corrections_requested'")) return 'corrections_requested';
        if (str_contains($type, "'changes_required'")) return 'changes_required';
        throw new ProjectStatusTransitionException('El esquema de entregas no reconoce el resultado de correcciones.', 503);
    }
}
