<?php

declare(strict_types=1);

final class DashboardModel
{
    public function getSummary(): array
    {
        // Resumen superior enfocado en decisiones rapidas del estudiante.
        return [
            [
                'cardClass' => 'approved-card',
                'icon' => 'fa-circle-check',
                'label' => 'Estado del informe',
                'title' => 'En revision del tutor',
                'description' => 'La version enviada ya fue recibida y esta siendo evaluada.',
                'meta' => 'Version 4 enviada',
            ],
            [
                'cardClass' => 'review-card',
                'icon' => 'fa-list-check',
                'label' => 'Pendiente clave',
                'title' => 'Corregir metodologia',
                'description' => 'Es la observacion mas importante antes de reenviar el documento.',
                'meta' => 'Prioridad alta',
            ],
            [
                'cardClass' => 'changes-card',
                'icon' => 'fa-calendar-check',
                'label' => 'Proxima accion',
                'title' => 'Revisar observaciones',
                'description' => 'Bloque de trabajo recomendado para cerrar pendientes recientes.',
                'meta' => '05 Jul - 09:00',
            ],
            [
                'cardClass' => 'documents-card',
                'icon' => 'fa-hourglass-half',
                'label' => 'Tiempo restante',
                'title' => '6 dias para correcciones',
                'description' => 'Fecha objetivo para entregar una version corregida del informe.',
                'meta' => 'Limite sugerido: 10 Jul',
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
            'description' => 'La version actual esta en revision. El foco ahora es resolver las observaciones de metodologia y referencias antes del siguiente envio.',
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
        // Observaciones accionables del informe actual.
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
        ];
    }

    public function getRecentActivity(): array
    {
        // Actividad resumida con eventos distintos entre si.
        return [
            ['icon' => 'fa-upload', 'title' => 'Version 4 enviada', 'text' => 'El informe actualizado fue registrado para revision.', 'time' => '03 Jul 2026'],
            ['icon' => 'fa-pen-to-square', 'title' => 'Revision del tutor', 'text' => 'Se registraron observaciones sobre la ultima version.', 'time' => '04 Jul 2026'],
            ['icon' => 'fa-folder-open', 'title' => 'Documento disponible', 'text' => 'La version revisada esta lista para consultar detalles y comentarios.', 'time' => '04 Jul 2026'],
        ];
    }

    public function getProcessDates(): array
    {
        // Fechas clave que ayudan a entender el avance real del proceso.
        return [
            ['label' => 'Ultima entrega', 'value' => '03 Jul 2026'],
            ['label' => 'Ultima revision', 'value' => '04 Jul 2026'],
            ['label' => 'Entrega objetivo', 'value' => '10 Jul 2026'],
        ];
    }

    public function getNotifications(): array
    {
        // Alertas utiles, sin repetir el listado de observaciones.
        return [
            ['title' => 'Revision finalizada', 'text' => 'Ya puedes consultar los comentarios de la version 4.', 'time' => 'Hace 2 horas'],
            ['title' => 'Prioridad sugerida', 'text' => 'Atiende primero la observacion del marco metodologico.', 'time' => 'Hoy'],
            ['title' => 'Entrega objetivo definida', 'text' => 'Planifica el reenvio corregido para el 10 de julio.', 'time' => 'Hoy'],
        ];
    }

    public function getReminders(): array
    {
        // Proximas acciones personales del usuario autenticado.
        return [
            ['date' => '05 Jul', 'title' => 'Corregir metodologia', 'text' => 'Ampliar enfoque, tecnica e instrumentos.'],
            ['date' => '07 Jul', 'title' => 'Normalizar referencias', 'text' => 'Unificar citas y bibliografia del informe.'],
            ['date' => '10 Jul', 'title' => 'Enviar version corregida', 'text' => 'Subir el documento final para nueva revision.'],
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
