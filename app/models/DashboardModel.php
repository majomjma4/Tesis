<?php

declare(strict_types=1);

final class DashboardModel
{
    public function getSummary(): array
    {
        // Resumen superior del dashboard academico.
        return [
            [
                'cardClass' => 'approved-card',
                'icon' => 'fa-thumbtack',
                'label' => 'Proyecto actual',
                'title' => 'Sistema de Gestion Documental',
                'description' => 'Estado actual del proyecto principal registrado en este semestre.',
                'meta' => 'En revision',
            ],
            [
                'cardClass' => 'review-card',
                'icon' => 'fa-pen-to-square',
                'label' => 'Observaciones',
                'title' => 'Revision de documentos',
                'description' => 'Observaciones registradas por el tutor que aun requieren atencion.',
                'meta' => '2 observaciones pendientes',
            ],
            [
                'cardClass' => 'changes-card',
                'icon' => 'fa-calendar-check',
                'label' => 'Proximo recordatorio',
                'title' => 'Reunion con tutor',
                'description' => 'Actividad academica personal mas cercana en tu calendario.',
                'meta' => '02 Jul - 15:30',
            ],
            [
                'cardClass' => 'documents-card',
                'icon' => 'fa-file-lines',
                'label' => 'Ultima entrega',
                'title' => 'Informe actualizado',
                'description' => 'Ultimo documento academico registrado para revision del proyecto.',
                'meta' => 'Hace 2 dias',
            ],
        ];
    }

    public function getProjects(): array
    {
        // Proyectos de ejemplo hasta conectar el modulo con persistencia real.
        return [
            [
                'statusClass' => 'revision',
                'status' => 'En revision',
                'title' => 'Sistema de Gestion Documental',
                'description' => 'Plataforma academica enfocada en la organizacion y administracion de proyectos estudiantiles.',
                'semester' => 'Septimo semestre',
                'tutor' => 'Ing. Tutor Asignado',
                'updatedAt' => 'Hace 2 dias',
                'footer' => 'Informe actualizado en revision',
            ],
            [
                'statusClass' => 'changes',
                'status' => 'Requiere cambios',
                'title' => 'Aplicacion Web Educativa',
                'description' => 'Proyecto orientado al desarrollo de herramientas digitales para apoyo academico institucional.',
                'semester' => 'Septimo semestre',
                'tutor' => 'Mgs. Tutor Asignado',
                'updatedAt' => 'Ayer',
                'footer' => 'Corregir observaciones del tutor',
            ],
        ];
    }

    public function getNotifications(): array
    {
        // Notificaciones visibles en el panel lateral.
        return [
            ['title' => 'Observacion registrada', 'text' => 'Tu tutor dejo una observacion en el informe actualizado.', 'time' => 'Hace 2 horas'],
            ['title' => 'Documento revisado', 'text' => 'Se reviso la ultima version del capitulo metodologico.', 'time' => 'Hace 1 dia'],
            ['title' => 'Recordatorio proximo', 'text' => 'Reunion con tutor programada para el 02 de julio.', 'time' => 'Hace 3 dias'],
        ];
    }

    public function getReminders(): array
    {
        // Recordatorios personales del usuario autenticado.
        return [
            ['date' => '02 Jul', 'title' => 'Reunion con tutor', 'text' => 'Sistema de Gestion Documental - 15:30'],
            ['date' => '05 Jul', 'title' => 'Revision de observaciones', 'text' => 'Aplicacion Web Educativa - 09:00'],
            ['date' => '10 Jul', 'title' => 'Entrega de correcciones', 'text' => 'Informe academico - 11:00'],
        ];
    }

    public function getCalendar(): array
    {
        // Estructura simple para pintar el calendario mensual en la vista.
        return [
            'month' => 'Julio 2026',
            'subtitle' => 'Recordatorios del semestre',
            'weekDays' => ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'],
            'days' => [
                ['number' => '29', 'class' => 'muted-day'],
                ['number' => '30', 'class' => 'muted-day'],
                ['number' => '1', 'class' => 'current-day'],
                ['number' => '2', 'class' => 'reminder-day'],
                ['number' => '3', 'class' => ''],
                ['number' => '4', 'class' => ''],
                ['number' => '5', 'class' => 'reminder-day'],
                ['number' => '6', 'class' => ''],
                ['number' => '7', 'class' => ''],
                ['number' => '8', 'class' => ''],
                ['number' => '9', 'class' => ''],
                ['number' => '10', 'class' => 'reminder-day'],
                ['number' => '11', 'class' => ''],
                ['number' => '12', 'class' => ''],
                ['number' => '13', 'class' => ''],
                ['number' => '14', 'class' => ''],
                ['number' => '15', 'class' => ''],
                ['number' => '16', 'class' => ''],
                ['number' => '17', 'class' => ''],
                ['number' => '18', 'class' => ''],
                ['number' => '19', 'class' => ''],
                ['number' => '20', 'class' => ''],
                ['number' => '21', 'class' => 'reminder-day'],
                ['number' => '22', 'class' => ''],
                ['number' => '23', 'class' => ''],
                ['number' => '24', 'class' => ''],
                ['number' => '25', 'class' => ''],
                ['number' => '26', 'class' => ''],
                ['number' => '27', 'class' => ''],
                ['number' => '28', 'class' => ''],
                ['number' => '29', 'class' => ''],
                ['number' => '30', 'class' => ''],
                ['number' => '31', 'class' => ''],
                ['number' => '1', 'class' => 'muted-day'],
                ['number' => '2', 'class' => 'muted-day'],
            ],
        ];
    }
}
