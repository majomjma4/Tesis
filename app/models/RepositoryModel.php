<?php

declare(strict_types=1);

final class RepositoryModel
{
    /**
     * Obtiene los proyectos académicos publicados en el repositorio.
     */
    public function getPublishedProjects(): array
    {
        $projects = [
            [
                'id' => 1,
                'title' => 'Sistema de Gestión de Inventario para Microempresas',
                'description' => 'Aplicación web para controlar existencias, movimientos y reportes de inventario en pequeños negocios.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'María Torres y José Cedeño',
                'tutor' => 'Msc. Maribel Fierro Montero',
                'teacher_slug' => 'maribel-fierro',
                'semester' => '4',
                'category' => 'Tesis',
                'category_slug' => 'tesis',
                'year' => '2026',
                'pao' => 'pao-i-2026',
                'pao_label' => 'PAO I 2026',
                'type' => 'Tesis',
                'type_slug' => 'tesis',
                'downloads' => 154,
                'technologies' => ['PHP', 'Laravel', 'MySQL', 'MVC', 'Bootstrap', 'Git'],
                'keywords' => ['Inventario', 'Aplicación web', 'Microempresas', 'Control de existencias']
            ],
            [
                'id' => 2,
                'title' => 'Plataforma de Seguimiento de Prácticas Preprofesionales',
                'description' => 'Solución institucional para registrar actividades, evidencias y evaluaciones de prácticas estudiantiles.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'Daniel Paredes',
                'tutor' => 'Msc. Maria Elena Navarrete',
                'teacher_slug' => 'maria-navarrete',
                'semester' => '3',
                'category' => 'Prácticas preprofesionales',
                'category_slug' => 'practicas-preprofesionales',
                'year' => '2025',
                'pao' => 'pao-ii-2025',
                'pao_label' => 'PAO II 2025',
                'type' => 'Prácticas preprofesionales',
                'type_slug' => 'practicas-preprofesionales',
                'downloads' => 98,
                'technologies' => ['JavaScript', 'React', 'API REST', 'PostgreSQL', 'Docker'],
                'keywords' => ['Seguimiento', 'Educación', 'Documentos', 'Prácticas preprofesionales']
            ],
            [
                'id' => 3,
                'title' => 'Portal Web de Vinculación Comunitaria',
                'description' => 'Sistema para gestionar actividades de vinculación, registros de participantes y evidencias de impacto social.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'Carla Mena y David López',
                'tutor' => 'Msc. Maribel Fierro Montero',
                'teacher_slug' => 'maribel-fierro',
                'semester' => '2',
                'category' => 'Vinculación',
                'category_slug' => 'vinculacion',
                'year' => '2025',
                'pao' => 'pao-i-2025',
                'pao_label' => 'PAO I 2025',
                'type' => 'Vinculación',
                'type_slug' => 'vinculacion',
                'downloads' => 76,
                'technologies' => ['PHP', 'Vue', 'MariaDB', 'Bootstrap'],
                'keywords' => ['Comunidad', 'Registro', 'Impacto social', 'Vinculación'],
                'source_project_id' => 4,
            ],
            [
                'id' => 4,
                'title' => 'Sistema para Perfil de Tesis y Seguimiento',
                'description' => 'Herramienta para controlar perfiles de tesis, revisiones, observaciones y avances del proceso de titulación.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'Andrea Pérez',
                'tutor' => 'Abg. Alex Fabián Galarza',
                'teacher_slug' => 'alex-galarza',
                'semester' => '1',
                'category' => 'Perfil de Tesis',
                'category_slug' => 'perfil-tesis',
                'year' => '2024',
                'pao' => 'pao-ii-2024',
                'pao_label' => 'PAO II 2024',
                'type' => 'Perfil de tesis',
                'type_slug' => 'perfil-tesis',
                'downloads' => 43,
                'technologies' => ['C#', '.NET', 'SQL Server', 'MVC'],
                'keywords' => ['Perfil', 'Seguimiento', 'Titulación', 'Revisión académica']
            ],
        ];
        $extraTitles = [
            'Sistema de reservas para laboratorios',
            'Aplicación de control de asistencia',
            'Portal de seguimiento de tutorías',
            'Gestor documental para secretaría',
            'Panel de indicadores estudiantiles',
            'Plataforma de encuestas académicas',
            'Sistema de préstamos tecnológicos',
        ];
        $teachers = $this->getTeachers();
        foreach ($extraTitles as $index => $title) {
            $teacher = $teachers[$index % count($teachers)];
            $projects[] = [
                'id' => $index + 5,
                'title' => $title,
                'description' => 'Proyecto demostrativo publicado para comprobar la paginación y los filtros del repositorio.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => ['Adriana Ponce Vera','Bruno Cárdenas Mena','Camila Andrade Ruiz','David Guerrero Paz','Elena Morales Cedeño','Fernando Viteri León'][$index % 6],
                'tutor' => $teacher['label'],
                'teacher_slug' => $teacher['value'],
                'semester' => (string)(($index % 4) + 1),
                'category' => 'Proyecto integrador',
                'category_slug' => 'proyecto-integrador',
                'year' => '2026',
                'pao' => 'pao-ii-2026',
                'pao_label' => 'PAO II 2026',
                'type' => 'Proyecto integrador',
                'type_slug' => 'proyecto-integrador',
                'downloads' => 20 + ($index * 7),
                'technologies' => ['PHP', 'JavaScript', 'MariaDB'],
                'keywords' => ['Demostración', 'Paginación', 'Software'],
            ];
        }
        if (Database::isEnabled()) {
            try {
                $unavailableIds = array_map(
                    'intval',
                    Database::connection()->query(
                        "SELECT id FROM projects
                         WHERE status='published' AND is_available=0 AND deleted_at IS NULL"
                    )->fetchAll(PDO::FETCH_COLUMN)
                );
                if ($unavailableIds !== []) {
                    $projects = array_values(array_filter(
                        $projects,
                        static fn (array $project): bool => !in_array((int) $project['id'], $unavailableIds, true)
                    ));
                }
            } catch (Throwable $exception) {
                error_log('Repository project availability: ' . $exception->getMessage());
            }
        }
        return $projects;
    }

    public function findPublishedProjectById(int $projectId): ?array
    {
        foreach ($this->getPublishedProjects() as $project) {
            if ($project['id'] === $projectId) {
                return $project;
            }
        }

        return null;
    }

    public function getPublishedProjectDetail(int $projectId): ?array
    {
        $project = $this->findPublishedProjectById($projectId);
        if ($project === null) {
            return null;
        }

        $detailData = [
            1 => [
                'authors_list' => ['María Torres', 'José Cedeño'],
                'publication_date' => '15 de julio de 2026',
                'summary' => 'El proyecto propone una plataforma web para digitalizar el control de existencias en microempresas. Centraliza productos, movimientos, alertas y reportes para reducir errores manuales y facilitar la toma de decisiones de propietarios y administradores.',
            ],
            2 => [
                'authors_list' => ['Daniel Paredes'],
                'publication_date' => '28 de noviembre de 2025',
                'summary' => 'La solución organiza el seguimiento académico de las prácticas preprofesionales, permitiendo registrar actividades, evidencias, evaluaciones y observaciones en un único entorno institucional.',
            ],
            3 => [
                'authors_list' => ['Carla Mena', 'David López'],
                'publication_date' => '10 de junio de 2025',
                'summary' => 'El portal facilita la planificación y documentación de actividades de vinculación comunitaria, así como el seguimiento de participantes, evidencias e indicadores de impacto social.',
            ],
            4 => [
                'authors_list' => ['Andrea Pérez'],
                'publication_date' => '18 de diciembre de 2024',
                'summary' => 'La herramienta acompaña el proceso de elaboración del perfil de tesis, sus revisiones, observaciones y avances, conservando una trazabilidad clara para estudiantes y responsables académicos.',
            ],
            5 => [
                'authors_list' => ['Sofía Ruiz', 'Mateo Vera', 'Luis Castro'],
                'publication_date' => '22 de julio de 2026',
                'summary' => 'La aplicación móvil permite organizar actividades colaborativas de proyectos PIS, centralizar evidencias y consultar entregas desde dispositivos móviles de manera sencilla.',
            ],
        ];

        return array_merge($project, $detailData[$projectId], [
            'archive' => [
                'name' => 'Proyecto_' . $projectId . '_Final.zip',
                'path' => ROOT_PATH . '/storage/repository/project_' . $projectId . '.zip',
                'size' => '—',
                'files_count' => 0,
                'folders_count' => 0,
            ],
        ]);
    }

    public function getSemesters(): array
    {
        return [
            ['value' => '1', 'label' => 'Primer semestre'],
            ['value' => '2', 'label' => 'Segundo semestre'],
            ['value' => '3', 'label' => 'Tercer semestre'],
            ['value' => '4', 'label' => 'Cuarto semestre']
        ];
    }

    public function getTeachers(): array
    {
        return [
            ['value' => 'maribel-fierro', 'label' => 'Msc. Maribel Fierro Montero'],
            ['value' => 'maria-navarrete', 'label' => 'Msc. Maria Elena Navarrete'],
            ['value' => 'diana-alegria', 'label' => 'Lic. Diana Alegría Camino'],
            ['value' => 'diana-ramirez', 'label' => 'Msc. Diana Anaid Ramirez'],
            ['value' => 'alex-galarza', 'label' => 'Abg. Alex Fabián Galarza'],
            ['value' => 'henrry-marino', 'label' => 'Msc. Henrry Mariño Acosta'],
        ];
    }

    public function getCategories(): array
    {
        return [
            ['value' => 'proyecto-pis', 'label' => 'Proyecto PIS'],
            ['value' => 'practicas', 'label' => 'Prácticas'],
            ['value' => 'vinculacion', 'label' => 'Vinculación'],
            ['value' => 'tesis', 'label' => 'Tesis'],
            ['value' => 'perfil-de-tesis', 'label' => 'Perfil de Tesis']
        ];
    }

    public function getProjectTypes(): array
    {
        return [
            ['value' => 'tesis', 'label' => 'Tesis'],
            ['value' => 'perfil-tesis', 'label' => 'Perfil de tesis'],
            ['value' => 'practicas-preprofesionales', 'label' => 'Prácticas preprofesionales'],
            ['value' => 'proyecto-pis', 'label' => 'Proyecto PIS'],
            ['value' => 'vinculacion', 'label' => 'Vinculación']
        ];
    }

    public function getAcademicPeriods(): array
    {
        return [
            ['value' => 'pao-i-2026', 'label' => 'PAO I 2026'],
            ['value' => 'pao-ii-2026', 'label' => 'PAO II 2026'],
            ['value' => 'pao-i-2025', 'label' => 'PAO I 2025'],
            ['value' => 'pao-ii-2025', 'label' => 'PAO II 2025'],
            ['value' => 'pao-i-2024', 'label' => 'PAO I 2024'],
            ['value' => 'pao-ii-2024', 'label' => 'PAO II 2024']
        ];
    }
}
