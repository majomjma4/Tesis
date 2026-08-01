<?php

declare(strict_types=1);

/** Provee ayudas institucionales de registro según el tipo de proyecto. */
final class ProjectTypeHelpService
{
    private const HELPS = [
        'thesis_profile' => 'Registra la propuesta inicial que define el problema, los objetivos, la justificación y la planificación del trabajo de titulación.',
        'thesis' => 'Registra el desarrollo completo del trabajo académico presentado como requisito para obtener el título profesional.',
        'pis' => 'Registra un proyecto integrador desarrollado por uno o varios estudiantes para aplicar de manera conjunta los conocimientos adquiridos durante su formación académica.',
        'practice' => 'Registra las actividades desarrolladas por el estudiante durante sus prácticas preprofesionales dentro de una entidad receptora, como parte de su formación profesional.',
        'community' => 'Registra las actividades académicas desarrolladas por estudiantes en proyectos orientados al fortalecimiento de la comunidad y su entorno.',
    ];

    public function helpFor(string $projectType): ?string
    {
        $projectType = trim($projectType);
        return self::HELPS[$projectType] ?? null;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return self::HELPS;
    }
}
