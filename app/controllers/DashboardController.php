<?php

declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        $dashboard = new DashboardModel();

        View::render('dashboard/index', [
            'title' => 'Dashboard | Gestion Documental Academica',
            'pageScript' => asset('js/dashboard.js'),
            'summaryCards' => $dashboard->getSummary(),
            'projects' => $dashboard->getProjects(),
            'notifications' => $dashboard->getNotifications(),
            'reminders' => $dashboard->getReminders(),
            'calendar' => $dashboard->getCalendar(),
        ]);
    }
}
