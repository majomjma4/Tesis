<?php

declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        $dashboard = new DashboardModel();

        if ((new AuthSessionService())->hasAdminAccess()) {
            $error = null;
            try {
                $adminDashboard = $dashboard->getAdminDashboard();
            } catch (Throwable $exception) {
                error_log('Admin dashboard error: ' . $exception->getMessage());
                $error = 'No fue posible consultar los indicadores administrativos en este momento.';
                $adminDashboard = $dashboard->emptyAdminDashboard();
            }

            View::render('dashboard/admin', [
                'currentPage' => 'dashboard',
                'title' => 'Inicio administrativo | Gestión Documental Académica',
                'bodyClass' => 'admin-dashboard-page',
                'pageStyles' => [asset('css/admin-dashboard.css')],
                'pageScript' => asset('js/admin-dashboard.js'),
                'dashboard' => $adminDashboard,
                'dashboardError' => $error,
            ]);
            return;
        }

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
            'projectUrls' => [
                'summary' => route('project-detail') . '&id=1&tab=summary',
                'deliveries' => route('project-detail') . '&id=1&tab=deliveries',
                'observations' => route('project-detail') . '&id=1&tab=observations',
                'history' => route('project-detail') . '&id=1&tab=history',
                'calendar' => route('project-detail') . '&id=1&tab=calendar',
                'notifications' => route('notifications'),
            ],
        ]);
    }
}
