<?php

declare(strict_types=1);

final class CalendarController
{
    public function index(): void
    {
        $calendar = new CalendarModel();

        View::render('calendar/index', [
            'currentPage' => 'calendar',
            'title' => 'Calendario | Gestion Documental Academica',
            'bodyClass' => 'dashboard-page calendar-page',
            'pageScript' => asset('js/calendar.js'),
            'calendarEvents' => $calendar->getEvents(),
        ]);
    }
}
