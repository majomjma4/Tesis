<?php

declare(strict_types=1);

final class CalendarModel
{
    public function getEvents(): array
    {
        // Datos de demostracion hasta conectar el calendario con persistencia real.
        return [
            ['date' => '2026-07-03', 'time' => '09:00', 'title' => 'Entrega de avance', 'description' => 'Subir la version corregida del marco metodologico.', 'type' => 'delivery', 'typeLabel' => 'Entrega'],
            ['date' => '2026-07-07', 'time' => '11:30', 'title' => 'Reunion con el tutor', 'description' => 'Revision de observaciones y acuerdos del siguiente avance.', 'type' => 'meeting', 'typeLabel' => 'Reunion'],
            ['date' => '2026-07-10', 'time' => '23:59', 'title' => 'Cierre de correcciones', 'description' => 'Fecha limite para completar las correcciones pendientes.', 'type' => 'deadline', 'typeLabel' => 'Fecha limite'],
            ['date' => '2026-07-16', 'time' => '15:00', 'title' => 'Revision documental', 'description' => 'Validar formato, referencias y anexos del informe.', 'type' => 'review', 'typeLabel' => 'Revision'],
            ['date' => '2026-07-21', 'time' => '10:00', 'title' => 'Presentacion del proyecto', 'description' => 'Ensayo de presentacion con el equipo de trabajo.', 'type' => 'meeting', 'typeLabel' => 'Reunion'],
            ['date' => '2026-08-04', 'time' => '08:30', 'title' => 'Entrega final', 'description' => 'Publicar el documento aprobado en el repositorio.', 'type' => 'delivery', 'typeLabel' => 'Entrega'],
        ];
    }
}
