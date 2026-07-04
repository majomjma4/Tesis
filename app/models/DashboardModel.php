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

    public function getCurrentReport(): array
    {
        // Informe principal del estudiante hasta conectar el modulo con persistencia real.
        return [
            'statusClass' => 'revision',
            'status' => 'En revision',
            'title' => 'Sistema de Gestion Documental',
            'description' => 'Informe academico principal en proceso de revision documental por el tutor asignado.',
            'semester' => 'Septimo semestre',
            'tutor' => 'Ing. Tutor Asignado',
            'version' => 'Version 4',
            'document' => 'Informe_actualizado_v4.pdf',
            'lastDelivery' => '03 Jul 2026',
            'lastReview' => '04 Jul 2026',
            'pendingObservations' => '2 observaciones pendientes',
        ];
    }

    public function getTeamMembers(): array
    {
        // Integrantes de ejemplo hasta conectar el proyecto con usuarios reales.
        return [
            ['initial' => 'C', 'name' => 'Carlos Martinez', 'role' => 'Lider'],
            ['initial' => 'A', 'name' => 'Andres Perez', 'role' => 'Integrante'],
            ['initial' => 'L', 'name' => 'Lucia Gomez', 'role' => 'Integrante'],
        ];
    }

    public function getObservations(): array
    {
        // Vista previa de observaciones recientes del informe actual.
        return [
            [
                'statusClass' => 'pending',
                'status' => 'Pendiente',
                'title' => 'Marco metodologico',
                'text' => 'Ampliar la descripcion del enfoque utilizado y justificar la tecnica de recoleccion de datos.',
                'date' => '04 Jul 2026',
            ],
            [
                'statusClass' => 'pending',
                'status' => 'Pendiente',
                'title' => 'Formato de referencias',
                'text' => 'Unificar el formato de citas y referencias bibliograficas antes del siguiente envio.',
                'date' => '04 Jul 2026',
            ],
                        [
                'statusClass' => 'pending',
                'status' => 'Pendiente',
                'title' => 'Marco metodologico',
                'text' => 'Ampliar la descripcion del enfoque utilizado y justificar la tecnica de recoleccion de datos.',
                'date' => '04 Jul 2026',
            ],
        ];
    }

    public function getRecentActivity(): array
    {
        // Actividad resumida; el historial completo pertenece al detalle del proyecto.
        return [
            ['icon' => 'fa-upload', 'title' => 'Version 4 enviada', 'text' => 'El informe actualizado fue registrado para revision.', 'time' => '03 Jul 2026'],
            ['icon' => 'fa-pen-to-square', 'title' => 'Revision del tutor', 'text' => 'Se registraron observaciones sobre la ultima version.', 'time' => '04 Jul 2026'],
            ['icon' => 'fa-arrows-rotate', 'title' => 'Estado actualizado', 'text' => 'El informe permanece en revision documental.', 'time' => '04 Jul 2026'],
        ];
    }

    public function getProcessDates(): array
    {
        // Fechas clave del proceso academico, sin reemplazar el calendario completo futuro.
        return [
            ['label' => 'Ultima entrega', 'value' => '03 Jul 2026'],
            ['label' => 'Ultima revision', 'value' => '04 Jul 2026'],
            ['label' => 'Proxima fecha', 'value' => 'Sin programar'],
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
