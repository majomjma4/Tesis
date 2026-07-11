<?php

declare(strict_types=1);

final class RepositoryModel
{
    /**
     * Obtiene los proyectos académicos publicados en el repositorio.
     */
    public function getPublishedProjects(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Sistema de Gestión de Inventario para Microempresas',
                'description' => 'Aplicación web para controlar existencias, movimientos y reportes de inventario en pequeños negocios.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'María Torres y José Cedeño',
                'tutor' => 'Ing. Andrea Salazar',
                'teacher_slug' => 'andrea-salazar',
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
                'tutor' => 'Mgs. Luis Zambrano',
                'teacher_slug' => 'luis-zambrano',
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
                'tutor' => 'Mgs. Maribel Fierro',
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
                'keywords' => ['Comunidad', 'Registro', 'Impacto social', 'Vinculación']
            ],
            [
                'id' => 4,
                'title' => 'Sistema para Perfil de Tesis y Seguimiento',
                'description' => 'Herramienta para controlar perfiles de tesis, revisiones, observaciones y avances del proceso de titulación.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'Andrea Pérez',
                'tutor' => 'Abg. Alex Galarza',
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
            [
                'id' => 5,
                'title' => 'Aplicación Móvil para Gestión de Proyectos PIS',
                'description' => 'Aplicación móvil para organizar entregas, evidencias y actividades colaborativas de proyectos integradores.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'Sofía Ruiz, Mateo Vera y Luis Castro',
                'tutor' => 'Mgs. Luis Zambrano',
                'teacher_slug' => 'luis-zambrano',
                'semester' => '4',
                'category' => 'Proyecto PIS',
                'category_slug' => 'proyecto-pis',
                'year' => '2026',
                'pao' => 'pao-ii-2026',
                'pao_label' => 'PAO II 2026',
                'type' => 'Proyecto PIS',
                'type_slug' => 'proyecto-pis',
                'downloads' => 121,
                'technologies' => ['Flutter', 'Dart', 'Firebase', 'GitHub', 'API REST', 'Aplicación móvil'],
                'keywords' => ['Proyecto integrador', 'Trabajo colaborativo', 'Evidencias', 'Aplicación móvil']
            ],

        ];
    }

    /**
     * Obtiene guías y documentos de apoyo disponibles en el repositorio.
     */
    public function getSupportDocuments(): array
    {
        return [
            [
                'title' => 'Guía para la elaboración del informe final',
                'description' => 'Documento base para estructurar objetivos, marco teórico, metodología y conclusiones del trabajo final.',
                'type' => 'Guía académica',
                'year' => '2026',
                'pao_label' => 'PAO I 2026',
                'category_slug' => 'tesis',
                'category_label' => 'Tesis',
                'keywords' => ['Formato', 'Informe', 'Titulación']
            ],
            [
                'title' => 'Plantilla de presentación de avances',
                'description' => 'Material de apoyo para exponer avances, resultados parciales y observaciones del tutor en clase.',
                'type' => 'Documento de apoyo',
                'year' => '2026',
                'pao_label' => 'PAO II 2026',
                'category_slug' => 'proyecto-pis',
                'category_label' => 'Proyectos PIS',
                'keywords' => ['Presentación', 'Avance', 'Tutoría']
            ],
            [
                'title' => 'Formato de revisión de entregables',
                'description' => 'Documento para controlar la entrega de informes, anexos y correcciones finales antes de la defensa.',
                'type' => 'Guía práctica',
                'year' => '2025',
                'pao_label' => 'PAO II 2025',
                'category_slug' => 'practicas',
                'category_label' => 'Prácticas',
                'keywords' => ['Entrega', 'Checklist', 'Correcciones']
            ],
            [
                'title' => 'Guía para defensa de proyecto',
                'description' => 'Apoyo para preparar la presentación oral, responder observaciones y organizar la defensa final.',
                'type' => 'Guía académica',
                'year' => '2024',
                'pao_label' => 'PAO I 2024',
                'category_slug' => 'vinculacion',
                'category_label' => 'Vinculación',
                'keywords' => ['Defensa', 'Presentación', 'Exposición']
            ],
            [
                'title' => 'Guía para defensa de proyecto',
                'description' => 'Apoyo para preparar la presentación oral, responder observaciones y organizar la defensa final.',
                'type' => 'Guía académica',
                'year' => '2024',
                'pao_label' => 'PAO I 2024',
                'category_slug' => 'vinculacion',
                'category_label' => 'Vinculación',
                'keywords' => ['Defensa', 'Presentación', 'Exposición']
            ],
           
        ];
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
            ['value' => 'andrea-salazar', 'label' => 'Ing. Andrea Salazar'],
            ['value' => 'luis-zambrano', 'label' => 'Mgs. Luis Zambrano'],
            ['value' => 'maribel-fierro', 'label' => 'Mgs. Maribel Fierro'],
            ['value' => 'alex-galarza', 'label' => 'Abg. Alex Galarza'],
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
