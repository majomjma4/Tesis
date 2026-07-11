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
                'type' => 'Proyecto de titulación',
                'keywords' => ['Inventario', 'Aplicación web', 'PHP']
            ],
            [
                'title' => 'Plataforma de Seguimiento de Prácticas Preprofesionales',
                'description' => 'Solución institucional para registrar actividades, evidencias y evaluaciones de prácticas estudiantiles.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'Daniel Paredes',
                'tutor' => 'Mgs. Luis Zambrano',
                'teacher_slug' => 'luis-zambrano',
                'semester' => '3',
                'category' => 'Proyecto PIS',
                'category_slug' => 'proyecto-pis',
                'year' => '2025',
                'pao' => 'pao-ii-2025',
                'pao_label' => 'PAO II 2025',
                'type' => 'Proyecto integrador',
                'keywords' => ['Seguimiento', 'Educación', 'Documentos']
            ],
            [
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
                'type' => 'Proyecto de extensión',
                'keywords' => ['Comunidad', 'Registro', 'Impacto social']
            ],
            [
                'title' => 'Sistema para Perfil de Tesis y Seguimiento',
                'description' => 'Herramienta para controlar perfiles de tesis, revisiones, observaciones y avances del proceso de titulación.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'Andrea Pérez',
                'tutor' => 'Abg. Alex Galarza',
                'teacher_slug' => 'alex-galarza',
                'semester' => '1',
                'category' => 'Perfil de Tesis',
                'category_slug' => 'perfil-de-tesis',
                'year' => '2024',
                'pao' => 'pao-ii-2024',
                'pao_label' => 'PAO II 2024',
                'type' => 'Proyecto inicial',
                'keywords' => ['Perfil', 'Seguimiento', 'Titulación']
            ],
            [
                'title' => 'Sistema para Perfil de Tesis y Seguimiento',
                'description' => 'Herramienta para controlar perfiles de tesis, revisiones, observaciones y avances del proceso de titulación.',
                'career' => 'Desarrollo de Software',
                'career_slug' => 'software',
                'authors' => 'Andrea Pérez',
                'tutor' => 'Abg. Alex Galarza',
                'teacher_slug' => 'alex-galarza',
                'semester' => '1',
                'category' => 'Perfil de Tesis',
                'category_slug' => 'perfil-de-tesis',
                'year' => '2024',
                'pao' => 'pao-ii-2024',
                'pao_label' => 'PAO II 2024',
                'type' => 'Proyecto inicial',
                'keywords' => ['Perfil', 'Seguimiento', 'Titulación']
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
            ['value' => 'Maribel Fierro', 'label' => 'Mgs. Maribel Fierro'],
            ['value' => 'Alex Galarza', 'label' => 'Abg. Alex Galarza'],
            ['value' => 'Dianita Alegria', 'label' => 'Lic. Diana Alegria'],
            ['value' => 'Maria Elena Navarrete', 'label' => 'Mgs. Maria Elena Navarrete'],
            ['value' => 'Henrry Mariño', 'label' => 'Mgs. Henrry Mariño'],
            ['value' => 'Diana Ramírez', 'label' => 'Mgs. Diana Ramírez'],
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
            ['value' => 'proyecto-de-titulacion', 'label' => 'Proyecto de titulación'],
            ['value' => 'proyecto-integrador', 'label' => 'Proyecto integrador'],
            ['value' => 'proyecto-de-extension', 'label' => 'Proyecto de extensión'],
            ['value' => 'proyecto-inicial', 'label' => 'Proyecto inicial']
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
