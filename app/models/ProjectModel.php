<?php

declare(strict_types=1);

final class ProjectModel
{
    /**
     * Obtiene el detalle extendido del proyecto activo del estudiante.
     */
    public function getProjectDetails(): array
    {
        return [
            'id' => 1,
            'title' => 'Sistema de Gestión Documental Académica',
            'description' => 'Diseño e implementación de una plataforma web institucional para el seguimiento, revisión y gestión documental de proyectos académicos en la carrera de Desarrollo de Software.',
            'semester' => 'Séptimo semestre',
            'tutor' => 'Ing. Tutor Asignado',
            'career' => 'Tecnología Superior en Desarrollo de Software',
            'line_of_research' => 'Desarrollo de Software y Tecnologías Emergentes',
            'status_class' => 'revision',
            'status_label' => 'En revisión',
            'created_at' => '02 Jun 2026',
            'last_activity' => '04 Jul 2026',
            'team' => [
                ['initial' => 'C', 'name' => 'Carlos Martínez', 'role' => 'Líder del Proyecto', 'email' => 'carlos.martinez@libertador.edu.ec'],
                ['initial' => 'A', 'name' => 'Andrés Pérez', 'role' => 'Integrante', 'email' => 'andres.perez@libertador.edu.ec'],
                ['initial' => 'L', 'name' => 'Lucía Gómez', 'role' => 'Integrante', 'email' => 'lucia.gomez@libertador.edu.ec']
            ]
        ];
    }

    /**
     * Obtiene el historial de entregas documentales (versiones) para el proyecto.
     * Diseñado para simular el avance gradual del proyecto durante el semestre.
     */
    public function getDocumentHistory(): array
    {
        return [
            [
                'version' => 4,
                'file_name' => 'Informe_actualizado_v4.pdf',
                'file_size' => '2.4 MB',
                'phase' => 'Avance de Marco Metodológico e Instrumentos',
                'delivery_date' => '03 Jul 2026',
                'delivery_time' => '16:30',
                'submitted_by' => 'Carlos Martínez',
                'status_class' => 'revision',
                'status_label' => 'En revisión',
                'feedback' => 'El tutor se encuentra evaluando la metodología ampliada y la justificación de las técnicas de recolección de datos presentadas en esta entrega.',
                'observations' => []
            ],
            [
                'version' => 3,
                'file_name' => 'Informe_Proyecto_v3.pdf',
                'file_size' => '2.1 MB',
                'phase' => 'Marco Teórico y Propuesta Conceptual',
                'delivery_date' => '24 Jun 2026',
                'delivery_time' => '11:15',
                'submitted_by' => 'Carlos Martínez',
                'status_class' => 'changes',
                'status_label' => 'Requiere cambios',
                'feedback' => 'El marco metodológico está muy genérico. Se deben detallar más los instrumentos de recolección de datos y unificar las referencias bajo formato APA 7 para validar el avance.',
                'observations' => [
                    'Marco metodológico: Ampliar la descripción del enfoque utilizado y justificar la técnica de recolección de datos.',
                    'Formato de referencias: Unificar el formato de citas y referencias bibliográficas antes del siguiente envío.'
                ]
            ],
            [
                'version' => 2,
                'file_name' => 'Propuesta_Ajustada_v2.pdf',
                'file_size' => '1.8 MB',
                'phase' => 'Ajuste de Objetivos y Alcance',
                'delivery_date' => '12 Jun 2026',
                'delivery_time' => '09:40',
                'submitted_by' => 'Carlos Martínez',
                'status_class' => 'approved',
                'status_label' => 'Aprobado con observaciones',
                'feedback' => 'Objetivos generales y específicos replanteados correctamente de acuerdo a la retroalimentación anterior. Se autoriza continuar con el desarrollo del marco teórico.',
                'observations' => [
                    'Detallar de mejor manera los alcances del sistema web en la sección de delimitación para evitar desvíos en el desarrollo.'
                ]
            ],
            [
                'version' => 1,
                'file_name' => 'Propuesta_Tema_Proyecto_v1.pdf',
                'file_size' => '1.2 MB',
                'phase' => 'Propuesta Inicial de Tema',
                'delivery_date' => '02 Jun 2026',
                'delivery_time' => '15:20',
                'submitted_by' => 'Carlos Martínez',
                'status_class' => 'approved',
                'status_label' => 'Aprobado',
                'feedback' => 'El tema es viable y pertinente para la carrera de Desarrollo de Software. Propuesta inicial aprobada para la estructuración formal del informe.',
                'observations' => []
            ]
        ];
    }

    /**
     * Fases académicas disponibles para nuevas entregas.
     */
    public function getCareerPhases(): array
    {
        return [
            ['id' => 'phase_1', 'label' => 'Propuesta de Tema (Fase I)'],
            ['id' => 'phase_2', 'label' => 'Marco Teórico (Fase II)'],
            ['id' => 'phase_3', 'label' => 'Marco Metodológico (Fase III)'],
            ['id' => 'phase_4', 'label' => 'Propuesta Técnica y Desarrollo (Fase IV)'],
            ['id' => 'phase_5', 'label' => 'Pruebas e Implantación (Fase V)'],
            ['id' => 'phase_6', 'label' => 'Informe Final Completo']
        ];
    }
}
