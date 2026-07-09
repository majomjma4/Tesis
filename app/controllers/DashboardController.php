<?php

declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        // Los datos del dashboard se mantienen fuera de la vista para respetar MVC.
        $dashboard = new DashboardModel();

        View::render('dashboard/index', [
            'currentPage' => 'dashboard',
            'title' => 'Dashboard | Gestion Documental Academica',
            'pageScript' => asset('js/dashboard.js'),
            'summaryCards' => $dashboard->getSummary(),
            'currentReport' => $dashboard->getCurrentReport(),
            'teamMembers' => $dashboard->getTeamMembers(),
            'observations' => $dashboard->getObservations(),
            'recentActivity' => $dashboard->getRecentActivity(),
            'processDates' => $dashboard->getProcessDates(),
            'notifications' => $dashboard->getNotifications(),
            'reminders' => $dashboard->getReminders(),
        ]);
    }
}
